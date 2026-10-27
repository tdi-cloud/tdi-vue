<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Builds the system version label shown in the UI, e.g. "v1.0.0 build 3 (1e05267)".
 *
 * The release number comes from config('app.version'). The build number is the
 * count of commits since the latest "v*" release tag, and the short commit hash
 * identifies the exact code running. Both are read from a REVISION file written
 * at deploy time (`git describe --tags --long --match "v*" > REVISION`), or from
 * the .git folder when the app runs from a git checkout.
 */
final class AppVersion
{
    private const DESCRIBE_PATTERN = '/^v.+-(?<build>\d+)-g(?<hash>[0-9a-f]{7,40})$/i';

    public static function label(?string $basePath = null): string
    {
        $label = 'v'.config('app.version');
        $build = self::buildNumber($basePath);
        $commit = self::commit($basePath);

        if ($build !== null) {
            $label .= " build {$build}";
        }

        return $commit === null ? $label : "{$label} ({$commit})";
    }

    /**
     * Compact form for tight spaces like the sidebar, e.g. "v2.0.0 b3".
     */
    public static function shortLabel(?string $basePath = null): string
    {
        $label = 'v'.config('app.version');
        $build = self::buildNumber($basePath);

        return $build === null ? $label : "{$label} b{$build}";
    }

    public static function commit(?string $basePath = null): ?string
    {
        $basePath ??= base_path();

        $revision = self::readFile($basePath.'/REVISION');

        if ($revision !== null && preg_match(self::DESCRIBE_PATTERN, $revision, $matches)) {
            return substr($matches['hash'], 0, 7);
        }

        $hash = $revision ?? self::readGitHead($basePath.'/.git');

        if ($hash === null || ! preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
            return null;
        }

        return substr($hash, 0, 7);
    }

    /**
     * Number of commits since the latest release tag; cached per commit so git
     * runs only once after each new commit or deploy.
     */
    public static function buildNumber(?string $basePath = null): ?int
    {
        $basePath ??= base_path();

        $revision = self::readFile($basePath.'/REVISION');

        if ($revision !== null) {
            return preg_match(self::DESCRIBE_PATTERN, $revision, $matches) ? (int) $matches['build'] : null;
        }

        $commit = self::commit($basePath);

        if ($commit === null) {
            return null;
        }

        return Cache::rememberForever("app-version:build:{$commit}", function () use ($basePath): ?int {
            $result = Process::path($basePath)->run(['git', 'describe', '--tags', '--long', '--match', 'v*']);

            if ($result->failed() || ! preg_match(self::DESCRIBE_PATTERN, trim($result->output()), $matches)) {
                return null;
            }

            return (int) $matches['build'];
        });
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
