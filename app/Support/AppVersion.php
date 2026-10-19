<?php

namespace App\Support;

/**
 * Builds the system version label shown in the UI, e.g. "v1.0.0 (1e05267)".
 *
 * The release number comes from config('app.version'); the short commit hash
 * is read from a REVISION file written at deploy time, or from the .git
 * folder when the app runs from a git checkout.
 */
final class AppVersion
{
    public static function label(?string $basePath = null): string
    {
        $version = 'v'.config('app.version');
        $commit = self::commit($basePath);

        return $commit === null ? $version : "{$version} ({$commit})";
    }

    public static function commit(?string $basePath = null): ?string
    {
        $basePath ??= base_path();

        $hash = self::readFile($basePath.'/REVISION') ?? self::readGitHead($basePath.'/.git');

        if ($hash === null || ! preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
            return null;
        }

        return substr($hash, 0, 7);
    }

    private static function readGitHead(string $gitPath): ?string
    {
        $head = self::readFile($gitPath.'/HEAD');

        if ($head === null || ! str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $ref = substr($head, 5);

        return self::readFile($gitPath.'/'.$ref) ?? self::readPackedRef($gitPath, $ref);
    }

    private static function readPackedRef(string $gitPath, string $ref): ?string
    {
        $packedRefs = self::readFile($gitPath.'/packed-refs');

        if ($packedRefs === null) {
            return null;
        }

        foreach (preg_split('/\R/', $packedRefs) as $line) {
            if (str_ends_with($line, ' '.$ref)) {
                return strtok($line, ' ');
            }
        }

        return null;
    }

    private static function readFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));

        return $contents === '' ? null : $contents;
    }
}
