<?php

namespace App\Http\Controllers;

use App\Models\DefaultProgramCover;
use App\Support\FlashMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DefaultProgramCoverController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $default = DefaultProgramCover::first();

        // Burahin ang lumang default image kung meron
        if ($default && $default->image && Storage::disk('public')->exists($default->image)) {
            Storage::disk('public')->delete($default->image);
        }

        $path = $request->file('image')->store('cover_pages', 'public');

        if ($default) {
            $default->update(['image' => $path]);
        } else {
            DefaultProgramCover::create(['image' => $path]);
        }

        return back()->with('success', FlashMessage::make('Default cover updated', 'Programs without their own cover page now show this image.'));
    }

    public function destroy()
    {
        $default = DefaultProgramCover::first();

        if ($default) {
            if ($default->image && Storage::disk('public')->exists($default->image)) {
                Storage::disk('public')->delete($default->image);
            }
            $default->delete();
        }

        return back()->with('success', FlashMessage::make('Default cover removed', 'Programs without their own cover page now show the built-in placeholder.'));
    }
}
