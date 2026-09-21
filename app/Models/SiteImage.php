<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteImage extends Model
{
    protected $fillable = ['key', 'path'];

    /**
     * Resolved key => URL map ng lahat ng customizable na homepage image slots
     * (tingnan ang config/site-images.php). Gagamitin ang na-upload na override
     * kung meron, kung wala ay babalik sa default na larawan.
     *
     * @return array<string, string>
     */
    public static function resolvedUrls(): array
    {
        $overrides = static::query()->pluck('path', 'key');

        return collect(config('site-images'))
            ->map(function (array $slot, string $key) use ($overrides) {
                $path = $overrides->get($key);

                return $path ? Storage::disk('public')->url($path) : $slot['default'];
            })
            ->all();
    }

    /**
     * Resolved URL ng iisang image slot lang (hal. 'auth_background'), para
     * hindi na kailangang mag-load ng buong resolvedUrls() map sa mga pages
     * na iisang slot lang ang kailangan.
     */
    public static function urlFor(string $key): ?string
    {
        $path = static::query()->where('key', $key)->value('path');

        return $path ? Storage::disk('public')->url($path) : config("site-images.{$key}.default");
    }
}
