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
        'revoked_at'  => 'datetime',
        'hours'       => 'decimal:1',
    ];

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

    protected static function booted(): void
    {
        static::creating(function (Certificate $cert) {
            if (empty($cert->certificate_number)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $cert->certificate_number = 'TDI-' . $year . '-CERT-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });

        static::deleting(function (Certificate $cert) {
            if ($cert->file_path) {
                Storage::disk('public')->delete($cert->file_path);
            }
        });
    }
}