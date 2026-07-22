<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForeignNomineeInterviewRating extends Model
{
    /**
     * Point ceilings per criterion, keyed by column name.
     *
     * @var array<string, int>
     */
    public const CRITERIA = [
        'communication_skills' => 5,
        'alertness' => 5,
        'judgement' => 5,
        'self_confidence' => 5,
        'emotional_stability' => 5,
        'appearance' => 5,
    ];

    protected $fillable = [
        'foreign_nominee_id',
        'nhrdc_empcode',
        'nhrdc_name',
        'nhrdc_position',
        'communication_skills',
        'alertness',
        'judgement',
        'self_confidence',
        'emotional_stability',
        'appearance',
        'rated_by',
        'rated_at',
    ];

    protected $appends = [
        'total',
    ];

    protected function casts(): array
    {
        return [
            'communication_skills' => 'decimal:2',
            'alertness' => 'decimal:2',
            'judgement' => 'decimal:2',
            'self_confidence' => 'decimal:2',
            'emotional_stability' => 'decimal:2',
            'appearance' => 'decimal:2',
            'rated_at' => 'datetime',
        ];
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(ForeignNominee::class, 'foreign_nominee_id');
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function getTotalAttribute(): float
    {
        return round(collect(self::CRITERIA)
            ->keys()
            ->sum(fn (string $key) => (float) $this->{$key}), 2);
    }
}
