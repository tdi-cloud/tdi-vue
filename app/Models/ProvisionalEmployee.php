<?php

namespace App\Models;

use Database\Factories\ProvisionalEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An employee added from an approved registration, kept here until the
 * official (monthly re-imported) employees list includes them. Soft-deleted
 * once merged, so the history stays.
 */
class ProvisionalEmployee extends Model
{
    /** @use HasFactory<ProvisionalEmployeeFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'program_registration_id',
        'user_id',
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
        'employee_row_id',
        'employee_row_created_at',
        'status',
        'merged_at',
        'last_checked_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_WAITING,
    ];

    protected function casts(): array
    {
        return [
            'employee_row_created_at' => 'datetime',
            'merged_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class, 'program_registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The employee columns (uppercase, as the employees table names them)
     * built from this holding record. Blank values become '' because every
     * employees column is NOT NULL.
     *
     * @return array<string, string>
     */
    public function toEmployeeAttributes(): array
    {
        $attributes = [];

        foreach (ProgramRegistration::EMPLOYEE_COLUMNS as $column => $employeeColumn) {
            $attributes[$employeeColumn] = (string) ($this->getAttribute($column) ?? '');
        }

        return $attributes;
    }

    /**
     * Inserts this person's row into the employees table and remembers which
     * row it is.
     */
    public function insertEmployeeRow(): Employee
    {
        $employee = Employee::forceCreate($this->toEmployeeAttributes());

        $this->forceFill([
            'employee_row_id' => $employee->id,
            'employee_row_created_at' => $employee->created_at,
        ])->save();

        return $employee;
    }

    /**
     * The employees row the app inserted for this person, if it is still there.
     * Matching on id, EMPCODE and creation time keeps a re-imported row that
     * happens to reuse the same id from being mistaken for ours.
     */
    public function insertedEmployeeRow(): ?Employee
    {
        if ($this->employee_row_id === null) {
            return null;
        }

        $employee = Employee::find($this->employee_row_id);

        if ($employee === null
            || mb_strtolower((string) $employee->EMPCODE) !== mb_strtolower($this->empcode)
            || $employee->created_at === null
            || $this->employee_row_created_at === null
            || ! $employee->created_at->equalTo($this->employee_row_created_at)) {
            return null;
        }

        return $employee;
    }
}
