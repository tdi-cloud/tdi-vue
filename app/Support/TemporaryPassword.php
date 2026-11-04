<?php

namespace App\Support;

/**
 * Random one-time passwords for accounts created from approved registrations.
 * Look-alike characters (0/O, 1/l/I) are left out so the password is easy to
 * type from an email.
 */
class TemporaryPassword
{
    public const LENGTH = 12;

    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    /**
     * At least one uppercase letter, one lowercase letter and one digit, in random order.
     */
    public static function generate(int $length = self::LENGTH): string
    {
        $all = self::UPPER.self::LOWER.self::DIGITS;

        $characters = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
        ];

        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        // Fisher–Yates shuffle with a cryptographically secure source.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    /**
     * How long a temporary password works before the person must use "Forgot password".
     */
    public static function validDays(): int
    {
        return max(1, (int) config('registration.temp_password_days', 7));
    }

    private static function pick(string $pool): string
    {
        return $pool[random_int(0, strlen($pool) - 1)];
    }
}
