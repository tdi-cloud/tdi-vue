<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A participant's report on carrying out one action plan activity, reviewed
 * by any admin.
 */
class ImplementationReport extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'action_plan_activity_id',
        'participant_id',
        'accomplishment',
        'actual_output',
        'date_completed',
        'actual_cost',
        'file_path',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_completed' => 'date',
            'actual_cost' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ImplementationReport $report) {
            Storage::disk('public')->delete($report->file_path);
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActionPlanActivity::class, 'action_plan_activity_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function fileUrl(): string
    {
        return '/storage/'.$this->file_path;
    }
}
