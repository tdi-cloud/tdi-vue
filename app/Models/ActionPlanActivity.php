<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One activity from a participant's Re-Entry Action Plan, monitored against
 * its schedule of implementation until an implementation report is approved.
 */
class ActionPlanActivity extends Model
{
    public const STATUS_UPCOMING = 'Upcoming';

    public const STATUS_ONGOING = 'Ongoing';

    public const STATUS_OVERDUE = 'Overdue';

    public const STATUS_FOR_REVIEW = 'For Review';

    public const STATUS_NEEDS_REVISION = 'Needs Revision';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUSES = [
        self::STATUS_UPCOMING,
        self::STATUS_ONGOING,
        self::STATUS_OVERDUE,
        self::STATUS_FOR_REVIEW,
        self::STATUS_NEEDS_REVISION,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'terminal_report_id',
        'participant_id',
        'batch_id',
        'sort_order',
        'activity',
        'expected_output',
        'start_date',
        'target_date',
        'budget',
        'remarks',
        'added_after_submission',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
            'budget' => 'decimal:2',
            'added_after_submission' => 'boolean',
        ];
    }

    /**
     * Validation rules for one activity's fields. The target date must also be
     * on or after the start date; callers add that rule with the right path.
     *
     * @return array<string, array<int, string>>
     */
    public static function fieldRules(): array
    {
        return [
            'activity' => ['required', 'string', 'max:1000'],
            'expected_output' => ['required', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'target_date' => ['required', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * "March 1 – April 30, 2026" style schedule for display and the PDF.
     */
    public function scheduleLabel(): string
    {
        $start = $this->start_date;
        $end = $this->target_date;

        if (! $start && ! $end) {
            return '';
        }

        if (! $start || ! $end || $start->isSameDay($end)) {
            return ($start ?? $end)->format('F j, Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j, Y').' – '.$end->format('F j, Y');
    }

    public function terminalReport(): BelongsTo
    {
        return $this->belongsTo(TerminalReport::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function implementationReport(): HasOne
    {
        return $this->hasOne(ImplementationReport::class);
    }

    /**
     * Where the activity stands: driven by its implementation report once one
     * is submitted, otherwise by today's date against its schedule.
     */
    public function monitoringStatus(): string
    {
        $reportStatus = $this->implementationReport?->status;

        return match (true) {
            $reportStatus === ImplementationReport::STATUS_APPROVED => self::STATUS_COMPLETED,
            $reportStatus === ImplementationReport::STATUS_PENDING => self::STATUS_FOR_REVIEW,
            $reportStatus === ImplementationReport::STATUS_REJECTED => self::STATUS_NEEDS_REVISION,
            $this->target_date !== null && $this->target_date->lt(today()) => self::STATUS_OVERDUE,
            $this->start_date !== null && $this->start_date->lte(today()) => self::STATUS_ONGOING,
            default => self::STATUS_UPCOMING,
        };
    }

    /**
     * Once an implementation report is under review or approved, the activity
     * itself can no longer be edited or removed.
     */
    public function isLocked(): bool
    {
        $reportStatus = $this->implementationReport?->status;

        return $reportStatus !== null && $reportStatus !== ImplementationReport::STATUS_REJECTED;
    }

    /**
     * Filter activities by monitoring status, matching monitoringStatus().
     */
    public function scopeWithMonitoringStatus(Builder $query, string $status): Builder
    {
        $today = today()->toDateString();
        $withReport = fn (string $reportStatus) => $query->whereHas(
            'implementationReport',
            fn (Builder $report) => $report->where('status', $reportStatus)
        );

        return match ($status) {
            self::STATUS_COMPLETED => $withReport(ImplementationReport::STATUS_APPROVED),
            self::STATUS_FOR_REVIEW => $withReport(ImplementationReport::STATUS_PENDING),
            self::STATUS_NEEDS_REVISION => $withReport(ImplementationReport::STATUS_REJECTED),
            self::STATUS_OVERDUE => $query->whereDoesntHave('implementationReport')
                ->whereDate('target_date', '<', $today),
            self::STATUS_ONGOING => $query->whereDoesntHave('implementationReport')
                ->whereDate('start_date', '<=', $today)
                ->where(fn (Builder $q) => $q->whereNull('target_date')->orWhereDate('target_date', '>=', $today)),
            self::STATUS_UPCOMING => $query->whereDoesntHave('implementationReport')
                ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '>', $today))
                ->where(fn (Builder $q) => $q->whereNull('target_date')->orWhereDate('target_date', '>=', $today)),
            default => $query,
        };
    }
}
