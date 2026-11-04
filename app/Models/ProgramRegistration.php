<?php

namespace App\Models;

use Database\Factories\ProgramRegistrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProgramRegistration extends Model
{
    /** @use HasFactory<ProgramRegistrationFactory> */
    use HasFactory;

    public const SOURCE_LISTED = 'listed';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Registration snapshot column => matching column on the `employees` table.
     *
     * @var array<string, string>
     */
    public const EMPLOYEE_COLUMNS = [
        'empcode' => 'EMPCODE',
        'lastname' => 'LASTNAME',
        'firstname' => 'FIRSTNAME',
        'mi' => 'MI',
        'office_division' => 'OFFICE/DIVISION',
        'position' => 'POSITION',
        'sg' => 'SG',
        'plantilla_status' => 'PLANTILLA STATUS',
        'sex' => 'SEX',
        'region' => 'REGION',
        'office' => 'OFFICE',
        'location' => 'LOCATION',
        'section' => 'SECTION',
        'unit' => 'UNIT',
    ];

    protected $fillable = [
        'registration_form_id',
        'batch_id',
        'source',
        'empcode',
        'lastname',
        'firstname',
        'mi',
        'office_division',
        'position',
        'sg',
        'plantilla_status',
        'sex',
        'region',
        'office',
        'location',
        'section',
        'unit',
        'email',
        'contact_number',
        'custom_answers',
        'data_privacy_consent_at',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
        'participant_id',
        'user_id',
        'account_created',
        'ip_address',
        'user_agent',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'custom_answers' => 'array',
            'data_privacy_consent_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'account_created' => 'boolean',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function fullName(): string
    {
        $mi = trim((string) $this->mi);
        $mi = $mi !== '' ? ' '.rtrim($mi, '.').'.' : '';

        return trim("{$this->firstname}{$mi} {$this->lastname}");
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provisionalEmployee(): HasOne
    {
        return $this->hasOne(ProvisionalEmployee::class)->withTrashed();
    }

    /**
     * Pending and approved registrations: the ones that count against the
     * slot limit and block a second registration for the same program.
     */
    public function scopeHoldingSlot(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_REJECTED);
    }

    /**
     * Snapshot columns copied from an existing employees row.
     *
     * @return array<string, string|null>
     */
    public static function snapshotFromEmployee(Employee $employee): array
    {
        $snapshot = [];

        foreach (self::EMPLOYEE_COLUMNS as $column => $employeeColumn) {
            $snapshot[$column] = $employee->getAttribute($employeeColumn);
        }

        return $snapshot;
    }
}
