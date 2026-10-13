<?php

namespace App\Models;

use Database\Factories\TutorialVideoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A how-to video on using the system. Only admins can watch them, and only
 * superadmins can upload, edit or delete them.
 */
class TutorialVideo extends Model
{
    /** @use HasFactory<TutorialVideoFactory> */
    use HasFactory;

    /** Repeat plays by the same user within this many minutes count as one view. */
    public const VIEW_COOLDOWN_MINUTES = 30;

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
     * Every recorded play of this video.
     */
    public function views(): HasMany
    {
        return $this->hasMany(TutorialVideoView::class);
    }

    /**
     * Adds views_count (total plays) and viewers_count (distinct users) to the query.
     */
    public function scopeWithViewCounts(Builder $query): void
    {
        $query->withCount([
            'views',
            'views as viewers_count' => fn (Builder $views) => $views->select(DB::raw('count(distinct user_id)')),
        ]);
    }

    /**
     * Records a play by the user, unless they already played it within the cooldown.
     */
    public function recordViewBy(User $user): void
    {
        $viewedRecently = $this->views()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(self::VIEW_COOLDOWN_MINUTES))
            ->exists();

        if (! $viewedRecently) {
            $this->views()->create(['user_id' => $user->id]);
        }
    }

    /**
     * Shape sent to the frontend. Includes the documentation steps only when
     * they were eager-loaded.
     *
     * @return array{id: int, title: string, description: ?string, video_url: string, thumbnail_url: ?string, created_at: ?string, steps_count: int, views_count: int, viewers_count: int, documentation_url: string, steps?: array<int, array<string, mixed>>}
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
            'views_count' => (int) ($this->views_count ?? $this->views()->count()),
            'viewers_count' => (int) ($this->viewers_count ?? $this->views()->distinct()->count('user_id')),
            'documentation_url' => route('how-to-videos.documentation', $this),
        ];

        if ($this->relationLoaded('steps')) {
            $card['steps'] = $this->steps->map->toDoc()->all();
        }

        return $card;
    }
}
