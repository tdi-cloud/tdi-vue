<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Submission;
use App\Models\SubmissionActivityLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SubmissionController extends Controller
{
    // GET /submissions
    // Global na "inbox" ng lahat ng requirement submissions, lintas sa lahat
    // ng program — hindi tulad ng per-program na Submissions tab (ProgramController@show).
    public function index(Request $request)
    {
        $query = Submission::with(['participant.employee', 'participant.justification', 'batch', 'requirement', 'program'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->tap(fn ($q) => $this->applyListFilters($q, $request))
            ->orderByDesc('submitted_at');

        $submissions = $query->paginate(20)->withQueryString();

        return Inertia::render('Submissions/index', [
            'submissions' => $submissions,
            'programs' => Program::orderBy('title')->get(['id', 'program_code', 'title']),
            'filters' => $request->only(['status', 'program_code', 'search']),
            'stats' => [
                'total' => Submission::count(),
                'pending' => Submission::where('status', 'Pending')->count(),
                'approved' => Submission::where('status', 'Approved')->count(),
                'rejected' => Submission::where('status', 'Rejected')->count(),
            ],
            // "Approve all" here spans every program, so it's superadmin only.
            'canApproveAll' => $request->user()->isSuperAdmin(),
            'approvablePendingCount' => $this->applyListFilters(Submission::where('status', 'Pending'), $request)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'program_code' => 'required',
            'batch_id' => 'required|exists:batches,id',
            // Without a requirement it is an "other document", which needs its own title
            'requirement_id' => 'nullable|exists:requirements,id',
            'document_title' => 'nullable|required_without:requirement_id|string|max:255',
            'submission_id' => 'nullable|integer',
            'status' => 'nullable|in:Pending,Approved,Rejected',
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string|max:2000',
            'file' => 'nullable|mimes:pdf|max:20480',
        ]);

        // Hanapin kung may existing na submission para sa participant+requirement
        $submission = $request->filled('requirement_id')
            ? Submission::where('participant_id', $request->participant_id)
                ->where('requirement_id', $request->requirement_id)
                ->first()
            : Submission::where('participant_id', $request->participant_id)
                ->whereNull('requirement_id')
                ->find($request->submission_id);

        if (! $request->filled('requirement_id') && ! $submission && ! $request->hasFile('file')) {
            return back()->withErrors(['file' => 'Please attach a PDF file.']);
        }

        $path = $submission?->file_path;

        if ($request->hasFile('file')) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $file = $request->file('file');
            $filename = time().'_'.$this->sanitizeFilename($file->getClientOriginalName());
            $path = $file->storeAs('submissions', $filename, 'public');
        }

        $data = [
            'participant_id' => $request->participant_id,
            'program_code' => $request->program_code,
            'batch_id' => $request->batch_id,
            'requirement_id' => $request->requirement_id,
            'document_title' => $request->filled('requirement_id') ? $submission?->document_title : $request->document_title,
            'status' => $request->status ?? ($submission->status ?? 'Pending'),
            'file_path' => $path,
            'notes' => $request->notes ?? $submission?->notes ?? '',
            'remarks' => $request->remarks ?? $submission?->remarks,
            'submitted_at' => $path ? Carbon::now() : $submission?->submitted_at,
            'reviewed_at' => $request->filled('status') ? Carbon::now() : $submission?->reviewed_at,
            'reviewed_by' => $request->filled('status') ? ($request->user()->name ?? 'System') : $submission?->reviewed_by,
            // Empcode ng unang nag-encode/nag-upload — hindi ito nababago ng mga susunod na edit.
            'added_by' => $submission ? $submission->added_by : $request->user()?->empcode,
        ];

        if ($submission) {
            $submission->update($data);

            $changedFields = array_keys(collect($submission->getChanges())->except('updated_at')->all());
            if (! empty($changedFields)) {
                $this->logActivity($request, $submission, 'updated', ['changed_fields' => $changedFields]);
            }
        } else {
            $submission = Submission::create($data);
            $this->logActivity($request, $submission, 'encoded', ['file_uploaded' => $request->hasFile('file')]);
        }

        return back()->with('success', 'Submission saved successfully.');
    }

    /**
     * Review ng submission — Approve o Reject, may remarks.
     */
    public function review(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $submission->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $request->user()->name ?? 'System',
        ]);

        $this->logActivity($request, $submission, 'reviewed', ['remarks' => $validated['remarks'] ?? null]);

        return back()->with('success', 'Submission reviewed successfully.');
    }

    /**
     * PATCH /submissions/{submission}/submitted-at (admins and above)
     *
     * Corrects the date a requirement was submitted — e.g. it was handed in
     * on time but encoded late — which also clears a wrong "Late" indication.
     */
    public function updateSubmittedAt(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'submitted_at' => 'required|date|before_or_equal:now',
        ], [
            'submitted_at.before_or_equal' => 'The submission date cannot be in the future.',
        ]);

        $previous = $submission->submitted_at?->toDateTimeString();
        $submission->update(['submitted_at' => Carbon::parse($validated['submitted_at'])]);

        $this->logActivity($request, $submission, 'updated', [
            'changed_fields' => ['submitted_at'],
            'previous_submitted_at' => $previous,
            'new_submitted_at' => $submission->submitted_at->toDateTimeString(),
        ]);

        return back()->with('success', 'Submission date updated.');
    }

    /**
     * POST /programs/{program}/submissions/approve-all (admins)
     *
     * Approves every Pending submission of one program — optionally only one
     * batch — so admins don't have to review them one by one. Rejected
     * submissions are left untouched.
     */
    public function approveAllForProgram(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => 'nullable|integer|exists:batches,id',
        ]);

        $query = Submission::where('program_code', $program->program_code)
            ->when($validated['batch_id'] ?? null, fn ($q, $batchId) => $q->where('batch_id', $batchId));

        $count = $this->approvePending($request, $query);

        return back()->with('success', $count ? "{$count} submission(s) approved." : 'There are no pending submissions to approve.');
    }

    /**
     * POST /submissions/approve-all (superadmins only)
     *
     * Approves every Pending submission across all programs, narrowed by the
     * same program/search filters the global Submissions page is showing.
     */
    public function approveAll(Request $request): RedirectResponse
    {
        $request->validate([
            'program_code' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $query = $this->applyListFilters(Submission::query(), $request);

        $count = $this->approvePending($request, $query);

        return back()->with('success', $count ? "{$count} submission(s) approved." : 'There are no pending submissions to approve.');
    }

    /**
     * Program + search filters of the global Submissions page — shared by the
     * list and "Approve all" so the bulk action matches what is shown.
     *
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    private function applyListFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('program_code'), fn ($q) => $q->where('program_code', $request->program_code))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('participant.employee', function ($e) use ($search) {
                        $e->where('FIRSTNAME', 'like', "%{$search}%")
                            ->orWhere('LASTNAME', 'like', "%{$search}%")
                            ->orWhere('EMPCODE', 'like', "%{$search}%");
                    })->orWhereHas('requirement', fn ($r) => $r->where('title', 'like', "%{$search}%"))
                        ->orWhere('document_title', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Approves the Pending submissions matched by the query, logging each one
     * the same way a single review is logged. Returns how many were approved.
     *
     * @param  Builder<Submission>  $query
     */
    private function approvePending(Request $request, Builder $query): int
    {
        $reviewer = $request->user()->name ?? 'System';
        $count = 0;

        $query->where('status', 'Pending')
            ->with(['participant.employee', 'requirement', 'batch'])
            ->chunkById(200, function ($submissions) use ($request, $reviewer, &$count) {
                foreach ($submissions as $submission) {
                    $submission->update([
                        'status' => 'Approved',
                        'reviewed_at' => Carbon::now(),
                        'reviewed_by' => $reviewer,
                    ]);
                    $this->logActivity($request, $submission, 'reviewed', ['bulk_approved' => true]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Delete ng submission, kasama ang file nito sa storage.
     */
    public function destroy(Request $request, Submission $submission)
    {
        $this->logActivity($request, $submission, 'deleted');

        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();

        return back()->with('success', 'Submission deleted successfully.');
    }

    /**
     * Strip directory components and any character outside a safe allow-list
     * from a user-supplied filename before it's used to build a storage path.
     */
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($name));
    }

    /**
     * I-record ang isang row sa "daily monitoring" audit trail ng submissions
     * — ginagamit ng hidden na `/submissions/activity-log` na page.
     *
     * @param  array<string, mixed>|null  $meta
     */
    private function logActivity(Request $request, Submission $submission, string $action, ?array $meta = null): void
    {
        $submission->loadMissing(['participant.employee', 'requirement', 'batch']);

        SubmissionActivityLog::create([
            'submission_id' => $submission->id,
            'participant_name' => $submission->participant?->employee?->name,
            'requirement_name' => $submission->requirement?->name ?? $submission->document_title,
            'program_code' => $submission->program_code,
            'batch_label' => $submission->batch?->batch,
            'action' => $action,
            'status' => $submission->status,
            'performed_by' => $request->user()->name ?? 'System',
            'meta' => $meta,
        ]);
    }
}
