<?php

namespace App\Models;

use Database\Factories\TutorialVideoStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One step of a how-to video's written documentation: a title, the
 * instructions, and an optional screenshot.
 */
class TutorialVideoStep extends Model
{
    /** @use HasFactory<TutorialVideoStepFactory> */
    use HasFactory;

    protected $fillable = [
        'tutorial_video_id',
        'sort_order',
        'title',
        'body',
        'image_path',
    ];

    public function tutorialVideo(): BelongsTo
    {
        return $this->belongsTo(TutorialVideo::class);
    }

    /**
     * Shape sent to the frontend.
     *
     * @return array{id: int, title: string, body: ?string, image_url: ?string}
     */
    public function toDoc(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        ];
    }
}
