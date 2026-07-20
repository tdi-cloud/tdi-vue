<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Batch extends Model
{
    protected $fillable = [
        'sort_order',
        'program_code',
        'batch',
        'status',
        'modality',
        'venue',
        'date_start',
        'date_end',
        'time_start',
        'time_end',
        'days',
        'hours',
        'added_by',
    ];

    /**
     * Batch belongs to a Program (via program_code).
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_code', 'program_code');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'batch_id');
    }

    public function requirements()
    {
        return $this->hasMany(Requirement::class, 'batch_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Batch $batch) {
            $paths = AbsentJustification::whereIn(
                'participant_id',
                $batch->participants()->pluck('id')
            )->pluck('file_path');

            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }
}
