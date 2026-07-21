<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForeignProgram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'program_title', 'description', 'program_start', 'program_end', 'slots', 'modality',
        'online_start', 'online_end', 'inperson_start', 'inperson_end',
        'program_cost', 'fund_source', 'category',
        'organizing_sponsor', 'status', 'submission_date', 'embassy_deadline',
        'interview_date', 'invited_agencies', 'attached_agency',
    ];

    protected $casts = [
        'program_start' => 'date',
        'program_end' => 'date',
        'online_start' => 'date',
        'online_end' => 'date',
        'inperson_start' => 'date',
        'inperson_end' => 'date',
        'submission_date' => 'date',
        'embassy_deadline' => 'date',
        'interview_date' => 'date',
    ];

    public function participants()
    {
        return $this->hasMany(ForeignParticipant::class);
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(ForeignNominee::class, 'foreign_program_id')
            ->orderBy('surname')->orderBy('firstname');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(OrganizingSponsor::class, 'organizing_sponsor', 'name');
    }
}
