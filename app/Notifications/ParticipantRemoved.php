<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParticipantRemoved extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $pendingRequirementTitles
     */
    public function __construct(
        protected string $programTitle,
        protected string $batchLabel,
        protected array $pendingRequirementTitles = []
    ) {}

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
        $message = "You have been removed as a participant in {$this->programTitle} ({$this->batchLabel}).";

        if (! empty($this->pendingRequirementTitles)) {
            $message .= ' Note: your pending submission(s) for '.implode(', ', $this->pendingRequirementTitles).' have also been removed.';
        }

        return [
            'title' => 'Removed from a Program',
            'message' => $message,
            'url' => route('home').'#my-programs',
        ];
    }
}
