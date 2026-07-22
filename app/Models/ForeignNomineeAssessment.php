<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForeignNomineeAssessment extends Model
{
    /**
     * Point ceilings per criterion, keyed by column name.
     *
     * @var array<string, int>
     */
    public const REQUIREMENT_CRITERIA = [
        'need_for_training' => 20,
        'relevance_to_duties' => 30,
        'meets_donor_requirements' => 10,
        'completion_of_documents' => 10,
    ];

    /**
     * Fixed-choice rubric per criterion — the assessor picks one option, not
     * an arbitrary number, so only these exact point values are valid.
     *
     * @var array<string, array<int, string>>
     */
    public const REQUIREMENT_OPTIONS = [
        'need_for_training' => [
            20 => 'Less than 10 hours of relevant training',
            17 => 'With 10 to 20 hours of relevant training',
            15 => 'With 21 to 30 hours relevant training',
            10 => 'With 31 to 40 hours of relevant training',
        ],
        'relevance_to_duties' => [
            30 => 'Relevant to present work assignment',
            28 => 'Relevant to other work assignment',
            20 => 'Not relevant to work assignment',
        ],
        'meets_donor_requirements' => [
            10 => 'Meets all requirements',
            8 => 'Lacks 1 requirement',
            6 => 'Lacks 2 requirements',
            4 => 'Lacks 3 or more requirements',
        ],
        'completion_of_documents' => [
            10 => 'Submits complete requirements',
            8 => 'Lacks 1 requirement',
            6 => 'Lacks 2 requirements',
            4 => 'Lacks 3 or more requirements',
        ],
    ];

    protected $fillable = [
        'foreign_nominee_id',
        'need_for_training',
        'relevance_to_duties',
        'meets_donor_requirements',
        'completion_of_documents',
        'assessed_by',
        'assessed_at',
    ];

    protected $appends = [
        'requirements_total',
    ];

    protected function casts(): array
    {
        return [
            'need_for_training' => 'decimal:2',
            'relevance_to_duties' => 'decimal:2',
            'meets_donor_requirements' => 'decimal:2',
            'completion_of_documents' => 'decimal:2',
            'assessed_at' => 'datetime',
        ];
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(ForeignNominee::class, 'foreign_nominee_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function getRequirementsTotalAttribute(): float
    {
        return round(collect(self::REQUIREMENT_CRITERIA)
            ->keys()
            ->sum(fn (string $key) => (float) $this->{$key}), 2);
    }

    /**
     * Requirements start at full marks (nothing to deduct yet), so any
     * nominee without an assessment record gets one created immediately —
     * regardless of which page (admin or NHRDC self-service) is visited
     * first. The admin can still deduct points and re-save afterward.
     *
     * @param  array<int>  $nomineeIds
     */
    public static function ensureDefaultsFor(array $nomineeIds): void
    {
        $existingIds = self::whereIn('foreign_nominee_id', $nomineeIds)
            ->pluck('foreign_nominee_id')
            ->all();

        foreach (array_diff($nomineeIds, $existingIds) as $nomineeId) {
            self::create(array_merge(
                ['foreign_nominee_id' => $nomineeId],
                self::REQUIREMENT_CRITERIA,
            ));
        }
    }
}
