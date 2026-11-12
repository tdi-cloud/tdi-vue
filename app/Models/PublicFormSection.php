<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\PublicFormSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A section of a public form, shown as one step. It can be limited to
 * employees with a minimum salary grade (e.g. SG 18 and above).
 */
class PublicFormSection extends Model
{
    /** @use HasFactory<PublicFormSectionFactory> */
    use HasFactory;

    use HasSortOrder;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_form_id',
        'title',
        'step_label',
        'description',
        'min_salary_grade',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_salary_grade' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PublicForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PublicForm::class, 'public_form_id');
    }

    /**
     * @return HasMany<PublicFormQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PublicFormQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The short name in the form's progress steps.
     */
    public function stepLabel(): string
    {
        return $this->step_label ?: Str::limit($this->title, 16);
    }

    /**
     * Who answers this section, when not everyone does.
     */
    public function audienceLabel(): ?string
    {
        return $this->min_salary_grade === null ? null : "For salary grade {$this->min_salary_grade} and above";
    }

    /**
     * @return Builder<PublicFormSection>
     */
    private function siblingsQuery(): Builder
    {
        return static::where('public_form_id', $this->public_form_id);
    }
}
