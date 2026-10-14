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
        $form = $this->assessment->supervisor_form ?? [];
        $preparedName = $form['fasd_name'] ?? 'A FASD signatory';
        $notedName = $form['noted_name'] ?? $form['name'] ?? $this->assessment->supervisor_name;

        return [
            'title' => 'TNA Result Signatories Updated',
            'message' => "{$preparedName} has been assigned as the \"Prepared by\" and {$notedName} as the \"Noted by\" signatory on your TNA Result.",
            'url' => route('tna.result.show', $this->assessment->id),
        ];
    }
}
