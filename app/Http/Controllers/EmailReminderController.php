<?php

namespace App\Http\Controllers;

use App\Mail\ReminderEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailReminderController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to'        => 'required|array|min:1',
            'to.*'      => 'required|email',
            'subject'   => 'required|string|max:500',
            'body'      => 'required|string',
            'signature' => 'nullable|string',
        ]);

        $mailable = new ReminderEmail(
            emailSubject: $validated['subject'],
            body:         $validated['body'],
            signature:    $validated['signature'] ?? '',
        );

        // BCC lahat ng recipients para hindi makita ng isa't isa ang emails ng iba
        Mail::bcc($validated['to'])->send($mailable);

        return back()->with('success', 'Email reminder sent successfully to ' . count($validated['to']) . ' recipient(s).');
    }
}