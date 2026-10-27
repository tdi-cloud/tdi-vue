<?php

namespace App\Http\Controllers;

use App\Models\ActionPlanActivity;
use App\Models\Batch;
use App\Models\ImplementationReport;
use App\Models\Participant;
use App\Models\TerminalReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Participant side of action plan monitoring: adding activities to a
 * submitted plan and handing in an implementation report per activity.
 */
class ActionPlanController extends Controller
{
    /**
     * POST /my-programs/{batch}/action-plan/activities
     */
    public function storeActivity(Request $request, Batch $batch): RedirectResponse
    {
        $participant = $this->ownParticipant($request, $batch);

        $report = TerminalReport::where('participant_id', $participant->id)
            ->where('batch_id', $batch->id)
            ->whereNotNull('reap_summary')
            ->latest('id')
            ->first();

        abort_unless($report, 403, 'Submit your Re-Entry Action Plan first before adding activities.');

        $rules = ActionPlanActivity::fieldRules();
        $rules['target_date'][] = 'after_or_equal:start_date';
        $validated = $request->validate($rules, [], ['start_date' => 'start date', 'target_date' => 'target date']);

        $report->activities()->create($validated + [
            'participant_id' => $participant->id,
            'batch_id' => $batch->id,
            'sort_order' => (int) $report->activities()->max('sort_order') + 1,
            'added_after_submission' => true,
        ]);

        return back()->with('success', 'Activity added to your action plan. Submit its implementation report on or before the target date.');
    }

    /**
     * DELETE /my-programs/{batch}/action-plan/activities/{activity}
     *
     * Only activities added later from the tracker can be removed here; the
     * ones in the submitted form are changed through the form itself.
     */
    public function destroyActivity(Request $request, Batch $batch, ActionPlanActivity $activity): RedirectResponse
    {
        $participant = $this->ownParticipant($request, $batch);
        $this->ensureOwnActivity($participant, $batch, $activity);

        abort_unless($activity->added_after_submission, 403, 'This activity is part of your submitted report. Update it through the report form.');
        abort_if($activity->isLocked(), 403, 'This activity already has an implementation report and can no longer be removed.');

        $activity->implementationReport?->delete();
        $activity->delete();

        return back()->with('success', 'Activity removed from your action plan.');
    }

    /**
     * POST /my-programs/{batch}/action-plan/activities/{activity}/implementation-report
     *
     * Submits (or replaces) the implementation report of an activity. A report
     * that was already approved can no longer be replaced.
     */
    public function submitReport(Request $request, Batch $batch, ActionPlanActivity $activity): RedirectResponse
    {
        $participant = $this->ownParticipant($request, $batch);
        $this->ensureOwnActivity($participant, $batch, $activity);

        $existing = $activity->implementationReport;
        abort_if($existing?->status === ImplementationReport::STATUS_APPROVED, 403, 'This implementation report is already approved.');

        $validated = $request->validate([
            'accomplishment' => ['required', 'string', 'max:5000'],
            'actual_output' => ['required', 'string', 'max:2000'],
            'date_completed' => ['required', 'date', 'before_or_equal:today'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'file' => [$existing ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [
            'date_completed.before_or_equal' => 'The date completed cannot be in the future.',
            'file.required' => 'Please attach proof of implementation (PDF or photo).',
        ], [
            'actual_output' => 'actual output',
            'date_completed' => 'date completed',
            'actual_cost' => 'actual cost',
        ]);

        $path = $existing?->file_path;

        if ($request->hasFile('file')) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            $file = $request->file('file');
            $filename = now()->timestamp.'_'.preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($file->getClientOriginalName()));
            $path = $file->storeAs('implementation-reports', $filename, 'public');
        }

        ImplementationReport::updateOrCreate(
            ['action_plan_activity_id' => $activity->id],
            [
                'participant_id' => $participant->id,
                'accomplishment' => $validated['accomplishment'],
                'actual_output' => $validated['actual_output'],
                'date_completed' => $validated['date_completed'],
                'actual_cost' => $validated['actual_cost'] ?? null,
                'file_path' => $path,
                'status' => ImplementationReport::STATUS_PENDING,
                'remarks' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Implementation report submitted. Awaiting review.');
    }

    private function ownParticipant(Request $request, Batch $batch): Participant
    {
        $user = $request->user();
        abort_if(empty($user->empcode), 403);

        return Participant::where('empcode', $user->empcode)
            ->where('batch_id', $batch->id)
            ->firstOrFail();
    }

    private function ensureOwnActivity(Participant $participant, Batch $batch, ActionPlanActivity $activity): void
    {
        abort_unless($activity->participant_id === $participant->id && $activity->batch_id === $batch->id, 404);
    }
}
