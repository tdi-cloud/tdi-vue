<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = [
        'position',
        'unit',
        'element',
        'type',
        'sort_order',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(TnaRating::class);
    }

    /** Scope: mga competency para sa isang position. */
    public function scopeForPosition($query, string $position)
    {
        return $query->where('position', $position);
    }
}