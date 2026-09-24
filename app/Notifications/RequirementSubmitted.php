<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementSubmitted extends Notification
{
    use Queueable;

    public function __construct(protected Submission $submission) {}

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
        $employeeName = $this->submission->participant?->employee?->name
            ?? $this->submission->participant?->empcode
            ?? 'An employee';

        $requirementTitle = $this->submission->requirement?->title ?? 'a requirement';
        $programTitle = $this->submission->program?->title ?? $this->submission->program_code;

        return [
            'title' => 'New Requirement Submission',
            'message' => "{$employeeName} submitted {$requirementTitle} for {$programTitle}.",
            'url' => route('submissions.index', ['submission_id' => $this->submission->id]),
        ];
    }
}
