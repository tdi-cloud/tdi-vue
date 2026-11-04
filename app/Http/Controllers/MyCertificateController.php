<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\DefaultProgramCover;
use App\Models\Participant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MyCertificateController extends Controller
{
    /**
     * "My Certificates" gallery: every certificate of the signed-in employee
     * (matched by empcode), newest first, with statistics for the collection.
     */
    public function index(Request $request): Response
    {
        $certificates = $this->certificatesFor($request->user());

        return Inertia::render('MyCertificates/Index', [
            'certificates' => $certificates->values()->all(),
            'stats' => $this->statistics($certificates),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function certificatesFor(User $user): Collection
    {
        if (empty($user->empcode)) {
            return collect();
        }

        // The My Progress page only opens for batches the employee is still enrolled in.
        $enrolledBatchIds = Participant::where('empcode', $user->empcode)->pluck('batch_id')->flip();
        $defaultCover = DefaultProgramCover::currentImagePath();

        return Certificate::with('batch.program.coverPage')
            ->where('empcode', $user->empcode)
            // Newest issue date first; certificates not yet dated go last.
            ->orderByRaw('issued_date IS NULL')
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Certificate $certificate) => $this->transform(
                $certificate,
                $enrolledBatchIds->has($certificate->batch_id),
                $defaultCover,
            ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Certificate $certificate, bool $isEnrolled, ?string $defaultCover): array
    {
        $batch = $certificate->batch;
        $program = $batch?->program;
        $coverPath = $program?->coverPage?->image ?? $defaultCover;

        return [
            'id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'type' => $certificate->type,
            'status' => $certificate->status,
            'issued_date' => $certificate->issued_date?->toDateString(),
            'year' => $certificate->issued_date?->year
                ?? ($batch?->date_start ? Carbon::parse($batch->date_start)->year : $certificate->created_at?->year),
            'hours' => (float) $certificate->hours,
            'file_url' => $certificate->file_path ? '/storage/'.$certificate->file_path : null,
            'file_name' => $certificate->file_name,
            'issued_by' => $certificate->issued_by,
            'remarks' => $certificate->remarks,
            'revoked_at' => $certificate->revoked_at?->toDateString(),
            'revoked_reason' => $certificate->revoked_reason,
            'batch_id' => $certificate->batch_id,
            'program_title' => $program?->title,
            'program_code' => $program?->program_code ?? $certificate->program_code,
            'program_type' => $program?->type,
            'batch_label' => $batch?->batch,
            'date_range' => $this->dateRange($batch?->date_start, $batch?->date_end),
            'venue' => $batch?->venue,
            'modality' => $batch?->modality,
            'cover_image' => $coverPath ? '/storage/'.$coverPath : null,
            'program_url' => $isEnrolled
                ? route('programs.my-progress', ['batch' => $certificate->batch_id, 'tab' => 'certificates'])
                : null,
        ];
    }

    /**
     * Revoked certificates stay in the collection but no longer count toward
     * training hours or completed programs.
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return array{total: int, hours: float, programs: int, by_type: array<string, int>, by_status: array<string, int>, by_year: array<int, int>, latest: array<string, mixed>|null}
     */
    protected function statistics(Collection $certificates): array
    {
        $valid = $certificates->where('status', '!=', 'Revoked');

        return [
            'total' => $certificates->count(),
            // A batch can carry several certificates (e.g. Participation and Completion)
            // that all record the same training hours, so each batch is counted once.
            'hours' => (float) $valid->groupBy('batch_id')->sum(fn (Collection $batchCertificates) => $batchCertificates->max('hours')),
            'programs' => $valid->pluck('program_code')->filter()->unique()->count(),
            'by_type' => collect(Certificate::TYPES)
                ->mapWithKeys(fn (string $type) => [$type => $certificates->where('type', $type)->count()])
                ->all(),
            'by_status' => collect(Certificate::STATUSES)
                ->mapWithKeys(fn (string $status) => [$status => $certificates->where('status', $status)->count()])
                ->all(),
            'by_year' => $certificates->countBy('year')->sortKeysDesc()->all(),
            // Already sorted newest first, so the first issued one is the latest.
            'latest' => $certificates->firstWhere('status', 'Issued'),
        ];
    }

    /**
     * Compact batch schedule, e.g. "Mar 4–6, 2026" or "Mar 28 – Apr 2, 2026".
     */
    protected function dateRange(?string $start, ?string $end): ?string
    {
        if (! $start) {
            return null;
        }

        $from = Carbon::parse($start);
        $to = $end ? Carbon::parse($end) : $from;

        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        if ($from->isSameMonth($to)) {
            return $from->format('M j').'–'.$to->format('j, Y');
        }

        if ($from->isSameYear($to)) {
            return $from->format('M j').' – '.$to->format('M j, Y');
        }

        return $from->format('M j, Y').' – '.$to->format('M j, Y');
    }
}
