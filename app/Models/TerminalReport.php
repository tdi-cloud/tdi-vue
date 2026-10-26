<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant's filled-out Terminal and Re-Entry Action Plan Report Form
 * (TESDA-OP-AS-01-F07). TREAP reports only carry the Program Description;
 * REAP reports also carry the Re-Entry Action Plan.
 */
class TerminalReport extends Model
{
    /** Requirement titles that are completed through this form. */
    public const REPORT_TYPES = ['TREAP', 'REAP'];

    protected $fillable = [
        'participant_id',
        'batch_id',
        'requirement_id',
        'report_type',
        'program_description',
        'competencies_gained',
        'reap_summary',
        'reap_objectives',
        'action_plan',
        'prepared_by',
        'approved_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'program_description' => 'array',
            'action_plan' => 'array',
            'prepared_by' => 'array',
            'approved_by' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public static function supportsRequirement(Requirement $requirement): bool
    {
        return in_array($requirement->title, self::REPORT_TYPES, true);
    }

    public function includesReentryPlan(): bool
    {
        return $this->report_type === 'REAP';
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }
}
