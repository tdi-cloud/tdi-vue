<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Requirement;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Store a new batch under a program.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_code' => 'required|string|exists:programs,program_code',
            'batch'        => 'required|string|max:255',
            'status'       => 'required|string',
            'modality'     => 'required|string',
            'venue'        => 'nullable|string',
            'date_start'   => 'required|date',
            'date_end'     => 'required|date|after_or_equal:date_start',
            'time_start'   => 'required|string',
            'time_end'     => 'required|string',
            'days'         => 'required|string',
            'hours'        => 'required|string',
        ]);

        $sortOrder = Batch::where('program_code', $validated['program_code'])->count() + 1;

        $batch = Batch::create(array_merge($validated, [
            'sort_order' => $sortOrder,
        ]));

        // ✅ IDINAGDAG: Kopyahin ang requirements ng kapatid na batch (same program)
        // para hindi maiwanan ang bagong batch. Recomputed ang due_date
        // base sa date_end ng BAGONG batch.
        $this->copyRequirementsFromSibling($batch);

        return back()->with('success', 'Batch created successfully.');
    }

    /**
     * Update an existing batch.
     */
    public function update(Request $request, Batch $batch)
    {
        $request->validate([
            'batch'      => 'required|string|max:255',
            'status'     => 'required|string',
            'modality'   => 'required|string',
            'venue'      => 'nullable|string',
            'date_start' => 'required|date',
            'date_end'   => 'required|date|after_or_equal:date_start',
            'time_start' => 'required|string',
            'time_end'   => 'required|string',
            'days'       => 'required|string',
            'hours'      => 'required|string',
        ]);

        $batch->update($request->only([
            'batch', 'status', 'modality', 'venue',
            'date_start', 'date_end', 'time_start', 'time_end',
            'days', 'hours',
        ]));

        // ✅ IDINAGDAG: Kapag nagbago ang date_end (na-reschedule ang batch),
        // i-recompute ang due dates ng lahat ng requirements nito.
        if ($batch->wasChanged('date_end')) {
            foreach ($batch->requirements as $requirement) {
                $requirement->update([
                    'due_date' => Requirement::dueDateFor($requirement->title, $batch->date_end),
                ]);
            }
        }

        return back()->with('success', 'Batch updated successfully.');
    }

    /**
     * Delete a batch.
     * (Kusang mabubura ang requirements nito dahil sa cascadeOnDelete
     * sa requirements migration — walang extra code na kailangan.)
     */
    public function destroy(Batch $batch)
    {
        $batch->delete();

        return back()->with('success', 'Batch deleted successfully.');
    }

    /**
     * ✅ IDINAGDAG: Kopyahin ang requirements mula sa isang kapatid na batch
     * papunta sa bagong batch, na may recomputed due dates.
     */
    private function copyRequirementsFromSibling(Batch $batch): void
    {
        $sibling = Batch::where('program_code', $batch->program_code)
            ->where('id', '!=', $batch->id)
            ->whereHas('requirements')
            ->with('requirements')
            ->first();

        if (! $sibling) {
            return;
        }

        foreach ($sibling->requirements as $requirement) {
            Requirement::firstOrCreate(
                [
                    'batch_id' => $batch->id,
                    'title'    => $requirement->title,
                ],
                [
                    'name'        => $requirement->name,
                    'due_date'    => Requirement::dueDateFor($requirement->title, $batch->date_end),
                    'is_required' => $requirement->is_required,
                    'note'        => $requirement->note,
                ]
            );
        }
    }
}