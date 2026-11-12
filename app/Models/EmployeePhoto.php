<?php

namespace App\Models;

use Database\Factories\EmployeePhotoFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Profile picture shown on the Employees page cards. Linked to the
 * employee by EMPCODE (not id) so it survives HR's monthly re-import.
 */
class EmployeePhoto extends Model
{
    /** @use HasFactory<EmployeePhotoFactory> */
    use HasFactory;

    public const DISK = 'public';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empcode',
        'path',
        'uploaded_by',
    ];

    /**
     * Public URL of the stored picture. Every upload gets a new file name,
     * so a changed picture never shows a stale cached copy.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => self::publicUrl($this->path),
        );
    }

    /**
     * Host-relative URL ("/storage/..."), so the picture loads on whichever
     * address the app is opened with (localhost, LAN IP or domain) instead of
     * only on APP_URL's host.
     */
    public static function publicUrl(string $path): string
    {
        $url = Storage::disk(self::DISK)->url($path);
        $relativeUrl = parse_url($url, PHP_URL_PATH);

        return is_string($relativeUrl) && $relativeUrl !== '' ? $relativeUrl : $url;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
