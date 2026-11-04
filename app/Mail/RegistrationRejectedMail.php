<?php

namespace App\Mail;

use App\Models\ProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProgramRegistration $registration) {}

    public function envelope(): Envelope
    {
        $program = $this->registration->batch?->program?->title ?? 'the program';

        return new Envelope(subject: "Registration update: {$program}");
    }

    public function content(): Content
    {
        $batch = $this->registration->batch;

        return new Content(
            view: 'emails.registration-rejected',
            with: [
                'name' => $this->registration->fullName(),
                'programTitle' => $batch?->program?->title,
                'batchLabel' => $batch?->batch,
                'reason' => $this->registration->remarks,
            ],
        );
    }
}
