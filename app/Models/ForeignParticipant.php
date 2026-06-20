<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForeignParticipant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'foreign_program_id', 'name', 'sex', 'position',
        'agency', 'contact_no', 'email', 'status',
    ];

    public function foreignProgram()
    {
        return $this->belongsTo(ForeignProgram::class);
    }
}