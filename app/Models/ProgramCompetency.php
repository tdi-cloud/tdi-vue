<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramCompetency extends Model
{
    /**
     * Singular ang table name sa migration ('program_competency'),
     * kaya kailangan i-specify — ang default kasi ay 'program_competencies'.
     */
    protected $table = 'program_competency';

    protected $fillable = [
        'program_id',
        'domain',
        'competency',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}