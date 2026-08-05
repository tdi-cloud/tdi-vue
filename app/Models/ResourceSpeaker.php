<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceSpeaker extends Model
{
    protected $table = 'resource_speakers';

    protected $fillable = [
        'program_id',
        'program_code',
        'batch_id',
        'name',
        'designation',
        'affiliation',
        'topic',
        'expertise',
        'email',
        'contact_number',
        'date_engaged',
        'remarks',
    ];

    protected $casts = [
        'date_engaged' => 'date',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
