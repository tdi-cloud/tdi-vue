<?php

namespace App\Models;

use App\Support\PublicForms\PublicFormDefinition;
use Database\Factories\PublicFormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A public (no login) form built by admins, such as the Learning and
 * Development Survey: its banner texts, sections and questions, whether it
 * takes responses, its background picture and the header picture above its
 * title. The logic for answering it
 * (validation, answers in words, export) is in PublicFormDefinition.
 */
class PublicForm extends Model
{
    /** @use HasFactory<PublicFormFactory> */
    use HasFactory;

    public const CLOSED_NOT_OPEN = 'not_open';

    public const CLOSED_DEADLINE = 'deadline';

    /** Site-wide default background, set by a superadmin in Homepage Images (config/site-images.php). */
    public const DEFAULT_BACKGROUND_KEY = 'public_form_background';

    /** Folder on the public disk for each form's own background picture. */
    public const BACKGROUND_DIRECTORY = 'public-form-backgrounds';

    /** Folder on the public disk for each form's header banner picture (shown above the title). */
    public const HEADER_IMAGE_DIRECTORY = 'public-form-headers';

    /** About how long one question takes, for the "About N minutes" estimate. */
    private const MINUTES_PER_QUESTION = 0.4;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'kicker',
        'description',
        'reference_prefix',
        'is_open',
        'closes_at',
        'background_image',
        'hide_background',
        'header_image',
        'updated_by',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_open' => true,
        'hide_background' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'closes_at' => 'datetime',
            'hide_background' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PublicFormSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PublicFormSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Every current question (removed ones are soft-deleted).
     *
     * @return HasMany<PublicFormQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PublicFormQuestion::class);
    }

    /**
     * Linked by slug, which never changes once the form is created.
     *
     * @return HasMany<PublicFormResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PublicFormResponse::class, 'form_slug', 'slug');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The form with its sections and questions, ready for PublicFormDefinition.
     */
    public static function findBySlugOrFail(string $slug): self
    {
        return self::query()->where('slug', $slug)->with('sections.questions')->firstOrFail();
    }

    /**
     * Open forms, for the public forms list.
     *
     * @param  Builder<PublicForm>  $query
     */
    public function scopeTakingResponses(Builder $query): void
    {
        $query->where('is_open', true)->where(fn (Builder $deadline) => $deadline->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    public function definition(): PublicFormDefinition
    {
        return PublicFormDefinition::for($this);
    }

    /**
     * Why the form isn't taking responses, or null when it is.
     */
    public function closedReason(): ?string
    {
        if (! $this->is_open) {
            return self::CLOSED_NOT_OPEN;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return self::CLOSED_DEADLINE;
        }

        return null;
    }

    public static function estimatedMinutes(int $questionCount): int
    {
        return max(1, (int) round($questionCount * self::MINUTES_PER_QUESTION));
    }

    /**
     * Start of the reference numbers, e.g. "LDS" for "LDS-000042".
     */
    public function referencePrefix(): string
    {
        return $this->reference_prefix ?: 'PF';
    }

    /**
     * What the public pages need, nothing internal.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(int $questionCount): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'kicker' => $this->kicker,
            'description' => $this->description,
            'estimated_minutes' => self::estimatedMinutes($questionCount),
            'closed_reason' => $this->closedReason(),
            'closes_at_label' => $this->closes_at?->format('F j, Y g:i A'),
        ];
    }

    /**
     * URL of this form's own background picture, or null when it has none.
     */
    public function customBackgroundUrl(): ?string
    {
        return $this->background_image ? Storage::disk('public')->url($this->background_image) : null;
    }

    /**
     * The background the public pages show: none, this form's own picture,
     * or the site-wide default.
     */
    public function publicBackgroundUrl(): ?string
    {
        if ($this->hide_background) {
            return null;
        }

        return $this->customBackgroundUrl() ?? SiteImage::urlFor(self::DEFAULT_BACKGROUND_KEY);
    }

    /**
     * URL of the banner picture shown above the title, or null when the form has none.
     */
    public function headerImageUrl(): ?string
    {
        return $this->header_image ? Storage::disk('public')->url($this->header_image) : null;
    }

    /**
     * A link-friendly name from the title that no other form uses, e.g.
     * "learning-needs-survey" or "learning-needs-survey-2".
     */
    public static function uniqueSlugFor(string $title): string
    {
        $base = Str::slug(Str::limit($title, 60, '')) ?: 'form';
        $slug = $base;

        for ($number = 2; self::where('slug', $slug)->exists(); $number++) {
            $slug = "{$base}-{$number}";
        }

        return $slug;
    }

    /**
     * Initials of the title's main words, e.g. "LNS" for "Learning Needs Survey".
     */
    public static function referencePrefixFor(string $title): string
    {
        $initials = collect(preg_split('/[^\pL\pN]+/u', $title, -1, PREG_SPLIT_NO_EMPTY))
            // Small English and Filipino words don't count.
            ->reject(fn (string $word) => in_array(Str::lower($word), ['a', 'an', 'and', 'at', 'for', 'in', 'of', 'on', 'the', 'to', 'ng', 'sa', 'mga'], true))
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->filter(fn (string $letter) => preg_match('/^[A-Z]$/', $letter) === 1)
            ->take(4)
            ->implode('');

        return strlen($initials) >= 2 ? $initials : 'PF';
    }
}
