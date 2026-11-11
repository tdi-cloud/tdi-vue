<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParticipantNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The body and signature must already be sanitized — the view prints them unescaped.
     */
    public function __construct(
        public string $emailSubject,
        public string $body,
        public string $signature,
        public string $programTitle,
        public string $batchName,
        public ?string $schedule = null,
        public ?string $venue = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.participant-notification',
        );
    }
}
