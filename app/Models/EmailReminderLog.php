<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReminderLog extends Model
{
    /** A requirement reminder sent from the Incomplete Submissions modal. */
    public const TYPE_REMINDER = 'reminder';

    /** A free-form announcement sent from the Notify Participants modal. */
    public const TYPE_NOTIFICATION = 'notification';

    /** Certificate PDFs emailed to participants from the Certificates tab. */
    public const TYPE_CERTIFICATE = 'certificate';

    protected $fillable = [
        'type',
        'sent_by',
        'sent_by_name',
        'program_id',
        'batch_id',
        'requirement_id',
        'subject',
        'recipients',
        'recipients_count',
    ];

    /**
     * Mirrors the column default so new models know their type before they are saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::TYPE_REMINDER,
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

    /**
     * Only requirement reminders.
     *
     * @param  Builder<EmailReminderLog>  $query
     */
    public function scopeReminders(Builder $query): void
    {
        $query->where('type', self::TYPE_REMINDER);
    }

    /**
     * Only participant notifications.
     *
     * @param  Builder<EmailReminderLog>  $query
     */
    public function scopeNotifications(Builder $query): void
    {
        $query->where('type', self::TYPE_NOTIFICATION);
    }

    /**
     * Only certificate emails.
     *
     * @param  Builder<EmailReminderLog>  $query
     */
    public function scopeCertificates(Builder $query): void
    {
        $query->where('type', self::TYPE_CERTIFICATE);
    }
}
