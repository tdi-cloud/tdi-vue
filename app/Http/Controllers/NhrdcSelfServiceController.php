<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\ForeignProgramNhrdcSignature;
use App\Models\NhrdcMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service area for NHRDC committee members: they log in with their own
 * account (gated by the `nhrdc` middleware, not `admin`) and can only ever
 * view/rate nominees and manage their own signed copy — never another
 * NHRDC member's rating, and never the Requirements section (admin-only).
 *
 * A representative assigned by the FSTP unit uses the same area to rate on
 * their NHRDC member's behalf (the rating is saved under the member). The
 * member stays the signatory: a representative may generate the member's
 * assessment sheet PDF, but the signed copy actions are reserved for the
 * member themselves.
 */
class NhrdcSelfServiceController extends Controller
{
    /**
     * Summary shown on the homepage banner for NHRDC members: how many
     * nominees (across all programs) they still need to rate. Returns null
     * when the user isn't on the NHRDC roster or has no nominees to rate yet,
     * so the banner stays hidden.
     *
     * @return array{pending: int, rated: int, total: int, representing: string|null}|null
     */
    public static function bannerData(?User $user): ?array
    {
        $member = $user?->actingNhrdcMember();

        if (! $member) {
            return null;
        }

        $empcode = $member->empcode;

        $programs = ForeignProgram::whereHas('nominees')
            ->withCount([
                'nominees',
                'nominees as rated_nominees_count' => function ($q) use ($empcode) {
                    $q->whereHas('interviewRatings', fn ($q2) => $q2->where('nhrdc_empcode', $empcode));
                },
            ])
            ->get();

        $total = (int) $programs->sum('nominees_count');

        if ($total === 0) {
            return null;
        }

        $rated = (int) $programs->sum('rated_nominees_count');

        return [
            'pending' => $total - $rated,
            'rated' => $rated,
            'total' => $total,
            'representing' => self::representingPayload($user, $member)['name'] ?? null,
        ];
    }

    // GET /nhrdc/programs
    public function index(): Response
    {
        $member = $this->actingMember();
        $empcode = $member->empcode;

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
            'representing' => self::representingPayload(Auth::user(), $member),
        ]);
    }

    // GET /nhrdc/programs/{foreignProgram}
    public function show(ForeignProgram $foreignProgram): Response
    {
        $member = $this->actingMember();
        $empcode = $member->empcode;

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
            'representing' => self::representingPayload(Auth::user(), $member),
            'hasSignedCopy' => ForeignProgramNhrdcSignature::where('foreign_program_id', $foreignProgram->id)
                ->where('nhrdc_empcode', $empcode)
                ->whereNotNull('signed_copy_path')
                ->exists(),
        ]);
    }

    // POST /nhrdc/nominees/{nominee}/rating
    // The NHRDC member is always derived from the authenticated user (their own
    // roster entry, or the member they represent) — never client-supplied.
    public function saveRating(Request $request, ForeignNominee $nominee)
    {
        $rules = [];
        foreach (ForeignNomineeInterviewRating::CRITERIA as $key => $max) {
            $rules[$key] = "required|numeric|min:0|max:{$max}|decimal:0,2";
        }
        $data = $request->validate($rules);

        $user = Auth::user();
        $member = $this->actingMember();
        $empcode = $member->empcode;
        $employee = Employee::where('EMPCODE', $empcode)->firstOrFail();
        $isRepresentative = $user->empcode !== $empcode;

        $data['nhrdc_empcode'] = $empcode;
        $data['nhrdc_name'] = $employee->name;
        $data['nhrdc_position'] = NhrdcMember::roleFor($empcode);
        $data['rated_by'] = $user->id;
        $data['rated_by_name'] = $isRepresentative
            ? (Employee::where('EMPCODE', $user->empcode)->first()?->name ?? $user->name)
            : $employee->name;
        $data['rated_by_role'] = $isRepresentative
            ? ForeignNomineeInterviewRating::RATED_BY_REPRESENTATIVE
            : ForeignNomineeInterviewRating::RATED_BY_NHRDC;
        $data['rated_at'] = now();

        $rating = ForeignNomineeInterviewRating::updateOrCreate(
            ['foreign_nominee_id' => $nominee->id, 'nhrdc_empcode' => $empcode],
            $data,
        );

        return response()->json($rating->fresh());
    }

    // GET /nhrdc/programs/{foreignProgram}/assessment-pdf
    // A representative may also generate it — the sheet is still the NHRDC
    // member's (their ratings and name as signatory), ready for them to sign.
    public function pdf(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = $this->actingMember();

        return app(ForeignNomineeAssessmentController::class)->nhrdcPdf($foreignProgram, $nhrdcMember);
    }

    // POST /nhrdc/programs/{foreignProgram}/signed-copy
    public function uploadSignedCopy(Request $request, ForeignProgram $foreignProgram)
    {
        $nhrdcMember = $this->signatoryMember();

        return app(ForeignNomineeAssessmentController::class)->uploadNhrdcSignedCopy($request, $foreignProgram, $nhrdcMember);
    }

    // GET /nhrdc/programs/{foreignProgram}/signed-copy
    public function downloadSignedCopy(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = $this->signatoryMember();

        return app(ForeignNomineeAssessmentController::class)->downloadNhrdcSignedCopy($foreignProgram, $nhrdcMember);
    }

    // DELETE /nhrdc/programs/{foreignProgram}/signed-copy
    public function destroySignedCopy(ForeignProgram $foreignProgram)
    {
        $nhrdcMember = $this->signatoryMember();

        return app(ForeignNomineeAssessmentController::class)->destroyNhrdcSignedCopy($foreignProgram, $nhrdcMember);
    }

    /**
     * The NHRDC member the authenticated user rates as (own roster entry or the
     * member they represent). The `nhrdc` middleware guarantees one exists.
     */
    private function actingMember(): NhrdcMember
    {
        return Auth::user()->actingNhrdcMember() ?? abort(403);
    }

    /**
     * The signed copy belongs to the NHRDC member as signatory — a
     * representative may rate and generate the sheet, but never upload it.
     */
    private function signatoryMember(): NhrdcMember
    {
        return NhrdcMember::where('empcode', Auth::user()->empcode)->first()
            ?? abort(403, 'Only the NHRDC member can upload or manage the signed assessment sheet.');
    }

    /**
     * When the user is a representative, who they are rating on behalf of.
     *
     * @return array{name: string|null, role: string}|null
     */
    private static function representingPayload(User $user, NhrdcMember $member): ?array
    {
        if ($user->empcode === $member->empcode) {
            return null;
        }

        return [
            'name' => $member->employee?->name,
            'role' => NhrdcMember::roleFor($member->empcode),
        ];
    }
}
