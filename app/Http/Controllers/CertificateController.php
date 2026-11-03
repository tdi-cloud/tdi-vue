<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Participant;
use App\Support\FlashMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'batch_id' => 'required|exists:batches,id',
            'program_code' => 'required|string',
            'type' => 'required|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'status' => 'nullable|in:Pending,Issued,Revoked',
            'issued_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $participant = Participant::findOrFail($validated['participant_id']);

        $cert = Certificate::firstOrNew([
            'participant_id' => $validated['participant_id'],
            'batch_id' => $validated['batch_id'],
            'type' => $validated['type'],
        ]);

        if (! $request->hasFile('file') && ! $cert->file_path) {
            return back()
                ->withErrors(['file' => 'A certificate PDF file is required.'])
                ->withInput();
        }

        if ($request->hasFile('file')) {
            if ($cert->file_path) {
                Storage::disk('public')->delete($cert->file_path);
            }
            $file = $request->file('file');
            $safeName = $this->sanitizeFilename($file->getClientOriginalName());
            $filename = time().'_'.$safeName;
            $path = $file->storeAs('certificates', $filename, 'public');

            $cert->file_path = $path;
            $cert->file_name = $safeName;
            $cert->uploaded_by = $request->user()->name ?? 'System';
        }

        $cert->program_code = $validated['program_code'];
        $cert->empcode = $participant->empcode;
        $cert->status = $validated['status'] ?? $cert->status ?? 'Pending';
        $cert->issued_date = $validated['issued_date'] ?? $cert->issued_date;
        $cert->issued_by = $validated['issued_by'] ?? $cert->issued_by;
        $cert->remarks = $validated['remarks'] ?? $cert->remarks;
        $cert->hours = $participant->hours ?? 0;

        $cert->save();

        return back()->with('success', FlashMessage::make(
            'Certificate saved',
            "{$participant->empcode}'s {$cert->type} certificate is saved as {$cert->status}.",
        ));
    }

    /**
     * Upload multiple certificate PDFs at once for a single batch + type.
     * Each file is already paired with its participant client-side (matched
     * by filename); this only validates and persists the pairing.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'program_code' => 'required|string',
            'type' => 'required|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'status' => 'nullable|in:Pending,Issued,Revoked',
            'issued_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:255',
            'files' => 'required|array|min:1',
            'files.*.participant_id' => 'required|distinct|exists:participants,id',
            'files.*.file' => 'required|file|mimes:pdf|max:5120',
        ]);

        $uploadedBy = $request->user()->name ?? 'System';
        $issuedCount = 0;
        $failedCount = 0;

        foreach ($validated['files'] as $index => $row) {
            $participant = Participant::where('id', $row['participant_id'])
                ->where('batch_id', $validated['batch_id'])
                ->first();

            if (! $participant) {
                $failedCount++;

                continue;
            }

            $cert = Certificate::firstOrNew([
                'participant_id' => $participant->id,
                'batch_id' => $validated['batch_id'],
                'type' => $validated['type'],
            ]);

            if ($cert->file_path) {
                Storage::disk('public')->delete($cert->file_path);
            }

            $file = $request->file("files.{$index}.file");
            $safeName = $this->sanitizeFilename($file->getClientOriginalName());
            $filename = time().'_'.$index.'_'.$safeName;
            $path = $file->storeAs('certificates', $filename, 'public');

            $cert->program_code = $validated['program_code'];
            $cert->empcode = $participant->empcode;
            $cert->file_path = $path;
            $cert->file_name = $safeName;
            $cert->uploaded_by = $uploadedBy;
            $cert->status = $validated['status'] ?? $cert->status ?? 'Pending';
            $cert->issued_date = $validated['issued_date'] ?? $cert->issued_date;
            $cert->issued_by = $validated['issued_by'] ?? $cert->issued_by;
            $cert->hours = $participant->hours ?? 0;
            $cert->save();

            $issuedCount++;
        }

        return back()->with('success', FlashMessage::make(
            "{$issuedCount} certificate(s) uploaded",
            $failedCount > 0
                ? "{$failedCount} file(s) could not be processed; upload those again one by one."
                : 'Every file was matched to its participant and saved.',
        ));
    }

    public function uploadByUser(Request $request, $batchId)
    {
        $request->validate([
            'type' => 'required|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        $user = $request->user();

        $participant = Participant::where('batch_id', $batchId)
            ->where('empcode', $user->empcode)
            ->firstOrFail();

        $cert = Certificate::firstOrNew([
            'participant_id' => $participant->id,
            'batch_id' => $batchId,
            'type' => $request->type,
        ]);

        if ($cert->file_path) {
            Storage::disk('public')->delete($cert->file_path);
        }

        $file = $request->file('file');
        $safeName = $this->sanitizeFilename($file->getClientOriginalName());
        $filename = time().'_'.$safeName;
        $path = $file->storeAs('certificates', $filename, 'public');

        $cert->program_code = $participant->batch->program_code;
        $cert->empcode = $participant->empcode;
        $cert->file_path = $path;
        $cert->file_name = $safeName;
        $cert->uploaded_by = $user->name;
        $cert->hours = $participant->hours ?? 0;
        $cert->status = $cert->status ?? 'Pending';

        $cert->save();

        return back()->with('success', FlashMessage::make('Certificate uploaded', 'Your certificate is saved. You can view or replace it anytime from this page.'));
    }

    /**
     * Let a logged-in employee upload several of their own certificates
     * (different types) for a batch in a single request, instead of
     * repeating the single-upload form once per type.
     */
    public function bulkUploadByUser(Request $request, $batchId)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1',
            'files.*.type' => 'required|distinct|in:Participation,Completion,Appearance,Appreciation,Recognition,Achievement',
            'files.*.file' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        $user = $request->user();

        $participant = Participant::where('batch_id', $batchId)
            ->where('empcode', $user->empcode)
            ->firstOrFail();

        $existingTypes = Certificate::where('participant_id', $participant->id)
            ->where('batch_id', $batchId)
            ->pluck('type')
            ->all();

        $uploadedCount = 0;

        foreach ($validated['files'] as $index => $row) {
            if (in_array($row['type'], $existingTypes, true)) {
                continue;
            }

            $file = $request->file("files.{$index}.file");
            $safeName = $this->sanitizeFilename($file->getClientOriginalName());
            $filename = time().'_'.$index.'_'.$safeName;
            $path = $file->storeAs('certificates', $filename, 'public');

            $cert = new Certificate([
                'participant_id' => $participant->id,
                'batch_id' => $batchId,
                'type' => $row['type'],
            ]);
            $cert->program_code = $participant->batch->program_code;
            $cert->empcode = $participant->empcode;
            $cert->file_path = $path;
            $cert->file_name = $safeName;
            $cert->uploaded_by = $user->name;
            $cert->hours = $participant->hours ?? 0;
            $cert->status = 'Pending';
            $cert->save();

            $existingTypes[] = $row['type'];
            $uploadedCount++;
        }

        return back()->with('success', FlashMessage::make("{$uploadedCount} certificate(s) uploaded", 'They are saved. You can view or replace them anytime from this page.'));
    }

    public function destroy(Certificate $certificate)
    {
        $certificate->delete(); // booted() handles file deletion

        return back()->with('success', FlashMessage::make('Certificate deleted', "The {$certificate->type} certificate of {$certificate->empcode} was removed."));
    }

    public function destroyByUser(Request $request, $batchId, Certificate $certificate)
    {
        $user = $request->user();

        abort_unless($certificate->empcode === $user->empcode, 403);

        $certificate->delete();

        return back()->with('success', FlashMessage::make('Certificate removed', 'You can upload it again anytime.'));
    }

    /**
     * Strip directory components and any character outside a safe allow-list
     * from a user-supplied filename before it's used to build a storage path.
     */
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($name));
    }
}
