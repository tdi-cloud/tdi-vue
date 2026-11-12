<?php

namespace App\Models;

use Database\Factories\PublicFormResponseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's answers to a public form.
 *
 * Linked by EMPCODE, with the employee's details copied in (`employee`),
 * because HR empties and re-imports the employees table every month.
 *
 * `answers` holds option values (see the form's definition), keyed by
 * question; a picked "Other" is stored as the marker value, with the typed
 * text in `other_answers` under the same key.
 */
class PublicFormResponse extends Model
{
    /** @use HasFactory<PublicFormResponseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_slug',
        'empcode',
        'full_name',
        'email',
        'region',
        'office_division',
        'employee',
        'answers',
        'other_answers',
        'confirmation_sent_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee' => 'array',
            'answers' => 'array',
            'other_answers' => 'array',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PublicForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PublicForm::class, 'form_slug', 'slug');
    }

    /**
     * Reference number shown to the employee and in the confirmation email,
     * e.g. "LDS-000042".
     */
    protected function reference(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf('%s-%06d', $this->form?->referencePrefix() ?? 'PF', $this->id),
        );
    }
}
