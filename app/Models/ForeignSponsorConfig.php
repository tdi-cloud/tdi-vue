<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForeignSponsorConfig extends Model
{
    protected $fillable = [
        'organizing_sponsor',
        'slug',
        'form_title',
        'is_active',
        'available_courses',
        'accomplished_form_note',
    ];

    protected $casts = [
        'available_courses' => 'array',
        'is_active'         => 'boolean',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(ForeignNomineeRequirement::class, 'foreign_sponsor_config_id')
                    ->orderBy('sort_order');
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(ForeignNominee::class, 'foreign_sponsor_config_id');
    }
}