<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One play of a how-to video by a user. Repeat plays by the same user within
 * TutorialVideo::VIEW_COOLDOWN_MINUTES are not recorded again.
 */
class TutorialVideoView extends Model
{
    protected $fillable = [
        'tutorial_video_id',
        'user_id',
    ];

    public function tutorialVideo(): BelongsTo
    {
        return $this->belongsTo(TutorialVideo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
