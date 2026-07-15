<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';

    protected $appends = ['name', 'initials', 'avatar_color'];

    public function getNameAttribute(): string
    {
        $mi = trim($this->MI ?? '');
        $mi = $mi !== '' ? ' '.rtrim($mi, '.').'.' : '';

        return trim("{$this->FIRSTNAME}{$mi} {$this->LASTNAME}");
    }

    public function getInitialsAttribute(): string
    {
        $f = strtoupper(substr($this->FIRSTNAME ?? '', 0, 1));
        $l = strtoupper(substr($this->LASTNAME ?? '', 0, 1));

        return $f.$l;
    }

    public function getAvatarColorAttribute(): string
    {
        $colors = [
            'bg-violet-500', 'bg-blue-500', 'bg-emerald-500',
            'bg-rose-500', 'bg-amber-500', 'bg-indigo-500',
            'bg-teal-500', 'bg-pink-500',
        ];
        $idx = ord($this->EMPCODE[0] ?? 'A') % count($colors);

        return $colors[$idx];
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'empcode', 'EMPCODE');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'EMPCODE', 'empcode');
    }
}
