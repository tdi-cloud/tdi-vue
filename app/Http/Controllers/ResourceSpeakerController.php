<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ResourceSpeaker;
use App\Support\FlashMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceSpeakerController extends Controller
{
    public function store(Request $request, Program $program)
    {
        $validated = $this->validateSpeaker($request, $program);

        $speaker = $program->resourceSpeakers()->create(array_merge($validated, [
            'program_code' => $program->program_code,
        ]));

        return back()->with('success', FlashMessage::make('Resource speaker added', "{$speaker->name} is now listed for \"{$program->title}\"."));
    }

    public function update(Request $request, Program $program, ResourceSpeaker $resourceSpeaker)
    {
        abort_unless($resourceSpeaker->program_id === $program->id, 404);

        $validated = $this->validateSpeaker($request, $program);

        $resourceSpeaker->update($validated);

        return back()->with('success', FlashMessage::make('Resource speaker updated', "Your changes to {$resourceSpeaker->name} are saved."));
    }

    public function destroy(Program $program, ResourceSpeaker $resourceSpeaker)
    {
        abort_unless($resourceSpeaker->program_id === $program->id, 404);

        $resourceSpeaker->delete();

        return back()->with('success', FlashMessage::make('Resource speaker removed', "{$resourceSpeaker->name} is no longer listed for this program."));
    }

    /**
     * `batch_id`, when given, must belong to this same program — otherwise a
     * speaker could be scoped to a batch that has nothing to do with it.
     */
    private function validateSpeaker(Request $request, Program $program): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'batch_id' => [
                'nullable',
                Rule::exists('batches', 'id')->where('program_code', $program->program_code),
            ],
            'designation' => 'nullable|string|max:255',
            'affiliation' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'expertise' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:50',
            'date_engaged' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);
    }
}
