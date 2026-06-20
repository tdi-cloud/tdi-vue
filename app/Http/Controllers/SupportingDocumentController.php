<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramSupportingDocument;
use Illuminate\Http\Request;

class SupportingDocumentController extends Controller
{
    /**
     * Store a new supporting document under a program.
     */
    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'document_type'   => 'required|string|max:255',
            'subject'         => 'required|string|max:255',
            'document_series' => 'required|integer|min:1900|max:2100',
            'origin'          => 'nullable|string|max:255',
            'document_number' => 'required|string|max:255',
            'date_issued'     => 'nullable|date',
            'link'            => 'nullable|string|max:2048|url',
        ]);

        $program->supportingDocuments()->create(array_merge($validated, [
            'program_code' => $program->program_code,
        ]));

        return back()->with('success', 'Supporting document added successfully.');
    }

    /**
     * Update an existing supporting document.
     */
    public function update(Request $request, Program $program, ProgramSupportingDocument $supportingDocument)
    {
        abort_unless($supportingDocument->program_id === $program->id, 404);

        $validated = $request->validate([
            'document_type'   => 'required|string|max:255',
            'subject'         => 'required|string|max:255',
            'document_series' => 'required|integer|min:1900|max:2100',
            'origin'          => 'nullable|string|max:255',
            'document_number' => 'required|string|max:255',
            'date_issued'     => 'nullable|date',
            'link'            => 'nullable|string|max:2048|url',
        ]);

        $supportingDocument->update($validated);

        return back()->with('success', 'Supporting document updated successfully.');
    }

    /**
     * Delete a supporting document.
     */
    public function destroy(Program $program, ProgramSupportingDocument $supportingDocument)
    {
        abort_unless($supportingDocument->program_id === $program->id, 404);

        $supportingDocument->delete();

        return back()->with('success', 'Supporting document deleted successfully.');
    }
}