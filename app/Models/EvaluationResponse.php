<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationResponse extends Model
{
    public const SOURCE_PARTICIPANT = 'participant';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'evaluation_form_id',
        'participant_id',
        'empcode',
        'email',
        'respondent_name',
        'name_source',
        'ip_address',
        'user_agent',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
}
