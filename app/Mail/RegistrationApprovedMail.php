<?php

namespace App\Mail;

use App\Models\ProgramRegistration;
use App\Support\TemporaryPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent right away (not queued), like the app's other emails, so the
 * temporary password never sits in the jobs table.
 */
class RegistrationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProgramRegistration $registration,
        public ?string $temporaryPassword = null,
        public bool $resent = false,
    ) {}

    public function envelope(): Envelope
    {
        $program = $this->registration->batch?->program?->title ?? 'the program';

        return new Envelope(subject: $this->resent
            ? 'Your new TDI login details'
            : "Registration approved: {$program}");
    }

    public function content(): Content
    {
        $batch = $this->registration->batch;
        $user = $this->registration->user;

        return new Content(
            view: 'emails.registration-approved',
            with: [
                'name' => $this->registration->fullName(),
                'programTitle' => $batch?->program?->title,
                'batchLabel' => $batch?->batch,
                'resent' => $this->resent,
                'temporaryPassword' => $this->temporaryPassword,
                'loginEmail' => $user?->email ?? $this->registration->email,
                'expiresAt' => $user?->temp_password_expires_at?->format('F j, Y g:i A'),
                'validDays' => TemporaryPassword::validDays(),
                'loginUrl' => route('login'),
                'forgotPasswordUrl' => route('password.request'),
            ],
        );
    }
}
