<?php

namespace App\Models;

use App\Support\EvaluationDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EvaluationForm extends Model
{
    protected $fillable = [
        'batch_id',
        'slug',
        'title',
        'intro_text',
        'background_image',
        'is_active',
        'created_by_empcode',
        'created_by_name',
    ];

    protected $appends = ['background_image_url'];

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? Storage::disk('public')->url($this->background_image) : null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(EvaluationSection::class)->orderBy('sort_order');
    }

    public function facilitators(): HasMany
    {
        return $this->hasMany(EvaluationFacilitator::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EvaluationResponse::class);
    }

    public function seedDefaults(): void
    {
        EvaluationDefaults::seedInto($this);
    }

    /**
     * A short, URL-safe, unique token for the public form link — combines
     * the program code and batch label so it stays human-recognizable, with
     * a random suffix appended only if that combination already collides.
     */
    public static function generateSlugFor(Batch $batch): string
    {
        $base = Str::slug($batch->program_code.'-'.$batch->batch);
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
