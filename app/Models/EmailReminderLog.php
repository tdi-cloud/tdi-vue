<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReminderLog extends Model
{
    protected $fillable = [
        'sent_by',
        'sent_by_name',
        'program_id',
        'batch_id',
        'requirement_id',
        'subject',
        'recipients',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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
