<?php

namespace App\Notifications;

use App\Models\TnaAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSubordinateToRate extends Notification
{
    use Queueable;

    public function __construct(protected TnaAssessment $assessment) {}

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
        return [
            'title' => 'New TNA Rating Request',
            'message' => "{$this->assessment->name} has submitted a self-rating and selected you as their supervisor.",
            'url' => route('tna.supervisory.show', $this->assessment->id),
        ];
    }
}
