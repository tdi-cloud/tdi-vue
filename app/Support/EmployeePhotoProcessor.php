<?php

namespace App\Support;

use App\Models\EmployeePhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores employee pictures (shown on the Employees page). Each upload is resized to fit a
 * MAX_EDGE square and re-encoded as WebP (which also strips anything hidden
 * in the original file), under a new random name.
 *
 * If GD is unavailable or the server lacks the memory to decode the
 * picture, the already-validated original is stored as-is instead.
 */
class EmployeePhotoProcessor
{
    public const DIRECTORY = 'employee-profiles';

    /** Longest side in pixels: sharp on 2x screens at the largest size the Employees page shows. */
    public const MAX_EDGE = 720;

    public const WEBP_QUALITY = 82;

    /** Rough bytes GD needs per decoded pixel, used to avoid running out of memory. */
    private const BYTES_PER_PIXEL = 5;

    /**
     * Saves the picture and returns its path on the public disk.
     */
    public function store(UploadedFile $file): string
    {
        $basename = (string) Str::uuid();
        $optimized = $this->optimize($file);

        if ($optimized !== null) {
            $path = self::DIRECTORY.'/'.$basename.'.webp';

            if (! Storage::disk(EmployeePhoto::DISK)->put($path, $optimized)) {
                throw new RuntimeException('The picture could not be saved.');
            }

            return $path;
        }

        // The extension comes from the file's contents, never from its original name.
        $stored = $file->storeAs(self::DIRECTORY, $basename.'.'.($file->guessExtension() ?? 'jpg'), EmployeePhoto::DISK);

        if ($stored === false) {
            throw new RuntimeException('The picture could not be saved.');
        }

        return $stored;
    }

    /**
     * Deletes a stored picture unless another employee record still points to it.
     * Paths outside the employee-profiles folder are never touched.
     */
    public function deleteIfUnused(?string $path): void
    {
        if ($path === null || ! str_starts_with($path, self::DIRECTORY.'/')) {
            return;
        }

        if (EmployeePhoto::query()->where('path', $path)->exists()) {
            return;
        }

        Storage::disk(EmployeePhoto::DISK)->delete($path);
    }

    /**
     * Resized WebP bytes, or null when the picture can't be processed here.
     */
    private function optimize(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $realPath = $file->getRealPath();
        $info = $realPath ? @getimagesize($realPath) : false;

        if ($info === false || $info[0] < 1 || $info[1] < 1 || ! $this->hasMemoryFor($info[0], $info[1])) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($realPath));

        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        unset($source);

        // Rotate after resizing so only the small copy is turned.
        $resized = $this->applyExifOrientation($resized, $realPath, $info[2]);

        ob_start();
        $encoded = imagewebp($resized, null, self::WEBP_QUALITY);
        $binary = ob_get_clean();

        return $encoded && is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * Phone cameras save JPEGs sideways with an EXIF hint; turn them upright.
     */
    private function applyExifOrientation(GdImage $image, string $path, int $imageType): GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated === false) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    private function hasMemoryFor(int $width, int $height): bool
    {
        $limit = $this->memoryLimitInBytes();

        if ($limit === null) {
            return true;
        }

        $needed = ($width * $height * self::BYTES_PER_PIXEL) + (16 * 1024 * 1024);

        return memory_get_usage(true) + $needed < $limit;
    }

    /**
     * PHP's memory_limit in bytes, or null when unlimited.
     */
    private function memoryLimitInBytes(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return null;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
