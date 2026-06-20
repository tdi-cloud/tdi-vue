<?php

namespace App\Http\Controllers;

use App\Models\ForeignParticipant;
use App\Models\ForeignProgram;
use Illuminate\Http\Request;

class ForeignParticipantController extends Controller
{
    public function store(Request $request, ForeignProgram $foreignProgram)
    {
        $data = $this->validateParticipant($request);
        $foreignProgram->participants()->create($data);

        return back()->with('success', 'Participant added successfully.');
    }

    public function update(Request $request, ForeignParticipant $foreignParticipant)
    {
        $data = $this->validateParticipant($request);
        $foreignParticipant->update($data);

        return back()->with('success', 'Participant updated successfully.');
    }

    public function destroy(ForeignParticipant $foreignParticipant)
    {
        $foreignParticipant->delete();

        return back()->with('success', 'Participant removed successfully.');
    }

    private function validateParticipant(Request $request): array
    {
        return $request->validate([
            'name'       => 'required|string|max:255',
            'sex'        => 'required|in:male,female,other',
            'position'   => 'required|string|max:255',
            'agency'     => 'required|string|max:255',
            'contact_no' => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'status'     => 'required|in:endorsed,waiting_result,not_endorsed,accepted,regret,cancelled',
        ]);
    }
}