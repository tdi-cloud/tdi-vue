<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForeignNomineeRequirement extends Model
{
    protected $fillable = [
        'foreign_sponsor_config_id',
        'sort_order',
        'question',
        'description',
        'link',
        'file_required',
    ];

    protected $casts = [
        'file_required' => 'boolean',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(ForeignSponsorConfig::class, 'foreign_sponsor_config_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ForeignNomineeSubmission::class, 'foreign_nominee_requirement_id');
    }
}