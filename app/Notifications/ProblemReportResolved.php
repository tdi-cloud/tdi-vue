<?php

namespace App\Notifications;

use App\Models\ProblemReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the reporter that a super admin marked their problem report resolved.
 */
class ProblemReportResolved extends Notification
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
        return [
            'title' => 'Your problem report is resolved',
            'message' => 'A super admin marked your report as resolved: "'.Str::limit($this->problemReport->description, 120).'"',
            'url' => route('problem-reports.mine', ['report' => $this->problemReport->id]),
        ];
    }
}
