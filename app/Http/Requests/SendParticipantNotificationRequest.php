<?php

namespace App\Http\Requests;

use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendParticipantNotificationRequest extends FormRequest
{
    /**
     * Only admins and superadmins may email a batch's participants.
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
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email', 'distinct:ignore_case'],
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
            'to.required' => 'Add at least one recipient.',
            'to.min' => 'Add at least one recipient.',
            'to.*.email' => '":input" is not a valid email address.',
            'to.*.distinct' => '":input" is listed more than once.',
            'subject.required' => 'Enter a subject for this notification.',
            'body.required' => 'Write a message before sending.',
        ];
    }

    /**
     * Reject a body that has no readable text once unsafe markup is removed (e.g. an empty editor).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('body')) {
                    return;
                }

                if (trim(html_entity_decode(strip_tags($this->sanitizedBody()))) === '') {
                    $validator->errors()->add('body', 'Write a message before sending.');
                }
            },
        ];
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
}
