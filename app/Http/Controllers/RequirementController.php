<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequirementController extends Controller
{
    /**
     * Mag-create ng requirement para sa LAHAT ng batches ng program.
     * Kapag 2 ang batch → 2 requirements (parehong title),
     * pero bawat isa ay may sariling due_date base sa date_end ng batch niya.
     */
    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', Rule::in(array_keys(Requirement::TYPES))],
            'is_required' => ['boolean'],
            'note'        => ['nullable', 'string', 'max:2000'],
        ]);

        $batches = $program->batches;

        if ($batches->isEmpty()) {
            return back()->withErrors([
                'title' => 'This program has no batches yet. Add a batch first before adding requirements.',
            ]);
        }

        foreach ($batches as $batch) {
            // Iwas duplicate kung meron na ang batch ng ganitong title
            $exists = Requirement::where('batch_id', $batch->id)
                ->where('title', $validated['title'])
                ->exists();

            if ($exists) {
                continue;
            }

            Requirement::create([
                'batch_id'    => $batch->id,
                'title'       => $validated['title'],
                'name'        => Requirement::nameFor($validated['title']),
                'due_date'    => Requirement::dueDateFor($validated['title'], $batch->date_end),
                'is_required' => $validated['is_required'] ?? true,
                'note'        => $validated['note'] ?? null,
            ]);
        }

        return back();
    }

    /**
     * Mag-delete ng isang requirement (per batch).
     */
    public function destroy(Program $program, Requirement $requirement)
    {
        // Siguraduhing sa program na ito talaga ang requirement
        abort_unless(
            $requirement->batch && $requirement->batch->program_code === $program->program_code,
            404
        );

        $requirement->delete();

        return back();
    }
}