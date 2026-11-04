<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\ProvisionalEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Compares the holding list of employees added from registrations with the
 * official employees table, which HR empties and re-imports every month:
 *
 *  - official row found  → remove the app's row and take the person off the holding list (merged)
 *  - no row at all       → the re-import wiped the app's row, so put it back (restored)
 *  - only the app's row  → nothing to do yet (still waiting)
 *
 * Safe to run any number of times.
 */
class ProvisionalEmployeeSync
{
    public const MERGED = 'merged';

    public const RESTORED = 'restored';

    public const WAITING = 'waiting';

    /**
     * @return array{merged: int, restored: int, waiting: int}
     */
    public function run(): array
    {
        $summary = [self::MERGED => 0, self::RESTORED => 0, self::WAITING => 0];

        ProvisionalEmployee::query()
            ->where('status', ProvisionalEmployee::STATUS_WAITING)
            ->lazyById()
            ->each(function (ProvisionalEmployee $record) use (&$summary) {
                $summary[$this->check($record)]++;
            });

        return $summary;
    }

    /**
     * Checks one holding record and returns what happened to it.
     */
    public function check(ProvisionalEmployee $record): string
    {
        return DB::transaction(function () use ($record) {
            $appRow = $record->insertedEmployeeRow();

            $hasOfficialRow = Employee::whereRaw('LOWER(EMPCODE) = ?', [Str::lower($record->empcode)])
                ->when($appRow, fn ($query) => $query->whereKeyNot($appRow->id))
                ->exists();

            if ($hasOfficialRow) {
                $appRow?->delete();

                $record->forceFill([
                    'status' => ProvisionalEmployee::STATUS_MERGED,
                    'merged_at' => now(),
                    'last_checked_at' => now(),
                ])->save();
                $record->delete();

                return self::MERGED;
            }

            if ($appRow === null) {
                $record->insertEmployeeRow();
                $record->forceFill(['last_checked_at' => now()])->save();

                return self::RESTORED;
            }

            $record->forceFill(['last_checked_at' => now()])->save();

            return self::WAITING;
        });
    }
}
