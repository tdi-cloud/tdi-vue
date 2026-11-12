<?php

namespace App\Mail;

use App\Models\PublicFormResponse;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the employee their public form response was received, with the
 * reference number and a copy of their answers. Sent right away (not
 * queued), like the app's other emails.
 */
class PublicFormResponseReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PublicFormResponse $response,
        public PublicFormDefinition $definition,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Response received: {$this->formTitle()}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.public-form-response-received',
            with: [
                'name' => $this->response->full_name,
                'formTitle' => $this->formTitle(),
                'reference' => $this->response->reference,
                'submittedAt' => $this->response->created_at?->timezone(config('app.timezone'))->format('F j, Y g:i A'),
                'answers' => $this->definition->readableAnswers($this->response->answers ?? [], $this->response->other_answers ?? []),
            ],
        );
    }

    /**
     * The title people saw on the form.
     */
    public function formTitle(): string
    {
        return $this->definition->form()->title;
    }
}
