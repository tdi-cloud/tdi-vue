<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCertificateEmailRequest;
use App\Mail\CertificateAvailableMail;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\EmailReminderLog;
use App\Models\Program;
use App\Support\CertificateEmailPlaceholders;
use App\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CertificateEmailController extends Controller
{
    /**
     * Email each selected participant their own certificate PDF, one message per
     * certificate, and log the send. A failed email doesn't stop the rest; it stays
     * "not yet emailed" so it can be retried. The route uses scoped bindings, so a
     * batch from another program is a 404.
     */
    public function send(SendCertificateEmailRequest $request, Program $program, Batch $batch): RedirectResponse
    {
        $validated = $request->validated();
        $email = [
            'subject' => $validated['subject'],
            'body' => $request->sanitizedBody(),
            'signature' => $request->sanitizedSignature(),
        ];
        $senderName = $request->user()->name;

        $recipients = [];
        $failures = [];

        foreach ($request->certificates() as $certificate) {
            $error = $this->deliver($certificate, $program, $batch, $email);

            if ($error === null) {
                $certificate->update(['emailed_at' => now(), 'emailed_by' => $senderName]);
            } else {
                $failures[] = ['name' => $this->recipientName($certificate), 'reason' => $error];
            }

            $recipients[] = $this->describeRecipient($certificate, $error === null);
        }

        $sentCount = count($recipients) - count($failures);

        EmailReminderLog::create([
            'type' => EmailReminderLog::TYPE_CERTIFICATE,
            'sent_by' => $request->user()->empcode,
            'sent_by_name' => $senderName,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'subject' => $validated['subject'],
            'recipients' => $recipients,
            'recipients_count' => $sentCount,
        ]);

        [$toastKey, $toast] = $this->toast($sentCount, count($failures), $validated['type'], $batch);

        return back()
            ->with('certificateEmailResult', ['sent' => $sentCount, 'failed' => $failures])
            ->with($toastKey, $toast);
    }

    /**
     * Send one participant's email. Returns a short reason when it fails, or null.
     *
     * @param  array{subject: string, body: string, signature: string}  $email
     */
    private function deliver(Certificate $certificate, Program $program, Batch $batch, array $email): ?string
    {
        $values = CertificateEmailPlaceholders::valuesFor($certificate, $program, $batch);

        try {
            Mail::to($certificate->participant->user->email)->send(new CertificateAvailableMail(
                certificate: $certificate,
                emailSubject: CertificateEmailPlaceholders::replaceInText($email['subject'], $values),
                body: CertificateEmailPlaceholders::replaceInHtml($email['body'], $values),
                signature: $email['signature'],
                recipientName: $values['full_name'],
                programTitle: (string) $program->title,
                batchName: (string) $batch->batch,
                schedule: $batch->scheduleLabel(),
            ));

            return null;
        } catch (Throwable $e) {
            Log::error('Failed to email certificate', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);

            return Str::limit(strtok($e->getMessage(), "\n") ?: 'The mail server did not accept the message.', 140);
        }
    }

    /**
     * @return array{empcode: string, name: string, email: string, certificate_id: int, certificate_number: string|null, certificate_type: string, status: string}
     */
    private function describeRecipient(Certificate $certificate, bool $sent): array
    {
        return [
            'empcode' => $certificate->empcode,
            'name' => $this->recipientName($certificate),
            'email' => $certificate->participant->user->email,
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'certificate_type' => $certificate->type,
            'status' => $sent ? 'sent' : 'failed',
        ];
    }

    private function recipientName(Certificate $certificate): string
    {
        return trim((string) $certificate->participant?->employee?->name) ?: $certificate->empcode;
    }

    /**
     * The toast shown after sending: a success when anything went out, an error when nothing did.
     *
     * @return array{0: string, 1: array{title: string, message: string, next: string|null, action: array{label: string, href: string}|null}}
     */
    private function toast(int $sentCount, int $failedCount, string $type, Batch $batch): array
    {
        if ($sentCount === 0) {
            return ['error', FlashMessage::make(
                'No certificates were sent',
                "None of the {$failedCount} email(s) could be delivered.",
                'Check the mail settings or try again in a few minutes.',
            )];
        }

        return ['success', FlashMessage::make(
            "{$sentCount} certificate(s) sent",
            "Each {$batch->batch} participant received only their own {$type} certificate PDF."
                .($failedCount > 0 ? " {$failedCount} could not be sent and are still marked as not yet emailed." : ''),
            'Emailed certificates show a green mail icon in the Certificates tab.',
        )];
    }
}
