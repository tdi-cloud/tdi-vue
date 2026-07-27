<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationQuestion extends Model
{
    use HasSortOrder;

    public const TYPE_LIKERT5 = 'likert5';

    public const TYPE_SCALE10 = 'scale10';

    public const TYPE_TEXT = 'text';

    public const TYPE_CHECKBOX = 'checkbox';

    public const TYPES = [self::TYPE_LIKERT5, self::TYPE_SCALE10, self::TYPE_TEXT, self::TYPE_CHECKBOX];

    /**
     * Fixed option labels for the two rating-scale question types — these
     * are not admin-customizable per question, only `checkbox` questions
     * store their own `options`.
     *
     * @var array<int, string>
     */
    public const LIKERT5_OPTIONS = [
        5 => '5- Strongly Agree',
        4 => '4- Agree',
        3 => '3- Disagree',
        2 => '2- Strongly Disagree',
        1 => '1 - Not Applicable',
    ];

    /**
     * @var array<int, string>
     */
    public const SCALE10_LEGEND = [
        '10 = Very Exceptional',
        '8-9 = Very Good',
        '6-7 = Satisfactory',
        '5 = Passing',
        '3-4 = Fair',
        '2 = Poor',
        '1 = Completely Unacceptable',
    ];

    protected $fillable = [
        'evaluation_section_id',
        'type',
        'label',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(EvaluationSection::class, 'evaluation_section_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }

    private function siblingsQuery()
    {
        return static::where('evaluation_section_id', $this->evaluation_section_id);
    }
}
