<?php

namespace App\Models;

use Database\Factories\RegistrationFormFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrationForm extends Model
{
    /** @use HasFactory<RegistrationFormFactory> */
    use HasFactory;

    /**
     * Color presets the public form can use. The Tailwind classes for each
     * one live in resources/js/lib/registrationThemes.ts.
     *
     * @var array<int, string>
     */
    public const THEMES = ['blue', 'navy', 'sky', 'indigo', 'teal'];

    public const CLOSED_NOT_OPEN = 'not_open';

    public const CLOSED_DEADLINE = 'deadline';

    public const CLOSED_FULL = 'full';

    protected $fillable = [
        'batch_id',
        'slug',
        'title',
        'intro_text',
        'background_image',
        'hide_background',
        'theme',
        'is_open',
        'closes_at',
        'slot_limit',
        'created_by_empcode',
        'created_by_name',
    ];

    protected $attributes = [
        'title' => 'Program Registration',
        'theme' => 'blue',
        'is_open' => false,
        'hide_background' => false,
    ];

    protected $appends = ['background_image_url'];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'hide_background' => 'boolean',
            'closes_at' => 'datetime',
            'slot_limit' => 'integer',
        ];
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? Storage::disk('public')->url($this->background_image) : null;
    }

    /**
     * The background the public form shows: none, this batch's own image, or
     * the site-wide default.
     */
    public function publicBackgroundUrl(): ?string
    {
        if ($this->hide_background) {
            return null;
        }

        return $this->background_image_url ?? SiteImage::urlFor('registration_background');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(RegistrationQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function editors(): HasMany
    {
        return $this->hasMany(RegistrationFormEditor::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * The owner is whoever set the form up. Forms saved without a creator
     * have no owner, so only superadmins (and editors they add) can change them.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->created_by_empcode !== null && $this->created_by_empcode === $user->empcode;
    }

    /**
     * Who can change this form and review its registrations: any superadmin,
     * plus the owner and the editors they added while they still have admin
     * access. Checked on every request, so lowering someone to "user" takes
     * their edit rights away right away.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        return $this->isOwnedBy($user) || $this->editors()->where('user_id', $user->id)->exists();
    }

    /**
     * Only the owner and superadmins can add or remove editors.
     */
    public function canManageEditorsBy(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && $this->isOwnedBy($user));
    }

    /**
     * Everyone canBeEditedBy() lets in, as one query (used to notify them).
     *
     * @return Collection<int, User>
     */
    public function usersWhoCanEdit(): Collection
    {
        return User::query()
            ->where('access', 'superadmin')
            ->orWhere(fn ($query) => $query
                ->where('access', 'admin')
                ->where(fn ($people) => $people
                    ->whereIn('id', $this->editors()->reorder()->select('user_id'))
                    ->when($this->created_by_empcode, fn ($owner, string $empcode) => $owner->orWhere('empcode', $empcode))))
            ->get();
    }

    /**
     * The additional questions every new form starts with. Managers can edit,
     * reorder or delete them like any other question.
     */
    public function seedDefaultQuestions(): void
    {
        $this->questions()->createMany([
            [
                'label' => 'Are you a Person With Disability?',
                'type' => RegistrationQuestion::TYPE_RADIO,
                'options' => ['Yes', 'No'],
                'is_required' => true,
                'sort_order' => 0,
            ],
            [
                'label' => 'Type of Disability',
                'help_text' => 'Answer only if you answered Yes above.',
                'type' => RegistrationQuestion::TYPE_TEXT,
                'is_required' => false,
                'sort_order' => 1,
            ],
        ]);
    }

    /**
     * Registrations that still hold a slot (pending or approved).
     */
    public function slotsTaken(): int
    {
        return $this->registrations()->holdingSlot()->count();
    }

    public function isFull(): bool
    {
        return $this->slot_limit !== null && $this->slotsTaken() >= $this->slot_limit;
    }

    /**
     * Why the public form isn't accepting registrations, or null when it is.
     */
    public function closedReason(): ?string
    {
        if (! $this->is_open) {
            return self::CLOSED_NOT_OPEN;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return self::CLOSED_DEADLINE;
        }

        if ($this->isFull()) {
            return self::CLOSED_FULL;
        }

        return null;
    }

    /**
     * A short, URL-safe, unique token for the public form link, built from
     * the program code and batch label, with a random suffix only on collision.
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
