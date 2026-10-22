<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    protected $fillable = [
        'participant_id',
        'batch_id',
        'program_code',
        'empcode',
        'certificate_number',
        'type',
        'issued_date',
        'hours',
        'status',
        'file_path',
        'file_name',
        'uploaded_by',
        'issued_by',
        'remarks',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'revoked_at' => 'datetime',
        'hours' => 'decimal:1',
    ];

    protected $appends = ['file_url'];

    public const TYPES = [
        'Participation',
        'Completion',
        'Appearance',
        'Appreciation',
        'Recognition',
        'Achievement',
    ];

    public const STATUSES = ['Pending', 'Issued', 'Revoked'];

    // ── Relationships ──

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_code', 'program_code');
    }

    // ── Accessor: full URL of the certificate file ──

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path
            ? Storage::disk('public')->url($this->file_path)
            : null;
    }

    // ── Auto-generate certificate number ──

    /**
     * Next number in this year's series, e.g. "TDI-2026-CERT-0165".
     *
     * Continues from the highest number already issued rather than counting
     * rows, so deleting a certificate never causes a number to be reused.
     */
    public static function nextCertificateNumber(): string
    {
        $prefix = 'TDI-'.now()->year.'-CERT-';

        // Longest first, then highest, so "...-10000" ranks above "...-9999".
        $latest = static::where('certificate_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(certificate_number) DESC')
            ->orderByDesc('certificate_number')
            ->value('certificate_number');

        $next = $latest === null ? 1 : (int) substr($latest, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $cert) {
            if (empty($cert->certificate_number)) {
                $cert->certificate_number = static::nextCertificateNumber();
            }
        });

        static::deleting(function (Certificate $cert) {
            if ($cert->file_path) {
                Storage::disk('public')->delete($cert->file_path);
            }
        });
    }
}
