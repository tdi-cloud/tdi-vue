<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParticipantController extends Controller
{
    /**
     * Kunin ang lahat ng empcodes na naka-enroll na sa KAHIT ANONG batch
     * ng parehong program. Isang beses lang pwede maging participant
     * ang isang employee per program.
     */
    private function enrolledEmpcodesInProgram(Batch $batch)
    {
        $batchIds = Batch::where('program_code', $batch->program_code)->pluck('id');

        return Participant::whereIn('batch_id', $batchIds)->pluck('empcode');
    }

    /**
     * Burahin ang justification memo (file + record) kung meron.
     */
    private function deleteJustification(Participant $participant): void
    {
        if ($participant->justification) {
            Storage::disk('public')->delete($participant->justification->file_path);
            $participant->justification->delete();
        }
    }

    /**
     * Search ng employees na pwedeng i-add sa batch.
     *
     * GET /participants/search?q=juan&batch_id=5
     */
    public function search(Request $request)
    {
        $q       = $request->query('q');
        $batchId = $request->query('batch_id');

        $batch = Batch::find($batchId);

        $existing = $batch
            ? $this->enrolledEmpcodesInProgram($batch)
            : collect();

        $employees = Employee::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('LASTNAME', 'LIKE', "%{$q}%")
                      ->orWhere('FIRSTNAME', 'LIKE', "%{$q}%")
                      ->orWhere('EMPCODE', 'LIKE', "%{$q}%");
                });
            })
            ->whereNotIn('EMPCODE', $existing)
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get();

        return response()->json(
            $employees->map(fn ($e) => [
                'empcode' => $e->EMPCODE,
                'name'    => $e->name,
            ])->values()
        );
    }

    /**
     * Bulk add ng participants sa isang batch.
     * ✅ Default attendance: Pending, hours: 0
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_id'   => 'required|exists:batches,id',
            'empcodes'   => 'required|array|min:1',
            'empcodes.*' => 'required|string|exists:employees,EMPCODE',
        ]);

        $batch = Batch::findOrFail($request->batch_id);

        $enrolled = $this->enrolledEmpcodesInProgram($batch)->toArray();

        $sort = Participant::where('batch_id', $batch->id)->count();

        $added   = 0;
        $skipped = [];

        foreach ($request->empcodes as $empcode) {
            if (in_array($empcode, $enrolled)) {
                $skipped[] = $empcode;
                continue;
            }

            Participant::create([
                'sort_order' => ++$sort,
                'batch_id'   => $batch->id,
                'empcode'    => $empcode,
                'attendance' => 'Pending',
                'hours'      => 0,
                'added_by'   => auth()->user()->name ?? 'system',
            ]);

            $enrolled[] = $empcode;
            $added++;
        }

        if ($skipped) {
            $names = Employee::whereIn('EMPCODE', $skipped)
                ->get()
                ->map(fn ($e) => $e->name)
                ->implode(', ');

            $message = $added
                ? "{$added} participant(s) added. Skipped (already enrolled in another batch of this program): {$names}."
                : "No participants added. Already enrolled in another batch of this program: {$names}.";

            return back()->with($added ? 'success' : 'error', $message);
        }

        return back()->with('success', 'Participants added successfully.');
    }

    /**
     * Update ng attendance ng isang participant.
     *
     * Rules:
     *  - Pending  → hours = 0; buburahin ang memo kung meron
     *  - Complete → required ang hours; hindi pwedeng lumampas sa batch hours;
     *               buburahin ang memo kung meron
     *  - Absent   → required ang justification memo (file); hours = 0
     *
     * POST /participants/{participant}/attendance  (multipart/form-data)
     */
    public function updateAttendance(Request $request, Participant $participant)
    {
        $request->validate([
            'attendance'    => 'required|in:Pending,Complete,Absent',

            // I-validate lang ang hours kapag Complete; kapag hindi, huwag pansinin
            'hours'         => 'exclude_unless:attendance,Complete|required|numeric|min:0.5',

            // I-validate lang ang file kapag Absent; required lang kung wala pang naka-upload na memo
            'justification' => [
                'exclude_unless:attendance,Absent',
                $participant->justification ? 'nullable' : 'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:5120',
            ],
        ], [
            'hours.required'         => 'Please enter the completed hours.',
            'justification.required' => 'Please upload the justification memo for the absence.',
        ]);

        $batch    = $participant->batch;
        $maxHours = (float) ($batch->hours ?? 0);

        switch ($request->attendance) {

            case 'Pending':
                $this->deleteJustification($participant); // ✅ linisin ang memo
                $participant->update([
                    'attendance' => 'Pending',
                    'hours'      => 0,
                ]);
                break;

            case 'Complete':
                $hours = (float) $request->hours;

                if ($maxHours > 0 && $hours > $maxHours) {
                    return back()->withErrors([
                        'hours' => "Completed hours cannot exceed the batch total of {$maxHours} hour(s).",
                    ]);
                }

                $this->deleteJustification($participant); // ✅ linisin ang memo
                $participant->update([
                    'attendance' => 'Complete',
                    'hours'      => $hours,
                ]);
                break;

            case 'Absent':
                // Mag-save lang ng file kung may bagong in-upload
                if ($request->hasFile('justification')) {
                    $path = $request->file('justification')->store('justifications', 'public');

                    if ($participant->justification) {
                        Storage::disk('public')->delete($participant->justification->file_path);
                        $participant->justification->update(['file_path' => $path]);
                    } else {
                        $participant->justification()->create(['file_path' => $path]);
                    }
                }

                $participant->update([
                    'attendance' => 'Absent',
                    'hours'      => 0,
                ]);
                break;
        }

        return back()->with('success', 'Attendance updated.');
    }


    public function applyToAll(Request $request, Participant $participant)
    {
        $request->validate([
            'attendance' => 'required|in:Complete',
            'hours'      => 'required|numeric|min:0.5',
        ]);

        $batch    = $participant->batch;
        $maxHours = (float) ($batch->hours ?? 0);
        $hours    = (float) $request->hours;

        if ($maxHours > 0 && $hours > $maxHours) {
            return back()->withErrors([
                'hours' => "Completed hours cannot exceed the batch total of {$maxHours} hour(s).",
            ]);
        }

        Participant::where('batch_id', $batch->id)
            ->where('attendance', '!=', 'Absent')
            ->update([
                'attendance' => 'Complete',
                'hours'      => $hours,
            ]);

        return back()->with('success', 'Attendance applied to all eligible participants.');
    }

    public function destroy(Participant $participant)
    {
        $this->deleteJustification($participant);

        $participant->delete();

        return back()->with('success', 'Participant removed.');
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'empcodes' => 'required|string',
        ]);

        $batch = Batch::findOrFail($request->batch_id);

        // I-split sa newline, comma, semicolon, o space; alisin ang blanko
        $rawCodes = preg_split('/[\s,;]+/', $request->empcodes, -1, PREG_SPLIT_NO_EMPTY);

        // Alisin ang duplicates pero panatilihin ang order
        $codes = array_values(array_unique(array_map('trim', $rawCodes)));

        $enrolled = $this->enrolledEmpcodesInProgram($batch)->toArray();

        $sort = Participant::where('batch_id', $batch->id)->count();

        $results = [];

        foreach ($codes as $code) {
            $employee = Employee::where('EMPCODE', $code)->first();

            if (! $employee) {
                $results[] = [
                    'empcode' => $code,
                    'name'    => null,
                    'status'  => 'failed',
                    'reason'  => 'Employee code not found.',
                ];
                continue;
            }

            if (in_array($employee->EMPCODE, $enrolled)) {
                $results[] = [
                    'empcode' => $employee->EMPCODE,
                    'name'    => $employee->name,
                    'status'  => 'failed',
                    'reason'  => 'Already enrolled in another batch of this program.',
                ];
                continue;
            }

            Participant::create([
                'sort_order' => ++$sort,
                'batch_id'   => $batch->id,
                'empcode'    => $employee->EMPCODE,
                'attendance' => 'Pending',
                'hours'      => 0,
                'added_by'   => auth()->user()->name ?? 'system',
            ]);

            $enrolled[] = $employee->EMPCODE;

            $results[] = [
                'empcode' => $employee->EMPCODE,
                'name'    => $employee->name,
                'status'  => 'success',
                'reason'  => null,
            ];
        }

        $successCount = count(array_filter($results, fn ($r) => $r['status'] === 'success'));
        $failedCount  = count($results) - $successCount;

        return back()->with('bulkResult', [
            'results' => $results,
            'success' => $successCount,
            'failed'  => $failedCount,
        ]);
    }
}