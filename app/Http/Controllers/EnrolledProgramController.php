<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Participant;
use App\Models\Requirement;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EnrolledProgramController extends Controller
{
    /**
     * Tinatawag ito ng controller na nag-re-render ng Welcome/index, para
     * mabuo ang data ng "My Enrolled Programs" section sa landing page.
     * Naka-link ang participants sa users sa pamamagitan ng `empcode`
     * (parehong column sa dalawang tables), hindi user_id.
     */
    public static function forUser($user): array
    {
        if (empty($user->empcode)) {
            return [];
        }

        return Participant::with(['batch.program.coverPage', 'batch.requirements', 'submissions'])
            ->where('empcode', $user->empcode)
            ->get()
            ->filter(fn ($p) => $p->batch && $p->batch->program)
            ->map(fn ($p) => self::transform($p))
            ->values()
            ->toArray();
    }

    /**
     * "View full program" page — pinipindot mula sa card sa landing page.
     */
    public function show(Request $request, Batch $batch)
    {
        $user = $request->user();

        abort_if(empty($user->empcode), 404);

        $participant = Participant::with([
                'batch.program.coverPage',
                'batch.program.resourceSpeakers',
                'batch.requirements',
                'submissions',
            ])
            ->where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        return Inertia::render('MyPrograms/Show', [
            'program' => self::transform($participant, withRequirements: true),
        ]);
    }

    /**
     * Helper: hanapin ang participant record ng currently logged-in user
     * sa isang batch, o i-abort kung wala.
     */
    protected function ownParticipant(Request $request, Batch $batch): Participant
    {
        $user = $request->user();
        abort_if(empty($user->empcode), 403);

        return Participant::where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();
    }

    /**
     * Submit o i-update ang sariling submission ng participant para sa isang
     * requirement: file (PDF) at/o notes. Hiwalay 'to sa SubmissionController
     * (admin-only, ginagamit para sa pag-review) — dito, sariling submission
     * lang ng currently logged-in participant ang puwedeng baguhin.
     *
     * - Kapag may bagong file na in-upload → bumabalik sa "Pending" ang status
     *   (kahit Rejected dati), at nire-reset ang reviewed_at/reviewed_by.
     * - Kapag notes lang ang binago (walang bagong file) → hindi nagbabago
     *   ang status, file_path lang at notes ang naa-update.
     * - Hindi na pwedeng baguhin kapag "Approved" na.
     */
    public function submitRequirement(Request $request, Batch $batch, Requirement $requirement)
    {
        abort_unless($requirement->batch_id === $batch->id, 404);

        $participant = $this->ownParticipant($request, $batch);

        $request->validate([
            'file'  => ['nullable', 'mimes:pdf', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = Submission::where('participant_id', $participant->id)
            ->where('requirement_id', $requirement->id)
            ->first();

        abort_if($submission && $submission->status === 'Approved', 403, 'This requirement is already approved and can no longer be edited.');

        if (! $request->hasFile('file') && ! $submission) {
            return back()->withErrors(['file' => 'Please attach a PDF file.'])->withInput();
        }

        $path          = $submission->file_path ?? null;
        $newFileUploaded = $request->hasFile('file');

        if ($newFileUploaded) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $file     = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path     = $file->storeAs('submissions', $filename, 'public');
        }

        Submission::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'requirement_id' => $requirement->id,
            ],
            [
                'program_code' => $batch->program_code,
                'batch_id'     => $batch->id,
                'status'       => $newFileUploaded ? 'Pending' : ($submission->status ?? 'Pending'),
                'file_path'    => $path,
                'notes'        => $request->filled('notes') ? $request->input('notes') : ($submission->notes ?? null),
                'submitted_at' => $newFileUploaded ? now() : ($submission->submitted_at ?? now()),
                'reviewed_at'  => $newFileUploaded ? null : ($submission->reviewed_at ?? null),
                'reviewed_by'  => $newFileUploaded ? null : ($submission->reviewed_by ?? null),
            ]
        );

        return back()->with('success', $newFileUploaded ? 'Requirement submitted successfully. Awaiting review.' : 'Notes updated.');
    }

    /**
     * I-delete ang sariling submission ng participant. Hindi pwede kapag
     * Approved na (panatilihin ang audit trail para sa mga naaprubahan na).
     */
    public function destroySubmission(Request $request, Batch $batch, Requirement $requirement)
    {
        $participant = $this->ownParticipant($request, $batch);

        $submission = Submission::where('participant_id', $participant->id)
            ->where('requirement_id', $requirement->id)
            ->first();

        abort_if(! $submission, 404);
        abort_if($submission->status === 'Approved', 403, 'Approved submissions cannot be deleted.');

        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();

        return back()->with('success', 'Submission deleted.');
    }

    protected static function transform(Participant $participant, bool $withRequirements = false): array
    {
        $batch        = $participant->batch;
        $program      = $batch->program;
        $requirements = $batch->requirements;

        $submissions = $participant->submissions->keyBy('requirement_id');

        $reqList = $requirements->map(function ($req) use ($submissions) {
            $sub = $submissions->get($req->id);

            return [
                'id'           => $req->id,
                'title'        => $req->title,
                'name'         => $req->name,
                'due_date'     => optional($req->due_date)->toDateString() ?? $req->due_date,
                'is_required'  => (bool) $req->is_required,
                'status'       => $sub->status ?? null, // Pending | Approved | Rejected | null
                'submitted_at' => $sub->submitted_at ?? null,
                'remarks'      => $sub->remarks ?? null,     // reviewer remarks (read-only)
                'notes'        => $sub->notes ?? null,       // participant's own notes (editable)
                'file_url'     => $sub && $sub->file_path ? '/storage/' . $sub->file_path : null,
                'file_name'    => $sub && $sub->file_path ? basename($sub->file_path) : null,
            ];
        });

        $missingCount  = $reqList->filter(fn ($r) => $r['is_required'] && is_null($r['status']))->count();
        $pendingCount  = $reqList->filter(fn ($r) => $r['status'] === 'Pending')->count();
        $approvedCount = $reqList->filter(fn ($r) => $r['status'] === 'Approved')->count();
        $rejectedCount = $reqList->filter(fn ($r) => $r['status'] === 'Rejected')->count();

        $data = [
            'batch_id'              => $batch->id,
            'program_id'            => $program->id,
            'program_title'         => $program->title,
            'program_code'          => $program->program_code,
            'batch_label'           => $batch->batch,
            'modality'              => $batch->modality,
            'venue'                 => $batch->venue,
            'date_start'            => $batch->date_start,
            'date_end'              => $batch->date_end,
            'year'                  => Carbon::parse($batch->date_start)->year,
            'total_hours'           => (float) $batch->hours,
            'hours_completed'       => (float) ($participant->hours ?? 0),
            'attendance'            => $participant->attendance ?? 'Pending',
            'cover_image'           => $program->coverPage ? '/storage/' . $program->coverPage->image : null,
            'requirements_total'    => $reqList->count(),
            'requirements_missing'  => $missingCount,
            'requirements_pending'  => $pendingCount,
            'requirements_approved' => $approvedCount,
            'requirements_rejected' => $rejectedCount,
        ];

        if ($withRequirements) {
            $data['requirements'] = $reqList->values();

            $data['resource_speakers'] = $program->resourceSpeakers
                ? $program->resourceSpeakers->map(fn ($s) => [
                    'id'           => $s->id,
                    'name'         => $s->name,
                    'designation'  => $s->designation,
                    'affiliation'  => $s->affiliation,
                    'topic'        => $s->topic,
                    'expertise'    => $s->expertise,
                    'date_engaged' => optional($s->date_engaged)->toDateString() ?? $s->date_engaged,
                ])->values()->toArray()
                : [];
        }

        return $data;
    }
}