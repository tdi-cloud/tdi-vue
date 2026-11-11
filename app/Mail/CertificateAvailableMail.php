<?php

namespace App\Mail;

use App\Models\Certificate;
use App\Support\CertificateEmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CertificateAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * One participant's certificate email. The subject and body must already have
     * their placeholders filled in, and the body and signature must already be
     * sanitized — the view prints them unescaped.
     */
    public function __construct(
        public Certificate $certificate,
        public string $emailSubject,
        public string $body,
        public string $signature,
        public string $recipientName,
        public string $programTitle,
        public string $batchName,
        public ?string $schedule = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $hours = (float) $this->certificate->hours;

        return new Content(
            view: 'emails.certificate-available',
            with: [
                'certificateLabel' => CertificateEmailTemplates::label($this->certificate->type),
                'heroEmoji' => CertificateEmailTemplates::emoji($this->certificate->type),
                'certificateNumber' => $this->certificate->certificate_number,
                'issuedDate' => $this->certificate->issued_date?->format('F j, Y'),
                'hours' => $hours > 0 ? rtrim(rtrim(number_format($hours, 1), '0'), '.') : null,
                'myCertificatesUrl' => route('certificates.mine'),
            ],
        );
    }

    /**
     * Only this participant's own PDF.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('public', $this->certificate->file_path)
                ->as($this->attachmentName())
                ->withMime('application/pdf'),
        ];
    }

    /**
     * A friendly, file-system-safe name, e.g. "Certificate of Completion - Juan Cruz.pdf".
     */
    public function attachmentName(): string
    {
        $name = CertificateEmailTemplates::label($this->certificate->type).' - '.$this->recipientName;

        $safeName = Str::of($name)
            ->replaceMatches('/[^\pL\pN .,\'()_-]+/u', ' ')
            ->squish()
            ->limit(150, '')
            ->trim(' .-');

        return ($safeName->isEmpty() ? 'Certificate' : $safeName->toString()).'.pdf';
    }
}
