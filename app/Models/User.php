<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'empcode',
        'password',
        'access',
        'is_fstp_member',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_fstp_member' => 'boolean',
        ];
    }

    /**
     * Buong public URL ng avatar (hindi ang raw stored path).
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
        );
    }

    /**
     * True kung 'admin' o 'superadmin' ang access ng user.
     * Parehong buong admin access ang dalawa; ang 'superadmin' ay hiwalay
     * na label lang para sa mga user na nangangailangan ng elevated distinction.
     */
    public function isAdmin(): bool
    {
        return in_array($this->access, ['admin', 'superadmin'], true);
    }

    /**
     * True kung 'superadmin' ang access ng user.
     */
    public function isSuperAdmin(): bool
    {
        return $this->access === 'superadmin';
    }

    /**
     * Whether the user may view and manage Foreign Programs: superadmins always,
     * otherwise only admins flagged as FSTP unit members.
     */
    public function canAccessForeignPrograms(): bool
    {
        return $this->isSuperAdmin() || ($this->isAdmin() && $this->is_fstp_member);
    }

    /**
     * True kung ang empcode ng user ay nasa NHRDC roster — hiwalay ito sa
     * admin access, kaya may sariling restricted na self-service area ang
     * mga NHRDC member kahit hindi sila admin.
     */
    public function isNhrdcMember(): bool
    {
        return $this->empcode && NhrdcMember::where('empcode', $this->empcode)->exists();
    }
}
