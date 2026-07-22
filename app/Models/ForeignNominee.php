<?php

namespace App\Models;

use App\Observers\ForeignNomineeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ForeignNomineeObserver::class)]
class ForeignNominee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'foreign_program_id',
        'foreign_sponsor_config_id',
        'firstname',
        'middle_name',
        'surname',
        'sex',
        'age',
        'position',
        'agency',
        'contact_number',
        'email',
        'status',
        'accomplished_form_path',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ForeignProgram::class, 'foreign_program_id');
    }

    public function sponsorConfig(): BelongsTo
    {
        return $this->belongsTo(ForeignSponsorConfig::class, 'foreign_sponsor_config_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ForeignNomineeSubmission::class, 'foreign_nominee_id');
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(ForeignNomineeAssessment::class, 'foreign_nominee_id');
    }

    public function interviewRatings(): HasMany
    {
        return $this->hasMany(ForeignNomineeInterviewRating::class, 'foreign_nominee_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->middle_name} {$this->surname}");
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'for_interview' => 'For Interview',
            'endorsed' => 'Endorsed',
            'waiting_result' => 'Waiting Result',
            'not_endorsed' => 'Not Endorsed',
            'accepted' => 'Accepted',
            'regret' => 'Regret',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
