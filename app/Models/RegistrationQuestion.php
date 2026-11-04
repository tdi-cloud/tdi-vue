<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\RegistrationQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationQuestion extends Model
{
    /** @use HasFactory<RegistrationQuestionFactory> */
    use HasFactory;

    use HasSortOrder;

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_RADIO = 'radio';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPE_SELECT = 'select';

    public const TYPES = [self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_SELECT];

    /**
     * Types whose answers must be one (or, for checkboxes, several) of the stored options.
     */
    public const CHOICE_TYPES = [self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_SELECT];

    /**
     * Types that can offer an "Other" choice with a typed answer.
     */
    public const OTHER_TYPES = [self::TYPE_RADIO, self::TYPE_CHECKBOX];

    /**
     * The value the form sends when "Other" is picked; the typed text arrives separately.
     */
    public const OTHER_VALUE = '__other__';

    protected $fillable = [
        'registration_form_id',
        'label',
        'help_text',
        'type',
        'options',
        'allow_other',
        'is_required',
        'sort_order',
    ];

    protected $attributes = [
        'allow_other' => false,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'allow_other' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function allowsOther(): bool
    {
        return $this->allow_other && in_array($this->type, self::OTHER_TYPES, true);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    private function siblingsQuery()
    {
        return static::where('registration_form_id', $this->registration_form_id);
    }
}
