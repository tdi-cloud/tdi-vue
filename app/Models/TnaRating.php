<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TnaRating extends Model
{
    protected $fillable = [
        'tna_assessment_id',
        'competency_id',
        'criticality',
        'competence',
        'frequency',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(TnaAssessment::class, 'tna_assessment_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}