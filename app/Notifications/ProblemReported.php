<?php

namespace App\Notifications;

use App\Models\ProblemReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProblemReported extends Notification
{
    use Queueable;

    public function __construct(protected ProblemReport $problemReport) {}

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
        $reporterName = $this->problemReport->user?->name ?? 'A user';

        return [
            'title' => 'Problem Reported',
            'message' => "{$reporterName} reported a problem: {$this->problemReport->description}",
            'url' => $this->problemReport->page_url,
        ];
    }
}
