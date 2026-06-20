<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = [
        'sort_order',
        'batch_id',
        'empcode',
        'attendance',
        'hours',
        'requirements',
        'added_by',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

   
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'empcode', 'EMPCODE');
    }

    public function justification()
    {
        return $this->hasOne(AbsentJustification::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}