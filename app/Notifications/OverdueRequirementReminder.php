<?php

namespace App\Notifications;

use App\Models\Requirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OverdueRequirementReminder extends Notification
{
    use Queueable;

    public function __construct(protected Requirement $requirement, protected int $daysOverdue) {}

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
        $batch = $this->requirement->batch;
        $program = $batch?->program;

        return [
            'title' => 'Overdue Training Requirement',
            'message' => sprintf(
                '%s for "%s" is now %d day(s) overdue. Please submit it as soon as possible.',
                Requirement::nameFor($this->requirement->title),
                $program?->title ?? 'your program',
                $this->daysOverdue
            ),
            'url' => $batch ? route('programs.my-progress', $batch->id) : route('home').'#my-programs',
            'requirement_id' => $this->requirement->id,
            'batch_id' => $batch?->id,
        ];
    }
}
