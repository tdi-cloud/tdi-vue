<?php

namespace App\Http\Controllers;

use App\Models\SiteImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteImageController extends Controller
{
    public function index(): Response
    {
        $overrides = SiteImage::query()->pluck('path', 'key');

        $slots = collect(config('site-images'))
            ->map(function (array $slot, string $key) use ($overrides) {
                $path = $overrides->get($key);

                return [
                    'key' => $key,
                    'label' => $slot['label'],
                    'section' => $slot['section'],
                    'url' => $path ? Storage::disk('public')->url($path) : $slot['default'],
                    'is_customized' => $path !== null,
                ];
            })
            ->values()
            ->groupBy('section');

        return Inertia::render('SiteImages/index', [
            'sections' => $slots,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        if (! array_key_exists($key, config('site-images'))) {
            abort(404);
        }

        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $existing = SiteImage::where('key', $key)->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->path);
        }

        $path = $request->file('image')->store('site-images', 'public');

        SiteImage::updateOrCreate(['key' => $key], ['path' => $path]);

        return back()->with('success', 'Image updated.');
    }

    public function destroy(string $key): RedirectResponse
    {
        if (! array_key_exists($key, config('site-images'))) {
            abort(404);
        }

        $existing = SiteImage::where('key', $key)->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->path);
            $existing->delete();
        }

        return back()->with('success', 'Image reset to default.');
    }
}
