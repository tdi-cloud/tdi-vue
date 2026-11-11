<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendParticipantNotificationRequest;
use App\Mail\ParticipantNotificationMail;
use App\Models\Batch;
use App\Models\EmailReminderLog;
use App\Models\Program;
use App\Models\User;
use App\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ParticipantNotificationController extends Controller
{
    /** Recipients per message, to stay within the mail server's per-message limit. */
    public const RECIPIENTS_PER_MESSAGE = 50;

    /**
     * Email an announcement to a batch's participants (BCC, in chunks) and log it.
     *
     * Unlike requirement reminders, this is allowed for Rescheduled batches —
     * announcing the new schedule is one of the main reasons to use it.
     * The route uses scoped bindings, so a batch from another program is a 404.
     */
    public function send(SendParticipantNotificationRequest $request, Program $program, Batch $batch): RedirectResponse
    {
        $validated = $request->validated();
        $body = $request->sanitizedBody();
        $signature = $request->sanitizedSignature();

        // A fresh mailable per chunk: bcc() appends, so reusing one would resend to earlier chunks.
        foreach (array_chunk($validated['to'], self::RECIPIENTS_PER_MESSAGE) as $chunk) {
            Mail::bcc($chunk)->send(new ParticipantNotificationMail(
                emailSubject: $validated['subject'],
                body: $body,
                signature: $signature,
                programTitle: $program->title,
                batchName: $batch->batch,
                schedule: $batch->scheduleLabel(),
                venue: $batch->venue ?: null,
            ));
        }

        EmailReminderLog::create([
            'type' => EmailReminderLog::TYPE_NOTIFICATION,
            'sent_by' => $request->user()->empcode,
            'sent_by_name' => $request->user()->name,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'subject' => $validated['subject'],
            'recipients' => $this->describeRecipients($batch, $validated['to']),
            'recipients_count' => count($validated['to']),
        ]);

        return back()->with('success', FlashMessage::make(
            'Notification sent to '.count($validated['to']).' recipient(s)',
            "\"{$validated['subject']}\" is on its way to {$batch->batch} participants.",
            'It is logged under Sent history in the Notify Participants window.',
        ));
    }

    /**
     * Match each address to the batch participant who owns it, so the history shows names.
     * Addresses added by hand that belong to no participant keep a null empcode/name.
     *
     * @param  list<string>  $emails
     * @return list<array{empcode: string|null, name: string|null, email: string}>
     */
    private function describeRecipients(Batch $batch, array $emails): array
    {
        $participants = $batch->participants()->with('employee')->get(['id', 'empcode']);

        $emailsByEmpcode = User::whereIn('empcode', $participants->pluck('empcode'))
            ->whereNotNull('email')
            ->pluck('email', 'empcode');

        $participantsByEmail = $participants
            ->filter(fn ($participant) => isset($emailsByEmpcode[$participant->empcode]))
            ->keyBy(fn ($participant) => mb_strtolower($emailsByEmpcode[$participant->empcode]));

        return array_map(function (string $email) use ($participantsByEmail): array {
            $participant = $participantsByEmail->get(mb_strtolower($email));

            return [
                'empcode' => $participant?->empcode,
                'name' => $participant?->employee?->name,
                'email' => $email,
            ];
        }, $emails);
    }
}
