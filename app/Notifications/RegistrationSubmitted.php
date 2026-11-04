<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegistrationSubmitted extends Notification
{
    use Queueable;

    public function __construct(protected ProgramRegistration $registration) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $batch = $this->registration->batch;
        $program = $batch?->program?->title ?? 'a program';

        return [
            'title' => 'New Registration',
            'message' => "{$this->registration->fullName()} registered for {$program} ({$batch?->batch}) and is waiting for your review.",
            'url' => route('batches.registration.edit', $this->registration->batch_id),
        ];
    }
}
