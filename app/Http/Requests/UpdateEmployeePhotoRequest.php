<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    /** @var list<string> */
    public const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Only superadmins manage employee pictures (same as user avatars and site images).
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * The type check reads the file's contents, not its name, so a renamed
     * script or document is rejected.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', self::TYPES),
                'max:'.self::MAX_KILOBYTES,
                'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a picture to upload.',
            'photo.file' => 'The picture did not upload completely. Please try again.',
            'photo.uploaded' => 'The picture did not upload completely. Please try again.',
            'photo.image' => 'Use a JPG, PNG or WebP picture.',
            'photo.mimes' => 'Use a JPG, PNG or WebP picture.',
            'photo.max' => 'The picture must be 5 MB or smaller.',
            'photo.dimensions' => 'The picture must be between 64 and 8,000 pixels on each side.',
        ];
    }
}
