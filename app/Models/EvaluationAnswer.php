<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAnswer extends Model
{
    protected $fillable = [
        'evaluation_response_id',
        'evaluation_question_id',
        'evaluation_facilitator_id',
        'value_numeric',
        'value_text',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(EvaluationResponse::class, 'evaluation_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestion::class, 'evaluation_question_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(EvaluationFacilitator::class, 'evaluation_facilitator_id');
    }
}
