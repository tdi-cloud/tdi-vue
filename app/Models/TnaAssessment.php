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
        'supervisor_reviewed_at',
        'supervisor_form',
        'self_rating_scan_path',
        'supervisor_rating_scan_path',
        'result_scan_subordinate_path',
        'result_scan_supervisor_path',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'supervisor_reviewed_at' => 'datetime',
        'supervisor_form' => 'array',
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
