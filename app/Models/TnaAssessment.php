<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TnaAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'position',
        'period',
        'name',
        'office',
        'division',
        'designation',
        'supervisor_empcode',
        'supervisor_name',
        'supervisor_position',
        'signature',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TnaRating::class);
    }
}