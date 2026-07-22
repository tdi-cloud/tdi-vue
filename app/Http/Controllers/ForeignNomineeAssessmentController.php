<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\ForeignProgramNhrdcSignature;
use App\Models\NhrdcMember;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ForeignNomineeAssessmentController extends Controller
{
    // GET /foreign-programs/{foreignProgram}/assessment
    public function index(ForeignProgram $foreignProgram): Response
    {
        ForeignNomineeAssessment::ensureDefaultsFor($foreignProgram->nominees()->pluck('id')->all());

        return Inertia::render('ForeignPrograms/Assessment', [
            'program' => $foreignProgram->load('sponsor'),
            'nominees' => $foreignProgram->nominees()
                ->with(['assessment', 'interviewRatings'])
                ->get(),
            'nhrdcSignatures' => ForeignProgramNhrdcSignature::where('foreign_program_id', $foreignProgram->id)
                ->whereNotNull('signed_copy_path')
                ->get(['nhrdc_empcode', 'uploaded_at'])
                ->keyBy('nhrdc_empcode'),
        ]);
    }

    // GET /foreign-nominee-assessments/search-employee
    // Used by the NHRDC roster management modal to find an employee to add.
    public function searchEmployee(Request $request)
    {
        $q = $request->query('q', '');

        $employees = Employee::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('LASTNAME', 'LIKE', "%{$q}%")
                        ->orWhere('FIRSTNAME', 'LIKE', "%{$q}%")
                        ->orWhere('EMPCODE', 'LIKE', "%{$q}%");
                });
            })
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get();

        return response()->json(
            $employees->map(fn ($e) => [
                'empcode' => $e->EMPCODE,
                'name' => $e->name,
                'position' => $e->POSITION,
            ])->values()
        );
    }

    // POST /foreign-nominees/{nominee}/assessment
    // Requirements section (max 70) — encoded by the admin/user.
    public function save(Request $request, ForeignNominee $nominee)
    {
        $rules = [];
        foreach (ForeignNomineeAssessment::REQUIREMENT_CRITERIA as $key => $max) {
            $rules[$key] = "required|numeric|min:0|max:{$max}|decimal:0,2";
        }

        $data = $request->validate($rules);
        $data['assessed_by'] = Auth::id();
        $data['assessed_at'] = now();

        $assessment = ForeignNomineeAssessment::updateOrCreate(
            ['foreign_nominee_id' => $nominee->id],
            $data,
        );

        return response()->json($assessment->fresh());
    }

    // POST /foreign-nominees/{nominee}/interview-ratings
    // Interview section (max 30) — every NHRDC panel member on the roster can
    // submit their own rating for a nominee; saving again updates their rating
    // rather than creating a duplicate.
    public function saveInterviewRating(Request $request, ForeignNominee $nominee)
    {
        $rules = [
            'nhrdc_empcode' => 'required|string|exists:nhrdc_members,empcode',
        ];
        foreach (ForeignNomineeInterviewRating::CRITERIA as $key => $max) {
            $rules[$key] = "required|numeric|min:0|max:{$max}|decimal:0,2";
        }

        $data = $request->validate($rules);

        $nhrdc = Employee::where('EMPCODE', $data['nhrdc_empcode'])->firstOrFail();
        $data['nhrdc_name'] = $nhrdc->name;
        $data['nhrdc_position'] = NhrdcMember::roleFor($data['nhrdc_empcode']);
        $data['rated_by'] = Auth::id();
        $data['rated_at'] = now();

        $rating = ForeignNomineeInterviewRating::updateOrCreate(
            ['foreign_nominee_id' => $nominee->id, 'nhrdc_empcode' => $data['nhrdc_empcode']],
            $data,
        );

        return response()->json($rating->fresh());
    }

    // DELETE /foreign-nominee-interview-ratings/{rating}
    public function destroyInterviewRating(ForeignNomineeInterviewRating $foreignNomineeInterviewRating)
    {
        $foreignNomineeInterviewRating->delete();

        return response()->json(['deleted' => true]);
    }

    // GET /foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/assessment-pdf
    // One Nominee Assessment Sheet per NHRDC member: every nominee under the
    // program is listed, but the Interview columns only reflect this specific
    // NHRDC's own rating (blank if they haven't rated a nominee yet).
    public function nhrdcPdf(ForeignProgram $foreignProgram, NhrdcMember $nhrdcMember)
    {
        ForeignNomineeAssessment::ensureDefaultsFor($foreignProgram->nominees()->pluck('id')->all());

        $nominees = $foreignProgram->nominees()
            ->with(['assessment', 'interviewRatings' => fn ($q) => $q->where('nhrdc_empcode', $nhrdcMember->empcode)])
            ->get()
            ->map(function (ForeignNominee $nominee) {
                $nominee->rating = $nominee->interviewRatings->first();

                return $nominee;
            });

        $sponsorFullName = $foreignProgram->sponsor?->full_name;
        $sponsorDisplay = $sponsorFullName
            ? "{$sponsorFullName} ({$foreignProgram->organizing_sponsor})"
            : $foreignProgram->organizing_sponsor;

        $pdf = Pdf::loadView('pdf.foreign-nominee-assessment', [
            'program' => $foreignProgram,
            'nominees' => $nominees,
            'sponsorDisplay' => $sponsorDisplay,
            'duration' => $this->formatDuration($foreignProgram->program_start, $foreignProgram->program_end),
            'slotsLabel' => $this->spellSlots($foreignProgram->slots),
            'signatoryName' => $nhrdcMember->employee?->name,
            'signatoryPosition' => $nhrdcMember->employee?->POSITION,
            'signatoryRole' => NhrdcMember::roleFor($nhrdcMember->empcode),
        ])->setPaper('a4', 'landscape');

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Nominee Assessment Sheet - '.$clean($foreignProgram->program_title)
            .' - '.$clean($nhrdcMember->employee?->name).'.pdf';

        return $pdf->stream($filename);
    }

    private function spellSlots(int $slots): string
    {
        $words = [
            1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        ];

        return ($words[$slots] ?? (string) $slots)." ({$slots})";
    }

    private function formatDuration($start, $end): string
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($start->year === $end->year) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' - '.$end->format('M j, Y');
    }

    // POST /foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy
    public function uploadNhrdcSignedCopy(Request $request, ForeignProgram $foreignProgram, NhrdcMember $nhrdcMember)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $signature = ForeignProgramNhrdcSignature::firstOrNew([
            'foreign_program_id' => $foreignProgram->id,
            'nhrdc_empcode' => $nhrdcMember->empcode,
        ]);

        if ($signature->signed_copy_path) {
            Storage::disk('local')->delete($signature->signed_copy_path);
        }

        $path = $request->file('file')->storeAs(
            "foreign-nhrdc-signatures/{$foreignProgram->id}",
            "{$nhrdcMember->empcode}.pdf",
            'local',
        );

        $signature->fill([
            'signed_copy_path' => $path,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
        ])->save();

        return response()->json([
            'nhrdc_empcode' => $signature->nhrdc_empcode,
            'uploaded_at' => $signature->uploaded_at,
        ]);
    }

    // GET /foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy
    public function downloadNhrdcSignedCopy(ForeignProgram $foreignProgram, NhrdcMember $nhrdcMember)
    {
        $signature = ForeignProgramNhrdcSignature::where('foreign_program_id', $foreignProgram->id)
            ->where('nhrdc_empcode', $nhrdcMember->empcode)
            ->first();

        abort_if(! $signature?->signed_copy_path || ! Storage::disk('local')->exists($signature->signed_copy_path), 404);

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Signed Nominee Assessment Sheet - '.$clean($foreignProgram->program_title)
            .' - '.$clean($nhrdcMember->employee?->name).'.pdf';

        return Storage::disk('local')->response($signature->signed_copy_path, $filename);
    }

    // DELETE /foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy
    public function destroyNhrdcSignedCopy(ForeignProgram $foreignProgram, NhrdcMember $nhrdcMember)
    {
        $signature = ForeignProgramNhrdcSignature::where('foreign_program_id', $foreignProgram->id)
            ->where('nhrdc_empcode', $nhrdcMember->empcode)
            ->first();

        abort_if(! $signature, 404);

        if ($signature->signed_copy_path) {
            Storage::disk('local')->delete($signature->signed_copy_path);
        }
        $signature->delete();

        return response()->json(['deleted' => true]);
    }
}
