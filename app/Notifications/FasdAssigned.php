<?php

namespace App\Notifications;

use App\Models\TnaAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FasdAssigned extends Notification
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
        $fasdName = $this->assessment->supervisor_form['fasd_name'] ?? 'A FASD signatory';

        return [
            'title' => 'FASD Signatory Updated',
            'message' => "{$fasdName} has been assigned as the \"Noted by\" signatory on your TNA Result.",
            'url' => route('tna.result.show', $this->assessment->id),
        ];
    }
}
