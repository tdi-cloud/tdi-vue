<?php

namespace App\Http\Controllers;

use App\Models\ActionPlanActivity;
use App\Models\Employee;
use App\Models\ImplementationReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin view of every participant's Re-Entry Action Plan activities: where
 * each stands against its schedule, and review of implementation reports.
 * Any admin can review.
 */
class ActionPlanMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->filteredQuery($request);

        $activities = (clone $query)
            ->with(['implementationReport', 'participant.employee', 'batch.program'])
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15) ?: 15)
            ->withQueryString()
            ->through(fn (ActionPlanActivity $activity) => $this->transform($activity));

        return Inertia::render('ActionPlanMonitoring/Index', [
            'activities' => $activities,
            'stats' => $this->stats($this->filteredQuery($request, ignoreStatus: true)),
            'statuses' => ActionPlanActivity::STATUSES,
            'regions' => Employee::query()->whereNotNull('REGION')->distinct()->orderBy('REGION')->pluck('REGION'),
            'filters' => $request->only(['search', 'status', 'region']),
        ]);
    }

    /**
     * PATCH /action-plan-monitoring/reports/{implementationReport}/review
     */
    public function review(Request $request, ImplementationReport $implementationReport): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.ImplementationReport::STATUS_APPROVED.','.ImplementationReport::STATUS_REJECTED],
            'remarks' => ['nullable', 'required_if:status,'.ImplementationReport::STATUS_REJECTED, 'string', 'max:2000'],
        ], [
            'remarks.required_if' => 'Please tell the participant what to revise.',
        ]);

        $implementationReport->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'reviewed_by' => $request->user()->name,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', $validated['status'] === ImplementationReport::STATUS_APPROVED
            ? 'Implementation report approved.'
            : 'Implementation report returned for revision.');
    }

    private function filteredQuery(Request $request, bool $ignoreStatus = false): Builder
    {
        $query = ActionPlanActivity::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();

            $query->where(function (Builder $q) use ($search) {
                $q->where('activity', 'like', "%{$search}%")
                    ->orWhereHas('participant', fn (Builder $p) => $p->where('empcode', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn (Builder $e) => $e->where('FIRSTNAME', 'like', "%{$search}%")
                            ->orWhere('LASTNAME', 'like', "%{$search}%")))
                    ->orWhereHas('batch.program', fn (Builder $p) => $p->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('region')) {
            $query->whereHas('participant.employee', fn (Builder $e) => $e->where('REGION', $request->string('region')));
        }

        if (! $ignoreStatus && $request->filled('status')) {
            $query->withMonitoringStatus($request->string('status')->toString());
        }

        return $query;
    }

    /**
     * Count per status plus the planned and actual budget, for the summary cards.
     *
     * @return array<string, mixed>
     */
    private function stats(Builder $query): array
    {
        $counts = collect(ActionPlanActivity::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (clone $query)->withMonitoringStatus($status)->count()]);

        $completed = $counts[ActionPlanActivity::STATUS_COMPLETED];
        $total = (clone $query)->count();

        return [
            'total' => $total,
            'by_status' => $counts->all(),
            'completion_rate' => $total > 0 ? round($completed / $total * 100) : 0,
            'planned_budget' => (float) (clone $query)->sum('budget'),
            'actual_cost' => (float) ImplementationReport::query()
                ->where('status', ImplementationReport::STATUS_APPROVED)
                ->whereIn('action_plan_activity_id', (clone $query)->select('id'))
                ->sum('actual_cost'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ActionPlanActivity $activity): array
    {
        $employee = $activity->participant?->employee;
        $report = $activity->implementationReport;

        return [
            'id' => $activity->id,
            'employee_name' => $employee?->name ?? $activity->participant?->empcode,
            'empcode' => $activity->participant?->empcode,
            'office' => $employee?->{'OFFICE/DIVISION'} ?: $employee?->OFFICE,
            'region' => $employee?->REGION,
            'program_title' => $activity->batch?->program?->title,
            'batch_label' => $activity->batch?->batch,
            'activity' => $activity->activity,
            'expected_output' => $activity->expected_output,
            'schedule' => $activity->scheduleLabel(),
            'target_date' => $activity->target_date?->toDateString(),
            'budget' => $activity->budget,
            'remarks' => $activity->remarks,
            'status' => $activity->monitoringStatus(),
            'report' => $report ? [
                'id' => $report->id,
                'accomplishment' => $report->accomplishment,
                'actual_output' => $report->actual_output,
                'date_completed' => $report->date_completed?->toDateString(),
                'actual_cost' => $report->actual_cost,
                'file_url' => $report->fileUrl(),
                'status' => $report->status,
                'remarks' => $report->remarks,
                'reviewed_by' => $report->reviewed_by,
                'reviewed_at' => $report->reviewed_at?->toDateString(),
                'submitted_at' => $report->submitted_at?->toDateString(),
            ] : null,
        ];
    }
}
