<?php

namespace App\Http\Controllers;

use App\Models\ActionPlanActivity;
use App\Models\Batch;
use App\Models\Participant;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\TerminalReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a participant fill out the Terminal and Re-Entry Action Plan Report
 * Form (TESDA-OP-AS-01-F07) for a TREAP or REAP requirement. Saving the form
 * generates an unsigned PDF to print and sign; the requirement is only
 * submitted once the participant uploads the signed copy.
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
                'action_plan' => $report->activities()->with('implementationReport')->get()->map(fn (ActionPlanActivity $activity) => [
                    'id' => $activity->id,
                    'activity' => $activity->activity,
                    'expected_output' => $activity->expected_output,
                    'start_date' => $activity->start_date?->toDateString(),
                    'target_date' => $activity->target_date?->toDateString(),
                    'budget' => $activity->budget,
                    'remarks' => $activity->remarks,
                    // Activities with an implementation report under review or approved can't be changed here.
                    'locked' => $activity->isLocked(),
                ])->values(),
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

        $existingReport = $this->existingReport($participant, $requirement);

        if (! $includesReentryPlan && $existingReport?->activities()->get()->contains(fn (ActionPlanActivity $activity) => $activity->isLocked())) {
            return back()->withErrors([
                'include_reentry_plan' => 'Part II can no longer be skipped because some of your activities already have implementation reports.',
            ]);
        }

        $report = DB::transaction(function () use ($participant, $requirement, $batch, $validated, $includesReentryPlan) {
            $report = TerminalReport::updateOrCreate(
                ['participant_id' => $participant->id, 'requirement_id' => $requirement->id],
                [
                    'batch_id' => $batch->id,
                    'report_type' => $requirement->title,
                    'program_description' => $validated['program_description'],
                    'competencies_gained' => $includesReentryPlan ? $validated['competencies_gained'] : null,
                    'reap_summary' => $includesReentryPlan ? $validated['reap_summary'] : null,
                    'reap_objectives' => $includesReentryPlan ? $validated['reap_objectives'] : null,
                    'prepared_by' => $validated['prepared_by'],
                    'approved_by' => $validated['approved_by'],
                    'generated_at' => now(),
                ]
            );

            $this->syncActivities($report, $includesReentryPlan ? array_values($validated['action_plan']) : []);

            return $report;
        });

        // The generated PDF is an unsigned draft. It only becomes the requirement
        // submission once the participant uploads the signed copy.
        $this->storeGeneratedPdf($report, $participant);

        return redirect()
            ->route('programs.terminal-report.generated', [$batch, $requirement])
            ->with('success', "Your {$requirement->title} report was generated. Print it, have it signed, then upload the signed copy to submit it.");
    }

    /**
     * GET .../terminal-report/generated
     *
     * Shown right after generating: the unsigned PDF to print and sign, and
     * the place to upload the signed copy, which is what gets submitted.
     */
    public function generated(Request $request, Batch $batch, Requirement $requirement): Response|RedirectResponse
    {
        $participant = $this->ownParticipant($request, $batch, $requirement, forEditing: false);
        $report = $this->existingReport($participant, $requirement);

        if (! $report?->generated_pdf_path) {
            return redirect()->route('programs.terminal-report.edit', [$batch, $requirement]);
        }

        $signedCopy = $this->existingSubmission($participant, $requirement);

        return Inertia::render('MyPrograms/TerminalReportGenerated', [
            'batchId' => $batch->id,
            'requirement' => ['id' => $requirement->id, 'title' => $requirement->title, 'name' => $requirement->name],
            'program' => ['title' => $batch->program?->title, 'batch_label' => $batch->batch],
            'pdfUrl' => '/storage/'.$report->generated_pdf_path,
            'pdfName' => basename($report->generated_pdf_path),
            'generatedAt' => $report->generated_at?->toIso8601String(),
            'signedCopy' => $signedCopy?->file_path ? [
                'status' => $signedCopy->status,
                'remarks' => $signedCopy->remarks,
                'file_url' => '/storage/'.$signedCopy->file_path,
                'submitted_at' => $signedCopy->submitted_at?->toIso8601String(),
                // Uploaded before the latest regeneration, so it may not match the current form.
                'older_than_draft' => $report->generated_at && $signedCopy->submitted_at?->lt($report->generated_at),
            ] : null,
            'activitiesCount' => $report->activities()->count(),
            'canEdit' => $signedCopy?->status !== 'Approved' && $participant->attendance !== 'Absent',
        ]);
    }

    /**
     * Save the submitted action plan rows as activity records: update the ones
     * that already exist, add new ones, and remove rows the participant deleted.
     * Activities with an implementation report under review or approved are
     * left untouched.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncActivities(TerminalReport $report, array $rows): void
    {
        $existing = $report->activities()->with('implementationReport')->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $activity = isset($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($activity?->isLocked()) {
                $keptIds[] = $activity->id;

                continue;
            }

            $attributes = [
                'sort_order' => $index,
                'activity' => $row['activity'],
                'expected_output' => $row['expected_output'],
                'start_date' => $row['start_date'],
                'target_date' => $row['target_date'],
                'budget' => $row['budget'] ?? null,
                'remarks' => $row['remarks'] ?? null,
            ];

            if ($activity) {
                $activity->update($attributes);
                $keptIds[] = $activity->id;
            } else {
                $keptIds[] = $report->activities()->create($attributes + [
                    'participant_id' => $report->participant_id,
                    'batch_id' => $report->batch_id,
                ])->id;
            }
        }

        $existing
            ->reject(fn (ActionPlanActivity $activity) => in_array($activity->id, $keptIds, true) || $activity->isLocked())
            ->each(function (ActionPlanActivity $activity) {
                $activity->implementationReport?->delete();
                $activity->delete();
            });
    }

    /**
     * The participant record of the signed-in user, after checking that the
     * requirement belongs to this batch and uses this form — and, when
     * $forEditing, that the report can still be edited.
     */
    private function ownParticipant(Request $request, Batch $batch, Requirement $requirement, bool $forEditing = true): Participant
    {
        abort_unless($requirement->batch_id === $batch->id, 404);
        abort_unless(TerminalReport::supportsRequirement($requirement), 404);

        $user = $request->user();
        abort_if(empty($user->empcode), 403);

        $participant = Participant::with('employee')
            ->where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        if (! $forEditing) {
            return $participant;
        }

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
                'action_plan.*.id' => ['nullable', 'integer'],
            ];

            foreach (ActionPlanActivity::fieldRules() as $field => $fieldRules) {
                $rules["action_plan.*.{$field}"] = $fieldRules;
            }

            // after_or_equal needs the sibling field's full path inside the array.
            $rules['action_plan.*.target_date'][] = 'after_or_equal:action_plan.*.start_date';
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
            'action_plan.*.start_date' => 'start date',
            'action_plan.*.target_date' => 'target date',
            'action_plan.*.budget' => 'budget',
        ];
    }

    /**
     * Render the form as an unsigned PDF and keep it on the report, replacing
     * the previous draft. It is not a submission: the participant prints it,
     * has it signed, and uploads the signed copy as the requirement.
     */
    private function storeGeneratedPdf(TerminalReport $report, Participant $participant): void
    {
        $pdf = Pdf::loadView('pdf.terminal-report', ['report' => $report])->setPaper('a4', 'portrait');

        $filename = now()->timestamp.'_'.$report->report_type.'_'.Str::slug($report->prepared_by['name'] ?? $participant->empcode, '_').'_unsigned.pdf';
        $path = 'terminal-reports/'.$filename;
        Storage::disk('public')->put($path, $pdf->output());

        if ($report->generated_pdf_path && $report->generated_pdf_path !== $path) {
            Storage::disk('public')->delete($report->generated_pdf_path);
        }

        $report->update(['generated_pdf_path' => $path]);
    }
}
