<?php

namespace App\Http\Controllers;

use App\Models\CoverPage;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoverPageController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'image'      => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $cover = CoverPage::where('program_id', $request->program_id)->first();

        // Burahin ang lumang image kung meron
        if ($cover && $cover->image && Storage::disk('public')->exists($cover->image)) {
            Storage::disk('public')->delete($cover->image);
        }

        // I-store ang bagong image sa storage/app/public/cover_pages
        $path = $request->file('image')->store('cover_pages', 'public');

        CoverPage::updateOrCreate(
            ['program_id' => $request->program_id],
            ['image' => $path]
        );

        return back()->with('success', 'Cover page updated successfully.');
    }

    public function destroy(Program $program)
    {
        $cover = CoverPage::where('program_id', $program->id)->first();

        if ($cover) {
            if ($cover->image && Storage::disk('public')->exists($cover->image)) {
                Storage::disk('public')->delete($cover->image);
            }
            $cover->delete();
        }

        return back()->with('success', 'Cover page removed.');
    }
}