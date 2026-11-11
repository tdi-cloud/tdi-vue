<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Program;

/**
 * Fills {placeholders} such as {first_name} in a certificate email with each
 * recipient's own details. Only known placeholders are replaced; anything else in
 * braces is left exactly as written.
 */
final class CertificateEmailPlaceholders
{
    /**
     * The placeholders an admin can insert, in the order the window lists them.
     *
     * @var list<string>
     */
    public const KEYS = [
        'first_name',
        'full_name',
        'program_title',
        'batch',
        'schedule',
        'certificate_type',
        'certificate_number',
    ];

    /** Used for {schedule} when the batch has no dates. */
    public const SCHEDULE_FALLBACK = 'the scheduled dates';

    /**
     * Fill the placeholders in already-sanitized HTML. Values are escaped, so a
     * name containing markup is shown as text and can never inject HTML.
     *
     * @param  array<string, string>  $values
     */
    public static function replaceInHtml(string $html, array $values): string
    {
        return strtr($html, self::tokens(array_map(fn (string $value): string => e($value), $values)));
    }

    /**
     * Fill the placeholders in plain text, such as the subject line.
     *
     * @param  array<string, string>  $values
     */
    public static function replaceInText(string $text, array $values): string
    {
        return strtr($text, self::tokens($values));
    }

    /**
     * The placeholder values for one certificate's recipient.
     *
     * @return array<string, string>
     */
    public static function valuesFor(Certificate $certificate, Program $program, Batch $batch): array
    {
        $employee = $certificate->participant?->employee;
        $fullName = trim((string) $employee?->name) ?: $certificate->empcode;

        return [
            'first_name' => trim((string) $employee?->FIRSTNAME) ?: $fullName,
            'full_name' => $fullName,
            'program_title' => (string) $program->title,
            'batch' => (string) $batch->batch,
            'schedule' => $batch->scheduleLabel() ?? self::SCHEDULE_FALLBACK,
            'certificate_type' => (string) $certificate->type,
            'certificate_number' => (string) $certificate->certificate_number,
        ];
    }

    /**
     * Map "{key}" to its value for the known keys only. strtr() never re-scans a
     * replacement, so a value that itself contains "{batch}" stays as it is.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function tokens(array $values): array
    {
        $tokens = [];

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $tokens['{'.$key.'}'] = $values[$key];
            }
        }

        return $tokens;
    }
}
