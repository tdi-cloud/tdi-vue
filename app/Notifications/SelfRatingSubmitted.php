<?php

namespace App\Notifications;

use App\Models\TnaAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SelfRatingSubmitted extends Notification
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
            'title' => 'Self-Rating Submitted',
            'message' => "Your TNA self-rating for {$this->assessment->period} has been submitted.",
            'url' => route('tna.self-rating'),
        ];
    }
}
