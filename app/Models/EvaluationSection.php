<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationSection extends Model
{
    use HasSortOrder;

    /**
     * Fixed section keys seeded on every form — `facilitators` is special:
     * the public form re-renders that section's questions once per
     * facilitator instead of once overall.
     */
    public const KEY_CONTENT = 'content';

    public const KEY_METHODOLOGY = 'methodology';

    public const KEY_ENVIRONMENT = 'environment';

    public const KEY_FACILITATORS = 'facilitators';

    public const KEY_PLANNED_ACTIONS = 'planned_actions';

    public const KEY_OVERALL = 'overall';

    protected $fillable = [
        'evaluation_form_id',
        'key',
        'title',
        'description',
        'sort_order',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('sort_order');
    }

    private function siblingsQuery()
    {
        return static::where('evaluation_form_id', $this->evaluation_form_id);
    }
}
