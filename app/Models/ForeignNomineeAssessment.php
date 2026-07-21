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

    public function getRequirementsTotalAttribute(): int
    {
        return collect(self::REQUIREMENT_CRITERIA)
            ->keys()
            ->sum(fn (string $key) => (int) $this->{$key});
    }
}
