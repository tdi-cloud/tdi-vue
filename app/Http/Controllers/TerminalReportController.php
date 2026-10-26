<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Participant;
use App\Models\PendingNotification;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\TerminalReport;
use App\Notifications\RequirementSubmitted;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a participant fill out the Terminal and Re-Entry Action Plan Report
 * Form (TESDA-OP-AS-01-F07) for a TREAP or REAP requirement. Saving the form
 * generates the PDF and submits it as the requirement's file.
 */
class TerminalReportController extends Controller
{
    private const INITIATED_LABELS = [
        'TDI' => 'TESDA Development Institute (TDI)',
        'NTTA' => 'National TVET Trainors Academy (NTTA)',
    ];

    public function edit(Request $request, Batch $batch, Requirement $requirement): Response
    {
        $participant = $this->ownParticipant($request, $batch, $requirement);
        $report = $this->existingReport($participant, $requirement);
        $submission = $this->existingSubmission($participant, $requirement);

        return Inertia::render('MyPrograms/TerminalReport', [
            'batchId' => $batch->id,
            'requirement' => [
                'id' => $requirement->id,
                'title' => $requirement->title,
                'name' => $requirement->name,
                'due_date' => optional($requirement->due_date)->toDateString(),
            ],
            'program' => [
                'title' => $batch->program?->title,
                'batch_label' => $batch->batch,
            ],
            // REAP must include Part II; for TREAP it is the participant's choice.
            'reentryPlanRequired' => $requirement->title === 'REAP',
            'autoFilled' => $this->programDescriptionDefaults($batch),
            'report' => $report ? [
                'program_description' => $report->program_description,
                'competencies_gained' => $report->competencies_gained,
                'reap_summary' => $report->reap_summary,
                'reap_objectives' => $report->reap_objectives,
                'action_plan' => $report->action_plan,
                'has_reentry_plan' => $report->hasReentryPlan(),
                'prepared_by' => $report->prepared_by,
                'approved_by' => $report->approved_by,
                'generated_at' => $report->generated_at?->toIso8601String(),
            ] : null,
            'defaultPreparedBy' => $this->preparedByDefaults($participant),
            'submission' => $submission ? [
                'status' => $submission->status,
                'remarks' => $submission->remarks,
                'file_url' => $submission->file_path ? '/storage/'.$submission->file_path : null,
            ] : null,
        ]);
    }

    public function update(Request $request, Batch $batch, Requirement $requirement): RedirectResponse
    {
        $participant = $this->ownParticipant($request, $batch, $requirement);
        // REAP always includes Part II. For TREAP the participant may skip it,
        // in which case the PDF shows Part II filled with "N/A".
        $includesReentryPlan = $requirement->title === 'REAP' || $request->boolean('include_reentry_plan');

        $validated = $request->validate($this->rules($includesReentryPlan), [], $this->attributeNames());

        $report = TerminalReport::updateOrCreate(
            ['participant_id' => $participant->id, 'requirement_id' => $requirement->id],
            [
                'batch_id' => $batch->id,
                'report_type' => $requirement->title,
                'program_description' => $validated['program_description'],
                'competencies_gained' => $includesReentryPlan ? $validated['competencies_gained'] : null,
                'reap_summary' => $includesReentryPlan ? $validated['reap_summary'] : null,
                'reap_objectives' => $includesReentryPlan ? $validated['reap_objectives'] : null,
                'action_plan' => $includesReentryPlan ? array_values($validated['action_plan']) : null,
                'prepared_by' => $validated['prepared_by'],
                'approved_by' => $validated['approved_by'],
                'generated_at' => now(),
            ]
        );

        $submission = $this->submitGeneratedPdf($request, $batch, $requirement, $participant, $report);

        if ($batch->program?->added_by && $batch->program->added_by !== $participant->empcode) {
            PendingNotification::notifyOrQueue($batch->program->added_by, new RequirementSubmitted($submission));
        }

        return redirect()
            ->route('programs.my-progress', $batch)
            ->with('success', "Your {$requirement->title} report was generated and submitted. Awaiting review.");
    }

    /**
     * The participant record of the signed-in user, after checking that the
     * requirement belongs to this batch, uses this form, and can still be edited.
     */
    private function ownParticipant(Request $request, Batch $batch, Requirement $requirement): Participant
    {
        abort_unless($requirement->batch_id === $batch->id, 404);
        abort_unless(TerminalReport::supportsRequirement($requirement), 404);

        $user = $request->user();
        abort_if(empty($user->empcode), 403);

        $participant = Participant::with('employee')
            ->where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        abort_if($participant->attendance === 'Absent', 403, 'You were marked Absent for this program, so this report is no longer required.');
        abort_if(
            $this->existingSubmission($participant, $requirement)?->status === 'Approved',
            403,
            'This report is already approved and can no longer be edited.'
        );

        return $participant;
    }

    private function existingReport(Participant $participant, Requirement $requirement): ?TerminalReport
    {
        return TerminalReport::where('participant_id', $participant->id)
            ->where('requirement_id', $requirement->id)
            ->first();
    }

    private function existingSubmission(Participant $participant, Requirement $requirement): ?Submission
    {
        return Submission::where('participant_id', $participant->id)
            ->where('requirement_id', $requirement->id)
            ->first();
    }

    /**
     * Section I values taken from the program and batch records.
     *
     * @return array<string, string|null>
     */
    private function programDescriptionDefaults(Batch $batch): array
    {
        $program = $batch->program;
        $program?->loadMissing(['competencies', 'resourceSpeakers']);

        $speakers = $program?->resourceSpeakers
            ->filter(fn ($speaker) => $speaker->batch_id === null || $speaker->batch_id === $batch->id)
            ->map(fn ($speaker) => collect([$speaker->name, $speaker->designation, $speaker->affiliation])->filter()->implode(', '))
            ->unique()
            ->implode("\n");

        $courseOutline = $program?->competencies->isNotEmpty()
            ? $program->competencies->pluck('competency')->filter()->unique()->implode("\n")
            : $program?->competency;

        return [
            'title' => $program?->title,
            'date_duration' => $this->formatDateRange($batch->date_start, $batch->date_end),
            'hours' => is_numeric($batch->hours) ? (string) ($batch->hours + 0) : $batch->hours,
            'venue' => $batch->venue ?: $batch->modality,
            'conducted_by' => $program?->provider ?: (self::INITIATED_LABELS[$program?->initiated] ?? $program?->initiated),
            'sponsoring_institution' => 'Technical Education and Skills Development Authority (TESDA)',
            'designed_for' => null,
            'objectives' => $program?->description,
            'methodology' => $batch->modality ?: $program?->modality,
            'course_outline' => $courseOutline ?: null,
            'resource_speakers' => $speakers ?: null,
        ];
    }

    /**
     * @return array{empcode: string, name: string, position: ?string, office: ?string, date: string}
     */
    private function preparedByDefaults(Participant $participant): array
    {
        $employee = $participant->employee;

        return [
            'empcode' => $participant->empcode,
            'name' => $employee?->name ?? $participant->empcode,
            'position' => $employee?->POSITION,
            'office' => $employee?->{'OFFICE/DIVISION'} ?: $employee?->OFFICE,
            'date' => now()->toDateString(),
        ];
    }

    private function formatDateRange(?string $start, ?string $end): ?string
    {
        if (! $start) {
            return null;
        }

        $startDate = Carbon::parse($start);
        $endDate = $end ? Carbon::parse($end) : $startDate;

        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('F j, Y');
        }

        if ($startDate->isSameMonth($endDate)) {
            return $startDate->format('F j').'–'.$endDate->format('j, Y');
        }

        if ($startDate->isSameYear($endDate)) {
            return $startDate->format('F j').' – '.$endDate->format('F j, Y');
        }

        return $startDate->format('F j, Y').' – '.$endDate->format('F j, Y');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $includesReentryPlan): array
    {
        $rules = [
            'include_reentry_plan' => ['sometimes', 'boolean'],

            'program_description' => ['required', 'array'],
            'program_description.title' => ['required', 'string', 'max:500'],
            'program_description.date_duration' => ['required', 'string', 'max:255'],
            'program_description.hours' => ['nullable', 'string', 'max:50'],
            'program_description.venue' => ['nullable', 'string', 'max:500'],
            'program_description.conducted_by' => ['nullable', 'string', 'max:500'],
            'program_description.sponsoring_institution' => ['nullable', 'string', 'max:500'],
            'program_description.designed_for' => ['required', 'string', 'max:1000'],
            'program_description.objectives' => ['nullable', 'string', 'max:5000'],
            'program_description.methodology' => ['nullable', 'string', 'max:2000'],
            'program_description.course_outline' => ['nullable', 'string', 'max:5000'],
            'program_description.resource_speakers' => ['nullable', 'string', 'max:5000'],

            'prepared_by' => ['required', 'array'],
            'prepared_by.empcode' => ['nullable', 'string', 'max:50'],
            'prepared_by.name' => ['required', 'string', 'max:255'],
            'prepared_by.position' => ['required', 'string', 'max:255'],
            'prepared_by.office' => ['nullable', 'string', 'max:255'],
            'prepared_by.date' => ['required', 'date'],

            'approved_by' => ['required', 'array'],
            'approved_by.empcode' => ['nullable', 'string', 'max:50'],
            'approved_by.name' => ['required', 'string', 'max:255'],
            'approved_by.position' => ['required', 'string', 'max:255'],
            'approved_by.office' => ['nullable', 'string', 'max:255'],
            'approved_by.date' => ['nullable', 'date'],
        ];

        if ($includesReentryPlan) {
            $rules += [
                'competencies_gained' => ['required', 'string', 'max:5000'],
                'reap_summary' => ['required', 'string', 'max:5000'],
                'reap_objectives' => ['required', 'string', 'max:5000'],
                'action_plan' => ['required', 'array', 'min:1', 'max:20'],
                'action_plan.*.activity' => ['required', 'string', 'max:1000'],
                'action_plan.*.expected_output' => ['required', 'string', 'max:1000'],
                'action_plan.*.schedule' => ['required', 'string', 'max:255'],
                'action_plan.*.budget' => ['nullable', 'string', 'max:255'],
                'action_plan.*.remarks' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'program_description.title' => 'title',
            'program_description.date_duration' => 'date/duration',
            'program_description.designed_for' => '"designed for"',
            'prepared_by.name' => 'name',
            'prepared_by.position' => 'position',
            'prepared_by.date' => 'date',
            'approved_by.name' => 'approver',
            'approved_by.position' => 'approver position',
            'competencies_gained' => 'competencies achieved / learning gained',
            'reap_summary' => 'summary of the REAP',
            'reap_objectives' => 'objectives',
            'action_plan' => 'action plan',
            'action_plan.*.activity' => 'activity',
            'action_plan.*.expected_output' => 'expected output',
            'action_plan.*.schedule' => 'schedule of implementation',
        ];
    }

    /**
     * Render the form as a PDF and store it as the requirement's submission,
     * replacing any earlier file and sending it back for review.
     */
    private function submitGeneratedPdf(Request $request, Batch $batch, Requirement $requirement, Participant $participant, TerminalReport $report): Submission
    {
        $pdf = Pdf::loadView('pdf.terminal-report', ['report' => $report])->setPaper('a4', 'portrait');

        $filename = now()->timestamp.'_'.$requirement->title.'_'.Str::slug($report->prepared_by['name'] ?? $participant->empcode, '_').'.pdf';
        $path = 'submissions/'.$filename;
        Storage::disk('public')->put($path, $pdf->output());

        $existing = $this->existingSubmission($participant, $requirement);

        if ($existing?->file_path && $existing->file_path !== $path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        return Submission::updateOrCreate(
            ['participant_id' => $participant->id, 'requirement_id' => $requirement->id],
            [
                'program_code' => $batch->program_code,
                'batch_id' => $batch->id,
                'status' => 'Pending',
                'file_path' => $path,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'added_by' => $existing?->added_by ?? $request->user()?->empcode,
            ]
        );
    }
}
