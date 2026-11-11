<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function evaluationForm()
    {
        return $this->hasOne(EvaluationForm::class);
    }

    public function registrationForm()
    {
        return $this->hasOne(RegistrationForm::class);
    }

    /**
     * Emails sent to this batch's participants from the Notify Participants modal, newest first.
     */
    public function participantNotificationLogs(): HasMany
    {
        return $this->hasMany(EmailReminderLog::class)->notifications()->latest();
    }

    /**
     * Human-readable batch dates, e.g. "March 3–5, 2026" or "March 30 – April 2, 2026".
     */
    public function scheduleLabel(): ?string
    {
        if (! $this->date_start) {
            return null;
        }

        $start = Carbon::parse($this->date_start);
        $end = $this->date_end ? Carbon::parse($this->date_end) : $start;

        if ($start->isSameDay($end)) {
            return $start->format('F j, Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('F j').'–'.$end->format('j, Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j, Y').' – '.$end->format('F j, Y');
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
