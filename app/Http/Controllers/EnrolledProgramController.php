<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Requirement;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EnrolledProgramController extends Controller
{
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

    public function show(Request $request, Batch $batch)
    {
        $user = $request->user();

        abort_if(empty($user->empcode), 404);

        $participant = Participant::with([
            'batch.program.coverPage',
            'batch.program.resourceSpeakers',
            'batch.program.supportingDocuments',
            'batch.requirements',
            'submissions',
            'justification',
        ])
            ->where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        // ── Load certificates for this participant in this batch ──
        $certificates = Certificate::where('participant_id', $participant->id)
            ->where('batch_id', $batch->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'certificate_number' => $c->certificate_number,
                'status' => $c->status,
                'issued_date' => $c->issued_date?->toDateString(),
                'hours' => (float) $c->hours,
                'file_url' => $c->file_path ? '/storage/'.$c->file_path : null,
                'file_name' => $c->file_name,
                'uploaded_by' => $c->uploaded_by,
                'issued_by' => $c->issued_by,
                'remarks' => $c->remarks,
            ])
            ->values();

        return Inertia::render('MyPrograms/Show', [
            'program' => array_merge(
                self::transform($participant, withRequirements: true),
                ['certificates' => $certificates]
            ),
        ]);
    }

    protected function ownParticipant(Request $request, Batch $batch): Participant
    {
        $user = $request->user();
        abort_if(empty($user->empcode), 403);

        return Participant::where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();
    }

    public function submitRequirement(Request $request, Batch $batch, Requirement $requirement)
    {
        abort_unless($requirement->batch_id === $batch->id, 404);

        $participant = $this->ownParticipant($request, $batch);

        $request->validate([
            'file' => ['nullable', 'mimes:pdf', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = Submission::where('participant_id', $participant->id)
            ->where('requirement_id', $requirement->id)
            ->first();

        abort_if($submission && $submission->status === 'Approved', 403, 'This requirement is already approved and can no longer be edited.');

        if (! $request->hasFile('file') && ! $submission) {
            return back()->withErrors(['file' => 'Please attach a PDF file.'])->withInput();
        }

        $path = $submission->file_path ?? null;
        $newFileUploaded = $request->hasFile('file');

        if ($newFileUploaded) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $file = $request->file('file');
            $filename = time().'_'.$file->getClientOriginalName();
            $path = $file->storeAs('submissions', $filename, 'public');
        }

        Submission::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'requirement_id' => $requirement->id,
            ],
            [
                'program_code' => $batch->program_code,
                'batch_id' => $batch->id,
                'status' => $newFileUploaded ? 'Pending' : ($submission->status ?? 'Pending'),
                'file_path' => $path,
                'notes' => $request->filled('notes') ? $request->input('notes') : ($submission->notes ?? null),
                'submitted_at' => $newFileUploaded ? now() : ($submission->submitted_at ?? now()),
                'reviewed_at' => $newFileUploaded ? null : ($submission->reviewed_at ?? null),
                'reviewed_by' => $newFileUploaded ? null : ($submission->reviewed_by ?? null),
            ]
        );

        return back()->with('success', $newFileUploaded
            ? 'Requirement submitted successfully. Awaiting review.'
            : 'Notes updated.');
    }

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

    // POST /my-programs/{batch}/absent-justification
    // Lets a participant self-report a non-attendance/failure justification at
    // any time — independent of requirement submission progress. Uploading it
    // automatically marks the participant Absent for this batch, so they're
    // no longer expected to submit the regular requirements.
    public function submitJustification(Request $request, Batch $batch)
    {
        $participant = $this->ownParticipant($request, $batch);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('file')->store('justifications', 'public');

        if ($participant->justification) {
            Storage::disk('public')->delete($participant->justification->file_path);
            $participant->justification->update(['file_path' => $path]);
        } else {
            $participant->justification()->create(['file_path' => $path]);
        }

        $participant->update([
            'attendance' => 'Absent',
            'hours' => 0,
        ]);

        return back()->with('success', 'Justification uploaded. You have been marked as Absent for this program and no longer need to submit the requirements.');
    }

    // DELETE /my-programs/{batch}/absent-justification
    public function destroyJustification(Request $request, Batch $batch)
    {
        $participant = $this->ownParticipant($request, $batch);

        abort_if(! $participant->justification, 404);

        Storage::disk('public')->delete($participant->justification->file_path);
        $participant->justification->delete();

        if ($participant->attendance === 'Absent') {
            $participant->update(['attendance' => 'Pending']);
        }

        return back()->with('success', 'Justification removed.');
    }

    protected static function transform(Participant $participant, bool $withRequirements = false): array
    {
        $batch = $participant->batch;
        $program = $batch->program;
        $requirements = $batch->requirements;
        $submissions = $participant->submissions->keyBy('requirement_id');

        $reqList = $requirements->map(function ($req) use ($submissions) {
            $sub = $submissions->get($req->id);

            return [
                'id' => $req->id,
                'title' => $req->title,
                'name' => $req->name,
                'due_date' => optional($req->due_date)->toDateString() ?? $req->due_date,
                'is_required' => (bool) $req->is_required,
                'status' => $sub->status ?? null,
                'submitted_at' => $sub->submitted_at ?? null,
                'remarks' => $sub->remarks ?? null,
                'notes' => $sub->notes ?? null,
                'file_url' => $sub && $sub->file_path ? '/storage/'.$sub->file_path : null,
                'file_name' => $sub && $sub->file_path ? basename($sub->file_path) : null,
            ];
        });

        $data = [
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'program_title' => $program->title,
            'program_code' => $program->program_code,
            'program_type' => $program->type,
            'program_description' => $program->description,
            'batch_label' => $batch->batch,
            'modality' => $batch->modality,
            'venue' => $batch->venue,
            'date_start' => $batch->date_start,
            'date_end' => $batch->date_end,
            'year' => Carbon::parse($batch->date_start)->year,
            'total_hours' => (float) $batch->hours,
            'hours_completed' => (float) ($participant->hours ?? 0),
            'attendance' => $participant->attendance ?? 'Pending',
            'cover_image' => $program->coverPage ? '/storage/'.$program->coverPage->image : null,
            'requirements_total' => $reqList->count(),
            'requirements_missing' => $reqList->filter(fn ($r) => $r['is_required'] && is_null($r['status']))->count(),
            'requirements_pending' => $reqList->filter(fn ($r) => $r['status'] === 'Pending')->count(),
            'requirements_approved' => $reqList->filter(fn ($r) => $r['status'] === 'Approved')->count(),
            'requirements_rejected' => $reqList->filter(fn ($r) => $r['status'] === 'Rejected')->count(),
            'certificates' => [],
        ];

        if ($withRequirements) {
            $data['requirements'] = $reqList->values();

            $data['justification'] = $participant->justification ? [
                'id' => $participant->justification->id,
                'file_url' => '/storage/'.$participant->justification->file_path,
                'uploaded_at' => $participant->justification->updated_at,
            ] : null;

            $data['resource_speakers'] = $program->resourceSpeakers
                ? $program->resourceSpeakers->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'designation' => $s->designation,
                    'affiliation' => $s->affiliation,
                    'topic' => $s->topic,
                    'expertise' => $s->expertise,
                    'date_engaged' => optional($s->date_engaged)->toDateString() ?? $s->date_engaged,
                ])->values()->toArray()
                : [];

            $data['supporting_documents'] = $program->supportingDocuments
                ? $program->supportingDocuments->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'subject' => $d->subject,
                    'document_series' => $d->document_series,
                    'origin' => $d->origin,
                    'document_number' => $d->document_number,
                    'date_issued' => optional($d->date_issued)->toDateString() ?? $d->date_issued,
                    'link' => $d->link,
                ])->values()->toArray()
                : [];
        }

        return $data;
    }
}
