<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionalReport extends Model
{
    protected $fillable = [
        'region', 'month', 'year',
        'file_name', 'file_path',
        'submitted_at', 'notes', 'added_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}