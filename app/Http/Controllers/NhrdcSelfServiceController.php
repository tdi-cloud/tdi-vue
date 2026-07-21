<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\ForeignProgramNhrdcSignature;
use App\Models\NhrdcMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service area for NHRDC committee members: they log in with their own
 * account (gated by the `nhrdc` middleware, not `admin`) and can only ever
 * view/rate nominees and manage their own signed copy — never another
 * NHRDC member's rating, and never the Requirements section (admin-only).
 */
class NhrdcSelfServiceController extends Controller
{
    // GET /nhrdc/programs
    public function index(): Response
    {
        $empcode = Auth::user()->empcode;

        $programs = ForeignProgram::whereHas('nominees')
            ->withCount([
                'nominees',
                'nominees as rated_nominees_count' => function ($q) use ($empcode) {
                    $q->whereHas('interviewRatings', fn ($q2) => $q2->where('nhrdc_empcode', $empcode));
                },
            ])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Nhrdc/Programs', [
            'programs' => $programs,
        ]);
    }

    // GET /nhrdc/programs/{foreignProgram}
    public function show(ForeignProgram $foreignProgram): Response
    {
        $empcode = Auth::user()->empcode;

        ForeignNomineeAssessment::ensureDefaultsFor($foreignProgram->nominees()->pluck('id')->all());

        $nominees = $foreignProgram->nominees()
            ->with(['assessment', 'interviewRatings' => fn ($q) => $q->where('nhrdc_empcode', $empcode)])
            ->get()
            ->map(function (ForeignNominee $nominee) {
                $nominee->my_rating = $nominee->interviewRatings->first();

                return $nominee;
            });

        return Inertia::render('Nhrdc/Assessment', [
            'program' => $foreignProgram->load('sponsor'),
            'nominees' => $nominees,
            'hasSignedCopy' => ForeignProgramNhrdcSignature::where('foreign_program_id', $foreignProgram->id)
                ->where('nhrdc_empcode', $empcode)
                ->whereNotNull('signed_copy_path')
                ->exists(),
        ]);
    }

    // POST /nhrdc/nominees/{nominee}/rating
    // The rater is always the authenticated NHRDC user — never client-supplied.
    public function saveRating(Request $request, ForeignNominee $nominee)
    {
        $rules = [];
        foreach (ForeignNomineeInterviewRating::CRITERIA as $key => $max) {
            $rules[$key] = "required|integer|min:0|max:{$max}";
        }
        $data = $request->validate($rules);

        $empcode = Auth::user()->empcode;
        $employee = Employee::where('EMPCODE', $empcode)->firstOrFail();

        $data['nhrdc_empcode'] = $empcode;
        $data['nhrdc_name'] = $employee->name;
        $data['nhrdc_position'] = NhrdcMember::roleFor($empcode);
        $data['rated_by'] = Auth::id();
        $data['rated_at'] = now();

        $rating = ForeignNomineeInterviewRating::updateOrCreate(
            ['foreign_nominee_id' => $nominee->id, 'nhrdc_empcode' => $empcode],
            $data,
        );

        return response()->json($rating->fresh());
    }

    // GET /nhrdc/programs/{foreignProgram}/assessment-pdf
    public function pdf(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = NhrdcMember::where('empcode', Auth::user()->empcode)->firstOrFail();

        return app(ForeignNomineeAssessmentController::class)->nhrdcPdf($foreignProgram, $nhrdcMember);
    }

    // POST /nhrdc/programs/{foreignProgram}/signed-copy
    public function uploadSignedCopy(Request $request, ForeignProgram $foreignProgram)
    {
        $nhrdcMember = NhrdcMember::where('empcode', Auth::user()->empcode)->firstOrFail();

        return app(ForeignNomineeAssessmentController::class)->uploadNhrdcSignedCopy($request, $foreignProgram, $nhrdcMember);
    }

    // GET /nhrdc/programs/{foreignProgram}/signed-copy
    public function downloadSignedCopy(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = NhrdcMember::where('empcode', Auth::user()->empcode)->firstOrFail();

        return app(ForeignNomineeAssessmentController::class)->downloadNhrdcSignedCopy($foreignProgram, $nhrdcMember);
    }

    // DELETE /nhrdc/programs/{foreignProgram}/signed-copy
    public function destroySignedCopy(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = NhrdcMember::where('empcode', Auth::user()->empcode)->firstOrFail();

        return app(ForeignNomineeAssessmentController::class)->destroyNhrdcSignedCopy($foreignProgram, $nhrdcMember);
    }
}
