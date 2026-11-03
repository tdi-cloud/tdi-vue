<?php

namespace App\Notifications;

use App\Models\ProblemReportReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to the reporter when a super admin replies, and to the super admins
 * when the reporter writes back.
 */
class ProblemReportReplied extends Notification
{
    use Queueable;

    public function __construct(protected ProblemReportReply $reply) {}

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
        $report = $this->reply->problemReport;
        $authorName = $this->reply->user?->name ?? 'Someone';
        $isReporter = $report->user()->is($notifiable);

        return [
            'title' => $isReporter ? 'Reply to your problem report' : 'New reply on a problem report',
            'message' => "{$authorName} replied: ".Str::limit($this->reply->body, 140),
            'url' => $isReporter
                ? route('problem-reports.mine', ['report' => $report->id])
                : route('problem-reports.index', ['report' => $report->id]),
        ];
    }
}
