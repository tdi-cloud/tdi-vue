<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lite mode for slow connections. The browser sets the `tdi_lite` cookie when
 * the user switches it on (resources/js/composables/useLiteMode.ts), so the
 * very first page load can already send fewer rows.
 */
final class LiteMode
{
    public const COOKIE = 'tdi_lite';

    /** Rows per page used in lite mode. */
    public const PER_PAGE = 10;

    public static function enabled(Request $request): bool
    {
        return $request->cookie(self::COOKIE) === '1';
    }

    /**
     * Page size for a list: an explicit `per_page` in the request wins, otherwise
     * the list's normal default, made smaller in lite mode.
     */
    public static function perPage(Request $request, int $default): int
    {
        $requested = $request->integer('per_page');

        if ($requested > 0) {
            return $requested;
        }

        return self::enabled($request) ? min($default, self::PER_PAGE) : $default;
    }
}
