<?php

namespace App\Models;

use Database\Factories\TutorialVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A how-to video on using the system. Only admins can watch them, and only
 * superadmins can upload, edit or delete them.
 */
class TutorialVideo extends Model
{
    /** @use HasFactory<TutorialVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'uploaded_by',
    ];

    /**
     * The written, step-by-step documentation for this video.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TutorialVideoStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Shape sent to the frontend. Includes the documentation steps only when
     * they were eager-loaded.
     *
     * @return array{id: int, title: string, description: ?string, video_url: string, thumbnail_url: ?string, created_at: ?string, steps_count: int, documentation_url: string, steps?: array<int, array<string, mixed>>}
     */
    public function toCard(): array
    {
        $card = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => Storage::disk('public')->url($this->video_path),
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'steps_count' => (int) ($this->steps_count ?? ($this->relationLoaded('steps') ? $this->steps->count() : $this->steps()->count())),
            'documentation_url' => route('how-to-videos.documentation', $this),
        ];

        if ($this->relationLoaded('steps')) {
            $card['steps'] = $this->steps->map->toDoc()->all();
        }

        return $card;
    }
}
