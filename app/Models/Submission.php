<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'participant_id',
        'program_code',
        'batch_id',
        'requirement_id',
        'status',
        'file_path',
        'notes',
        'remarks',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class, 'participant_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_code', 'program_code');
    }
}