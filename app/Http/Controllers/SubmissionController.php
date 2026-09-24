<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Submission;
use App\Models\SubmissionActivityLog;
use Carbon\Carbon;
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
            ->when($request->filled('program_code'), fn ($q) => $q->where('program_code', $request->program_code))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('participant.employee', function ($e) use ($search) {
                        $e->where('FIRSTNAME', 'like', "%{$search}%")
                            ->orWhere('LASTNAME', 'like', "%{$search}%")
                            ->orWhere('EMPCODE', 'like', "%{$search}%");
                    })->orWhereHas('requirement', fn ($r) => $r->where('title', 'like', "%{$search}%"));
                });
            })
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
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'program_code' => 'required',
            'batch_id' => 'required|exists:batches,id',
            'requirement_id' => 'required|exists:requirements,id',
            'status' => 'nullable|in:Pending,Approved,Rejected',
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string|max:2000',
            'file' => 'nullable|mimes:pdf|max:20480',
        ]);

        // Hanapin kung may existing na submission para sa participant+requirement
        $submission = Submission::where('participant_id', $request->participant_id)
            ->where('requirement_id', $request->requirement_id)
            ->first();

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
            'status' => $request->status ?? ($submission->status ?? 'Pending'),
            'file_path' => $path,
            'notes' => $request->notes ?? $submission?->notes ?? '',
            'remarks' => $request->remarks ?? $submission?->remarks,
            'submitted_at' => $path ? Carbon::now() : $submission?->submitted_at,
            'reviewed_at' => $request->filled('status') ? Carbon::now() : $submission?->reviewed_at,
            'reviewed_by' => $request->filled('status') ? ($request->user()->name ?? 'System') : $submission?->reviewed_by,
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
            'requirement_name' => $submission->requirement?->name,
            'program_code' => $submission->program_code,
            'batch_label' => $submission->batch?->batch,
            'action' => $action,
            'status' => $submission->status,
            'performed_by' => $request->user()->name ?? 'System',
            'meta' => $meta,
        ]);
    }
}
