<?php

namespace App\Http\Controllers;

use App\Mail\ReminderEmail;
use App\Models\EmailReminderLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        $mailable = new ReminderEmail(
            emailSubject: $validated['subject'],
            body: $validated['body'],
            signature: $validated['signature'] ?? '',
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
