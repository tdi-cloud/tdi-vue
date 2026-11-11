<?php

namespace App\Http\Requests;

use App\Models\Batch;
use App\Models\Certificate;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendCertificateEmailRequest extends FormRequest
{
    /**
     * The requested certificates, in the order they were sent, once validated.
     *
     * @var Collection<int, Certificate>|null
     */
    private ?Collection $checkedCertificates = null;

    /**
     * Only admins and superadmins may email certificates.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Certificate::TYPES)],
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['required', 'integer', 'distinct'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'signature' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Choose a certificate type.',
            'type.in' => 'Choose one of the certificate types.',
            'certificate_ids.required' => 'Choose at least one participant.',
            'certificate_ids.min' => 'Choose at least one participant.',
            'certificate_ids.*.distinct' => 'A certificate was selected more than once.',
            'subject.required' => 'Enter a subject for this email.',
            'subject.max' => 'Keep the subject within 500 characters.',
            'body.required' => 'Write a message before sending.',
        ];
    }

    /**
     * Check that the body has readable text and that every certificate can really be emailed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('body') && trim(html_entity_decode(strip_tags($this->sanitizedBody()))) === '') {
                    $validator->errors()->add('body', 'Write a message before sending.');
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'certificate_ids', 'certificate_ids.*'])) {
                    return;
                }

                $this->checkCertificates($validator);
            },
        ];
    }

    /**
     * The validated certificates with their participant, employee and user account loaded.
     *
     * @return Collection<int, Certificate>
     */
    public function certificates(): Collection
    {
        return $this->checkedCertificates ?? new Collection;
    }

    /**
     * The message body with only safe formatting kept.
     */
    public function sanitizedBody(): string
    {
        return app(HtmlSanitizer::class)->sanitize($this->input('body'));
    }

    /**
     * The signature with only safe formatting kept.
     */
    public function sanitizedSignature(): string
    {
        return app(HtmlSanitizer::class)->sanitize($this->input('signature'));
    }

    private function checkCertificates(Validator $validator): void
    {
        /** @var Batch $batch */
        $batch = $this->route('batch');
        $type = (string) $this->input('type');
        $ids = array_map('intval', (array) $this->input('certificate_ids'));

        $certificates = Certificate::query()
            ->with(['participant.employee', 'participant.user'])
            ->whereKey($ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $index => $id) {
            $problem = $this->problemWith($certificates->get($id), $batch, $type);

            if ($problem !== null) {
                $validator->errors()->add("certificate_ids.{$index}", $problem);
            }
        }

        if ($validator->errors()->isEmpty()) {
            $this->checkedCertificates = new Collection(array_map(fn (int $id) => $certificates->get($id), $ids));
        }
    }

    /**
     * Why this certificate can't be emailed, or null when it can.
     */
    private function problemWith(?Certificate $certificate, Batch $batch, string $type): ?string
    {
        if ($certificate === null) {
            return 'A selected certificate no longer exists. Reopen the window and choose again.';
        }

        $name = trim((string) $certificate->participant?->employee?->name) ?: $certificate->empcode;

        return match (true) {
            (int) $certificate->batch_id !== (int) $batch->id => "{$name}'s certificate belongs to another batch, not {$batch->batch}.",
            $certificate->type !== $type => "{$name}'s certificate is a {$certificate->type} certificate, not {$type}.",
            $certificate->status === 'Pending' => "{$name}'s {$type} certificate is still Pending. Mark it as Issued before emailing it.",
            $certificate->status !== 'Issued' => "{$name}'s {$type} certificate was revoked and can't be emailed.",
            ! $certificate->hasStoredFile() => "{$name}'s {$type} certificate has no PDF on file. Upload the PDF, then try again.",
            blank($certificate->participant?->user?->email) => "{$name} has no email address in the system, so the certificate can't be emailed.",
            default => null,
        };
    }
}
