<?php

namespace App\Http\Controllers;

use App\Mail\ReminderEmail;
use App\Models\Batch;
use App\Models\EmailReminderLog;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailReminderController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|array|min:1',
            'to.*' => 'required|email',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'signature' => 'nullable|string',
            'program_id' => 'nullable|integer|exists:programs,id',
            'batch_id' => 'nullable|integer|exists:batches,id',
            'requirement_id' => 'nullable|integer|exists:requirements,id',
            'recipients' => 'nullable|array',
            'recipients.*.empcode' => 'nullable|string',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.email' => 'nullable|email',
        ]);

        // Huwag payagan ang sinuman na mag-send ng email reminder para sa isang
        // naka-reschedule nang batch, dahil malamang luma na/mali na ang mga detalye
        // (petsa, deadline) na nakasaad sa reminder — para maiwasan ang pagkalito ng participants.
        if (! empty($validated['batch_id'])) {
            $batch = Batch::find($validated['batch_id']);

            if ($batch && $batch->status === 'Rescheduled') {
                throw ValidationException::withMessages([
                    'batch_id' => 'This batch has been rescheduled. Email reminders are disabled for it to avoid sending outdated schedule information.',
                ]);
            }
        }

        // Huwag payagan ang sinuman na mag-send ng reminder para sa isang
        // requirement na hindi pa overdue — walang dapat i-remind hangga't
        // hindi pa lumalagpas ang due date nito.
        $requirement = null;

        if (! empty($validated['requirement_id'])) {
            $requirement = Requirement::with('batch.program')->find($validated['requirement_id']);

            if ($requirement && (! $requirement->due_date || ! $requirement->due_date->lt(now()->startOfDay()))) {
                throw ValidationException::withMessages([
                    'requirement_id' => 'This requirement is not yet overdue. Email reminders can only be sent once the due date has passed.',
                ]);
            }
        }

        // Ang "Requirement Overview" card sa email ay opsyonal lang lalabas
        // kapag may specific requirement na pinili (may kumpletong program
        // title at due date tayo dito) — hindi ito ipinipilit kapag general
        // reminder lang (batch/program-level) ang ipinapadala.
        //
        // Status na "Overdue" (hindi "Pending Submission") ang ipinapadala
        // dahil ang isa lang na katotohanan na alam natin dito ay na-lampasan
        // na ang due date (na siyang tanging dahilan kung bakit pinayagan ang
        // pag-send na ito sa itaas) — hindi natin alam kung na-submit na ba
        // ito ng bawat isa sa maraming BCC recipient, kaya hindi tumpak na
        // ipalagay na "Pending Submission" sila lahat.
        $mailable = new ReminderEmail(
            emailSubject: $validated['subject'],
            body: $validated['body'],
            signature: $validated['signature'] ?? '',
            trainingProgram: $requirement?->batch?->program?->title,
            dueDate: $requirement?->due_date?->format('F j, Y'),
            status: $requirement ? 'Overdue' : null,
        );

        // BCC lahat ng recipients para hindi makita ng isa't isa ang emails ng iba
        Mail::bcc($validated['to'])->send($mailable);

        // I-log kung sino ang nag-send, kailan, at kanino, para may history
        // ng mga reminder na naipadala na (hal. para sa isang requirement/batch).
        $recipients = ! empty($validated['recipients'])
            ? $validated['recipients']
            : array_map(fn ($email) => ['empcode' => null, 'name' => null, 'email' => $email], $validated['to']);

        EmailReminderLog::create([
            'sent_by' => $request->user()->empcode,
            'sent_by_name' => $request->user()->name,
            'program_id' => $validated['program_id'] ?? null,
            'batch_id' => $validated['batch_id'] ?? null,
            'requirement_id' => $validated['requirement_id'] ?? null,
            'subject' => $validated['subject'],
            'recipients' => $recipients,
            'recipients_count' => count($validated['to']),
        ]);

        return back()->with('success', 'Email reminder sent successfully to '.count($validated['to']).' recipient(s).');
    }
}
