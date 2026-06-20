<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
   
    public function store(Request $request)
    {
        $request->validate([
            'participant_id'  => 'required|exists:participants,id',
            'program_code'    => 'required',
            'batch_id'        => 'required|exists:batches,id',
            'requirement_id'  => 'required|exists:requirements,id',
            'status'          => 'nullable|in:Pending,Approved,Rejected',
            'notes'           => 'nullable|string',
            'remarks'         => 'nullable|string|max:2000',
            'file'            => 'nullable|mimes:pdf|max:20480',
        ]);

        // Hanapin kung may existing na submission para sa participant+requirement
        $submission = Submission::where('participant_id', $request->participant_id)
            ->where('requirement_id', $request->requirement_id)
            ->first();

        $path = $submission?->file_path;

        if ($request->hasFile('file')) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('submissions', $filename, 'public');
        }

        $data = [
            'participant_id'  => $request->participant_id,
            'program_code'    => $request->program_code,
            'batch_id'        => $request->batch_id,
            'requirement_id'  => $request->requirement_id,
            'status'          => $request->status ?? ($submission->status ?? 'Pending'),
            'file_path'       => $path,
            'notes'           => $request->notes ?? $submission?->notes ?? '',
            'remarks'         => $request->remarks ?? $submission?->remarks,
            'submitted_at'    => $path ? Carbon::now() : $submission?->submitted_at,
            'reviewed_at'     => $request->filled('status') ? Carbon::now() : $submission?->reviewed_at,
            'reviewed_by'     => $request->filled('status') ? ($request->user()->name ?? 'System') : $submission?->reviewed_by,
        ];

        if ($submission) {
            $submission->update($data);
        } else {
            $submission = Submission::create($data);
        }

        return back()->with('success', 'Submission saved successfully.');
    }

    /**
     * Review ng submission — Approve o Reject, may remarks.
     */
    public function review(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'status'  => 'required|in:Approved,Rejected',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $submission->update([
            'status'      => $validated['status'],
            'remarks'     => $validated['remarks'] ?? null,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $request->user()->name ?? 'System',
        ]);

        return back()->with('success', 'Submission reviewed successfully.');
    }

    /**
     * Delete ng submission, kasama ang file nito sa storage.
     */
    public function destroy(Submission $submission)
    {
        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();

        return back()->with('success', 'Submission deleted successfully.');
    }
}