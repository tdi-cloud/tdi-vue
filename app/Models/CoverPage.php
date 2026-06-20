<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoverPage extends Model
{
    protected $fillable = ['program_id', 'image'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}