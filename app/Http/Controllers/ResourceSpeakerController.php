<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ResourceSpeaker;
use Illuminate\Http\Request;

class ResourceSpeakerController extends Controller
{
    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'designation'     => 'nullable|string|max:255',
            'affiliation'     => 'nullable|string|max:255',
            'topic'           => 'nullable|string|max:255',
            'expertise'       => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'contact_number'  => 'nullable|string|max:50',
            'date_engaged'    => 'nullable|date',
            'remarks'         => 'nullable|string',
        ]);

        $program->resourceSpeakers()->create(array_merge($validated, [
            'program_code' => $program->program_code,
        ]));

        return back()->with('success', 'Resource speaker added successfully.');
    }

    public function update(Request $request, Program $program, ResourceSpeaker $resourceSpeaker)
    {
        abort_unless($resourceSpeaker->program_id === $program->id, 404);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'designation'     => 'nullable|string|max:255',
            'affiliation'     => 'nullable|string|max:255',
            'topic'           => 'nullable|string|max:255',
            'expertise'       => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'contact_number'  => 'nullable|string|max:50',
            'date_engaged'    => 'nullable|date',
            'remarks'         => 'nullable|string',
        ]);

        $resourceSpeaker->update($validated);

        return back()->with('success', 'Resource speaker updated successfully.');
    }

    public function destroy(Program $program, ResourceSpeaker $resourceSpeaker)
    {
        abort_unless($resourceSpeaker->program_id === $program->id, 404);

        $resourceSpeaker->delete();

        return back()->with('success', 'Resource speaker removed successfully.');
    }
}