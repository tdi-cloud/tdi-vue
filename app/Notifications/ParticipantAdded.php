<?php

namespace App\Notifications;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParticipantAdded extends Notification
{
    use Queueable;

    public function __construct(protected Participant $participant) {}

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
        $program = $this->participant->batch?->program;

        return [
            'title' => 'Added to a Program',
            'message' => 'You have been added as a participant in '.($program?->title ?? 'a program').'.',
            'url' => $program ? route('programs.show', $program->id) : route('home'),
        ];
    }
}
