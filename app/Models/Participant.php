<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * The participant's login account, which holds their email address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empcode', 'empcode');
    }

    public function justification()
    {
        return $this->hasOne(AbsentJustification::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function terminalReports()
    {
        return $this->hasMany(TerminalReport::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
