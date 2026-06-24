<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForeignNomineeSubmission extends Model
{
    protected $fillable = [
        'foreign_nominee_id',
        'foreign_nominee_requirement_id',
        'file_path',
    ];

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(ForeignNominee::class, 'foreign_nominee_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ForeignNomineeRequirement::class, 'foreign_nominee_requirement_id');
    }
}