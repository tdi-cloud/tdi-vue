<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

/**
 * Values already used in the employees table, so details people type in by
 * hand stay consistent with the official list.
 */
class EmployeeFieldOptions
{
    /**
     * Always offered for sex, after the values the employee list uses.
     */
    public const PREFER_NOT_TO_SAY = 'Prefer not to say';

    /**
     * Office/divisions are grouped by region, so the form only offers the ones
     * that belong to the region the person picked.
     *
     * @return array{region: list<string>, office_division: array<string, list<string>>, sex: list<string>, plantilla_status: list<string>}
     */
    public static function all(): array
    {
        return Cache::remember('registration:employee-field-options:v5', now()->addMinutes(10), fn () => [
            'region' => self::distinct('REGION'),
            'office_division' => self::officeDivisionsByRegion(),
            'sex' => self::sexOptions(),
            'plantilla_status' => self::distinct('PLANTILLA STATUS'),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function sexOptions(): array
    {
        $values = self::distinct('SEX');

        // With no employees to learn from, the form falls back to a text box instead.
        if ($values === []) {
            return [];
        }

        return array_values(array_unique([...$values, self::PREFER_NOT_TO_SAY]));
    }

    /**
     * The office/divisions offered for a region, or null when the employees
     * table has none to offer (then any typed value is accepted).
     *
     * @return list<string>|null
     */
    public static function officeDivisionsFor(?string $region): ?array
    {
        $byRegion = self::all()['office_division'];

        if ($byRegion === []) {
            return null;
        }

        return $byRegion[trim((string) $region)] ?? [];
    }

    /**
     * People who type their details in only pick a region and office/division,
     * but each employees row also has an OFFICE, LOCATION, SECTION and UNIT.
     * Those already follow from the office/division, so take the combination
     * that existing employees of that region and office/division most often have.
     *
     * @return array{office: ?string, location: ?string, section: ?string, unit: ?string}
     */
    public static function detailsForDivision(?string $region, ?string $division): array
    {
        $details = ['office' => null, 'location' => null, 'section' => null, 'unit' => null];

        if ($division === null || trim($division) === '') {
            return $details;
        }

        $row = Employee::query()
            ->select(['OFFICE', 'LOCATION', 'SECTION', 'UNIT'])
            ->selectRaw('COUNT(*) as matches')
            ->where('OFFICE/DIVISION', trim($division))
            ->when($region !== null && trim($region) !== '', fn ($query) => $query->where('REGION', trim($region)))
            ->groupBy(['OFFICE', 'LOCATION', 'SECTION', 'UNIT'])
            ->orderByDesc('matches')
            ->orderBy('OFFICE')
            ->first();

        if ($row === null) {
            return $details;
        }

        $blankToNull = fn ($value) => trim((string) $value) === '' ? null : trim((string) $value);

        return [
            'office' => $blankToNull($row->OFFICE),
            'location' => $blankToNull($row->LOCATION),
            'section' => $blankToNull($row->SECTION),
            'unit' => $blankToNull($row->UNIT),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function officeDivisionsByRegion(): array
    {
        return Employee::query()
            ->select(['REGION', 'OFFICE/DIVISION'])
            ->whereNotNull('REGION')
            ->where('REGION', '!=', '')
            ->whereNotNull('OFFICE/DIVISION')
            ->where('OFFICE/DIVISION', '!=', '')
            ->distinct()
            ->orderBy('REGION')
            ->orderBy('OFFICE/DIVISION')
            ->get()
            ->groupBy(fn (Employee $employee) => trim((string) $employee->REGION))
            ->map(fn ($rows) => $rows
                ->map(fn (Employee $employee) => trim((string) $employee->getAttribute('OFFICE/DIVISION')))
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all())
            ->all();
    }

    /**
     * Sorted naturally, so R2 comes before R10.
     *
     * @return list<string>
     */
    private static function distinct(string $column): array
    {
        return Employee::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
