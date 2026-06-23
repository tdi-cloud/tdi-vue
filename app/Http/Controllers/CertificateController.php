<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /**
     * Store or update a certificate for a participant.
     * Used by ADMIN from the batch/program management side.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'participant_id'   => 'required|exists:participants,id',
            'batch_id'         => 'required|exists:batches,id',
            'program_code'     => 'required|string',
            'type'             => 'required|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'status'           => 'nullable|in:Pending,Issued,Revoked',
            'issued_date'      => 'nullable|date',
            'issued_by'        => 'nullable|string|max:255',
            'remarks'          => 'nullable|string|max:1000',
            'file'             => 'nullable|file|mimes:pdf|max:5120', // 5MB max
        ]);

        $participant = Participant::findOrFail($validated['participant_id']);

        // Find or create certificate for this participant + batch + type
        $cert = Certificate::firstOrNew([
            'participant_id' => $validated['participant_id'],
            'batch_id'       => $validated['batch_id'],
            'type'           => $validated['type'],
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($cert->file_path) {
                Storage::disk('public')->delete($cert->file_path);
            }
            $file     = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path     = $file->storeAs('certificates', $filename, 'public');

            $cert->file_path  = $path;
            $cert->file_name  = $file->getClientOriginalName();
            $cert->uploaded_by = $request->user()->name ?? 'System';
        }

        $cert->program_code  = $validated['program_code'];
        $cert->empcode       = $participant->empcode;
        $cert->status        = $validated['status'] ?? $cert->status ?? 'Pending';
        $cert->issued_date   = $validated['issued_date'] ?? $cert->issued_date;
        $cert->issued_by     = $validated['issued_by'] ?? $cert->issued_by;
        $cert->remarks       = $validated['remarks'] ?? $cert->remarks;
        $cert->hours         = $participant->hours ?? 0;

        $cert->save();

        return back()->with('success', 'Certificate saved successfully.');
    }

    /**
     * Upload a certificate by the participant themselves.
     * Used from the enrolled program / my-progress page.
     */
    public function uploadByUser(Request $request, $batchId)
    {
        $request->validate([
            'type' => 'required|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        $user = $request->user();

        // Find the participant record for this user in this batch
        $participant = Participant::where('batch_id', $batchId)
            ->where('empcode', $user->empcode)
            ->firstOrFail();

        $cert = Certificate::firstOrNew([
            'participant_id' => $participant->id,
            'batch_id'       => $batchId,
            'type'           => $request->type,
        ]);

        // Delete old file
        if ($cert->file_path) {
            Storage::disk('public')->delete($cert->file_path);
        }

        $file     = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path     = $file->storeAs('certificates', $filename, 'public');

        $cert->program_code  = $participant->batch->program_code;
        $cert->empcode       = $participant->empcode;
        $cert->file_path     = $path;
        $cert->file_name     = $file->getClientOriginalName();
        $cert->uploaded_by   = $user->name;
        $cert->hours         = $participant->hours ?? 0;
        $cert->status        = $cert->status ?? 'Pending';

        $cert->save();

        return back()->with('success', 'Certificate uploaded successfully.');
    }

    /**
     * Delete a certificate and its file.
     */
    public function destroy(Certificate $certificate)
    {
        $certificate->delete(); // booted() handles file deletion
        return back()->with('success', 'Certificate deleted successfully.');
    }

    /**
     * Delete a certificate by the user themselves (from my-progress page).
     */
    public function destroyByUser(Request $request, $batchId, Certificate $certificate)
    {
        $user = $request->user();

        // Make sure this cert belongs to the user
        abort_unless($certificate->empcode === $user->empcode, 403);

        $certificate->delete();

        return back()->with('success', 'Certificate removed successfully.');
    }
}