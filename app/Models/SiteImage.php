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
}
