<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Support\PublicForms\PublicFormDefinition;
use Database\Factories\PublicFormQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A question of a public form. Answers are saved under `answer_key` and the
 * choices' `value`s, which never change once created. Removing a question
 * soft-deletes it, so the answers already sent still show in the responses
 * and the CSV.
 *
 * `options` is a list of {value, label, label_fil, score, exclusive}.
 */
class PublicFormQuestion extends Model
{
    /** @use HasFactory<PublicFormQuestionFactory> */
    use HasFactory;

    use HasSortOrder;
    use SoftDeletes;

    public const TYPES = [
        PublicFormDefinition::TYPE_RANKING,
        PublicFormDefinition::TYPE_MULTIPLE,
        PublicFormDefinition::TYPE_SINGLE,
        PublicFormDefinition::TYPE_TEXT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_form_id',
        'public_form_section_id',
        'answer_key',
        'type',
        'question',
        'question_fil',
        'instruction',
        'instruction_fil',
        'options',
        'allow_other',
        'other_label',
        'other_label_fil',
        'is_required',
        'rank_count',
        'max_choices',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'allow_other' => false,
        'is_required' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'allow_other' => 'boolean',
            'is_required' => 'boolean',
            'rank_count' => 'integer',
            'max_choices' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The choices with every field filled in.
     *
     * @return list<array{value: string, label: string, label_fil: string|null, score: int|null, exclusive: bool}>
     */
    public function normalizedOptions(): array
    {
        return array_values(array_map(fn (array $option) => [
            'value' => (string) $option['value'],
            'label' => (string) $option['label'],
            'label_fil' => $option['label_fil'] ?? null,
            'score' => isset($option['score']) ? (int) $option['score'] : null,
            'exclusive' => (bool) ($option['exclusive'] ?? false),
        ], $this->options ?? []));
    }

    /**
     * @return BelongsTo<PublicForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PublicForm::class, 'public_form_id');
    }

    /**
     * @return BelongsTo<PublicFormSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(PublicFormSection::class, 'public_form_section_id');
    }

    /**
     * Ordered within its section.
     *
     * @return Builder<PublicFormQuestion>
     */
    private function siblingsQuery(): Builder
    {
        return static::where('public_form_section_id', $this->public_form_section_id);
    }
}
