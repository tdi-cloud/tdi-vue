<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsentJustification extends Model
{
    protected $fillable = [
        'participant_id',
        'file_path',
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}