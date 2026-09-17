<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row: the site-wide fallback cover image shown for a program
 * that has no cover page of its own uploaded.
 */
class DefaultProgramCover extends Model
{
    protected $fillable = ['image'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /**
     * The relative storage path of the current default cover, if one is set.
     * Cached per-request since transform() may call this once per program.
     */
    public static function currentImagePath(): ?string
    {
        return once(fn () => static::query()->value('image'));
    }
}
