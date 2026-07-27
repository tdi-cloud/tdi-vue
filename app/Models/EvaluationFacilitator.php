<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationFacilitator extends Model
{
    use HasSortOrder;

    protected $fillable = [
        'evaluation_form_id',
        'name',
        'role',
        'sort_order',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }

    public static function nextSortOrder(int $evaluationFormId): int
    {
        return static::where('evaluation_form_id', $evaluationFormId)->count();
    }

    private function siblingsQuery()
    {
        return static::where('evaluation_form_id', $this->evaluation_form_id);
    }
}
