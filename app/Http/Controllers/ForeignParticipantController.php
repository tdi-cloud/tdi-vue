<?php

namespace App\Http\Controllers;

use App\Models\ForeignParticipant;
use App\Models\ForeignProgram;
use App\Support\FlashMessage;
use Illuminate\Http\Request;

class ForeignParticipantController extends Controller
{
    public function store(Request $request, ForeignProgram $foreignProgram)
    {
        $data = $this->validateParticipant($request);
        $participant = $foreignProgram->participants()->create($data);

        return back()->with('success', FlashMessage::make('Participant added', "{$participant->name} is now a participant of \"{$foreignProgram->program_title}\"."));
    }

    public function update(Request $request, ForeignParticipant $foreignParticipant)
    {
        $data = $this->validateParticipant($request);
        $foreignParticipant->update($data);

        return back()->with('success', FlashMessage::make('Participant updated', "Your changes to {$foreignParticipant->name} are saved."));
    }

    public function destroy(ForeignParticipant $foreignParticipant)
    {
        $foreignParticipant->delete();

        return back()->with('success', FlashMessage::make('Participant removed', "{$foreignParticipant->name} is no longer listed."));
    }

    private function validateParticipant(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'sex' => 'required|in:male,female,other',
            'position' => 'required|string|max:255',
            'agency' => 'required|string|max:255',
            'contact_no' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'required|in:endorsed,waiting_result,not_endorsed,accepted,regret,cancelled',
        ]);
    }
}
