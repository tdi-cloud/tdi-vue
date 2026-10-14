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
        'document_title',
        'status',
        'file_path',
        'notes',
        'remarks',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'added_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Keep a copy of the requirement title so the submission is still labeled
     * after its requirement is deleted (requirement_id is then set to null).
     */
    protected static function booted(): void
    {
        static::saving(function (Submission $submission) {
            if ($submission->requirement_id && ($submission->isDirty('requirement_id') || ! $submission->document_title)) {
                $submission->document_title = Requirement::whereKey($submission->requirement_id)->value('title')
                    ?? $submission->document_title;
            }
        });
    }

    /**
     * Label for display: the live requirement title, or the stored copy.
     */
    public function displayTitle(): string
    {
        return $this->requirement?->title ?? $this->document_title ?? 'Other Document';
    }

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
