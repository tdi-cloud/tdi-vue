<?php

namespace App\Notifications;

use App\Models\TnaAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SelfRatingReviewed extends Notification
{
    use Queueable;

    public function __construct(protected TnaAssessment $assessment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your TNA Result is Ready')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your supervisor has reviewed your TNA self-rating.')
            ->line("Period: {$this->assessment->period}")
            ->action('View TNA Result', route('tna.result.show', $this->assessment->id))
            ->line('Thank you for completing your Training Needs Analysis.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'TNA Result Ready',
            'message' => 'Your supervisor has reviewed your self-rating. Your TNA result is now available.',
            'url' => route('tna.result.show', $this->assessment->id),
        ];
    }
}
