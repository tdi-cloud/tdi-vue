<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TnaController extends Controller
{
    /**
     * Data para sa TNA banner sa welcome/index page.
     * Nagbabalik ng null kung: hindi naka-login, walang employee record,
     * o walang TNA Tool ang position -> hindi lalabas ang banner.
     */
    public static function bannerData($user): ?array
    {
        if (! $user || empty($user->empcode)) {
            return null;
        }

        $employee = Employee::where('EMPCODE', $user->empcode)->first();
        if (! $employee) {
            return null;
        }

        $hasTool = Competency::forPosition($employee->POSITION)->exists();
        if (! $hasTool) {
            return null;
        }

        $period = config('tna.period');

        $assessment = TnaAssessment::where('user_id', $user->id)
            ->where('period', $period)
            ->where('position', $employee->POSITION)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        return [
            'period'        => $period,
            'position'      => $employee->POSITION,
            'submitted'     => (bool) $assessment,
            'assessment_id' => $assessment?->id,
            'reviewed'      => $assessment?->supervisor_reviewed_at !== null,
        ];
    }

    /**
     * Data para sa SUPERVISOR banner sa homepage.
     * Lalabas lang kung ang naka-login ay may pending na ira-rate.
     */
    public static function supervisorBannerData($user): ?array
    {
        if (! $user || empty($user->empcode)) {
            return null;
        }

        $pending = TnaAssessment::where('supervisor_empcode', $user->empcode)
            ->whereNotNull('submitted_at')
            ->whereNull('supervisor_reviewed_at')
            ->count();

        if ($pending === 0) {
            return null;
        }

        return ['pending' => $pending];
    }

    /**
     * Listahan ng mga subordinate na pumili sa naka-login na supervisor.
     * Pending muna, tapos ang na-review na.
     */
    public function supervisoryIndex()
    {
        $user = auth()->user();
        abort_if(empty($user->empcode), 403);

        $assessments = TnaAssessment::where('supervisor_empcode', $user->empcode)
            ->whereNotNull('submitted_at')
            ->orderByRaw('supervisor_reviewed_at IS NULL DESC')
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'position'     => $a->position,
                'office'       => $a->office,
                'division'     => $a->division,
                'period'       => $a->period,
                'submitted_at' => $a->submitted_at?->format('M d, Y g:i A'),
                'reviewed'     => $a->supervisor_reviewed_at !== null,
                'reviewed_at'  => $a->supervisor_reviewed_at?->format('M d, Y g:i A'),
            ]);

        return Inertia::render('Tna/SupervisoryIndex', [
            'assessments' => $assessments,
            'pendingCount' => $assessments->where('reviewed', false)->count(),
        ]);
    }

    /**
     * Ipakita ang supervisory rating form para sa isang subordinate.
     * Tanging ang napiling supervisor ang makaka-access.
     */
    public function supervisoryShow(TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );

        if ($assessment->supervisor_reviewed_at !== null) {
            return redirect()
                ->route('tna.supervisory.index')
                ->with('success', 'You have already rated this assessment.');
        }

        $supEmployee = Employee::where('EMPCODE', $user->empcode)->first();

        $ratings = $assessment->ratings()->with('competency')->get()
            ->filter(fn ($r) => $r->competency)
            ->sortBy(fn ($r) => $r->competency->sort_order)
            ->values();

        $toUnits = fn (string $type) => $ratings
            ->filter(fn ($r) => $r->competency->type === $type)
            ->groupBy(fn ($r) => $r->competency->unit)
            ->map(fn ($group, $unit) => [
                'unit'     => $unit,
                'elements' => $group->map(fn ($r) => [
                    'competency_id' => $r->competency_id,
                    'element'       => $r->competency->element,
                    'self'          => [
                        'criticality' => $r->criticality,
                        'competence'  => $r->competence,
                        'frequency'   => $r->frequency,
                    ],
                ])->values(),
            ])->values();

        return Inertia::render('Tna/SupervisoryRating', [
            'assessment' => [
                'id'                   => $assessment->id,
                'period'               => $assessment->period,
                'subordinate_name'     => $assessment->name,
                'subordinate_position' => $assessment->position,
            ],
            'supervisor' => [
                'name'     => $supEmployee ? $this->fullName($supEmployee) : $assessment->supervisor_name,
                'office'   => $supEmployee?->OFFICE,
                'division' => $supEmployee?->SECTION,
            ],
            'coreUnits'     => $toUnits('core'),
            'electiveUnits' => $toUnits('elective'),
        ]);
    }

    /**
     * I-save ang supervisory rating; ise-set ang supervisor_reviewed_at
     * (dito naglo-lock ang self-rating ng empleyado).
     */
    public function supervisoryStore(Request $request, TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );

        if ($assessment->supervisor_reviewed_at !== null) {
            throw ValidationException::withMessages([
                'ratings' => 'You have already rated this assessment.',
            ]);
        }

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'office'                  => ['nullable', 'string', 'max:255'],
            'division'                => ['nullable', 'string', 'max:255'],
            'subordinate_name'        => ['required', 'string', 'max:255'],
            'subordinate_position'    => ['required', 'string', 'max:255'],
            'ratings'                 => ['required', 'array', 'min:1'],
            'ratings.*.competency_id' => ['required', 'integer'],
            'ratings.*.criticality'   => ['required', 'integer', 'between:1,3'],
            'ratings.*.competence'    => ['required', 'integer', 'between:0,4'],
            'ratings.*.frequency'     => ['required', 'integer', 'between:1,3'],
        ]);

        $ratingRows = $assessment->ratings()->get()->keyBy('competency_id');
        $submitted  = collect($data['ratings'])->keyBy('competency_id');

        // Kompleto dapat ang lahat ng element na sinagot ng empleyado
        $incomplete = $ratingRows->keys()->first(fn ($cid) => ! $submitted->has($cid));
        if ($incomplete !== null) {
            throw ValidationException::withMessages([
                'ratings' => 'Please complete all competencies before submitting.',
            ]);
        }

        DB::transaction(function () use ($assessment, $data, $ratingRows, $submitted) {
            foreach ($ratingRows as $cid => $row) {
                $r = $submitted->get($cid);
                if (! $r) {
                    continue;
                }
                $row->update([
                    'sup_criticality' => $r['criticality'],
                    'sup_competence'  => $r['competence'],
                    'sup_frequency'   => $r['frequency'],
                ]);
            }

            $assessment->update([
                'supervisor_form' => [
                    'name'                 => $data['name'],
                    'office'               => $data['office'] ?? null,
                    'division'             => $data['division'] ?? null,
                    'subordinate_name'     => $data['subordinate_name'],
                    'subordinate_position' => $data['subordinate_position'],
                ],
                'supervisor_reviewed_at' => now(),
            ]);
        });

        return redirect()
            ->route('tna.supervisory.index')
            ->with('success', 'Supervisory rating submitted. Thank you!');
    }

    /**
     * PDF ng natapos na supervisory rating (ISO form).
     * Supervisor-owner lang, at kung na-rate na.
     */
    public function supervisoryPdf(TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );
        abort_if($assessment->supervisor_reviewed_at === null, 404);

        $ratings = $assessment->ratings()->with('competency')->get()
            ->filter(fn ($r) => $r->competency)
            ->sortBy(fn ($r) => $r->competency->sort_order)
            ->values();

        $build = fn (string $type) => $ratings
            ->filter(fn ($r) => $r->competency->type === $type)
            ->groupBy(fn ($r) => $r->competency->unit)
            ->map(fn ($group, $unit) => [
                'unit' => $unit,
                'rows' => $group->map(fn ($r) => [
                    'element'     => $r->competency->element,
                    'criticality' => $r->sup_criticality,
                    'competence'  => $r->sup_competence,
                    'frequency'   => $r->sup_frequency,
                ])->values(),
            ])->values();

        $form = $assessment->supervisor_form ?? [];

        $pdf = Pdf::loadView('pdf.supervisory-rating', [
            'a'        => $assessment,
            'form'     => $form,
            'core'     => $build('core'),
            'elective' => $build('elective'),
        ])->setPaper('a4', 'portrait');

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Supervisory-Rating - ' . $clean($form['subordinate_name'] ?? $assessment->name)
            . ' - ' . $clean($form['subordinate_position'] ?? $assessment->position)
            . ' - ' . $clean($assessment->period) . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Ipakita ang self-rating form ng naka-login na employee.
     */
    public function selfRating()
    {
        $user     = auth()->user();
        $employee = $this->resolveEmployee($user);

        if (! $employee) {
            return Inertia::render('Tna/NoTool', [
                'reason'   => 'no_employee',
                'position' => null,
            ]);
        }

        $position = $employee->POSITION;

        $competencies = Competency::forPosition($position)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($competencies->isEmpty()) {
            return Inertia::render('Tna/NoTool', [
                'reason'   => 'no_competencies',
                'position' => $position,
            ]);
        }

        $period = config('tna.period');

        // Naisumite na ba para sa position na ito sa period na ito?
        $existing = TnaAssessment::where('user_id', $user->id)
            ->where('period', $period)
            ->where('position', $position)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        $toUnits = fn ($items) => $items
            ->groupBy('unit')
            ->map(fn ($group, $unit) => [
                'unit'     => $unit,
                'elements' => $group->map(fn ($c) => [
                    'id'      => $c->id,
                    'element' => $c->element,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Tna/SelfRating', [
            'period'   => $period,
            'employee' => [
                'name'     => $this->fullName($employee),
                'office'   => $employee->OFFICE,
                'division' => $employee->SECTION,
                'position' => $position,
            ],
            'coreUnits'     => $toUnits($competencies->where('type', 'core')),
            'electiveUnits' => $toUnits($competencies->where('type', 'elective')),
            'alreadySubmitted' => $existing ? [
                'id'              => $existing->id,
                'submitted_at'    => $existing->submitted_at->format('M d, Y g:i A'),
                'reviewed'        => $existing->supervisor_reviewed_at !== null,
                'supervisor_name' => $existing->supervisor_name,
            ] : null,
        ]);
    }

    /**
     * Search ng supervisor mula sa employees table (JSON, para sa autocomplete).
     */
    public function searchSupervisor(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $me = auth()->user();

        $results = Employee::query()
            ->where(function ($query) use ($q) {
                $query->where('FIRSTNAME', 'like', "%{$q}%")
                    ->orWhere('LASTNAME', 'like', "%{$q}%")
                    ->orWhere('EMPCODE', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(FIRSTNAME, ' ', LASTNAME) LIKE ?", ["%{$q}%"]);
            })
            // huwag isama ang sarili
            ->when($me?->empcode, fn ($qq) => $qq->where('EMPCODE', '!=', $me->empcode))
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'empcode'  => $e->EMPCODE,
                'name'     => $this->fullName($e),
                'position' => $e->POSITION,
                'office'   => $e->{'OFFICE/DIVISION'},
            ]);

        return response()->json($results);
    }

    /**
     * I-save ang isinumiteng self-rating.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'office'                  => ['nullable', 'string', 'max:255'],
            'division'                => ['nullable', 'string', 'max:255'],
            'designation'             => ['nullable', 'string', 'max:255'],
            'supervisor_empcode'      => ['required', 'string', 'max:255'],
            'supervisor_name'         => ['required', 'string', 'max:255'],
            'supervisor_position'     => ['nullable', 'string', 'max:255'],
            'signature'               => ['required', 'string', 'max:255'],
            'ratings'                 => ['required', 'array', 'min:1'],
            'ratings.*.competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'ratings.*.criticality'   => ['nullable', 'integer', 'between:1,3'],
            'ratings.*.competence'    => ['nullable', 'integer', 'between:0,4'],
            'ratings.*.frequency'     => ['nullable', 'integer', 'between:1,3'],
        ], [
            'supervisor_empcode.required' => 'Please select a supervisor before submitting.',
        ]);

        $user     = auth()->user();
        $employee = $this->resolveEmployee($user);
        abort_if(! $employee, 403, 'No employee record found.');

        $position = $employee->POSITION;
        $period   = config('tna.period');

        // Iwas doble-submit para sa parehong position sa parehong period
        $dup = TnaAssessment::where('user_id', $user->id)
            ->where('period', $period)
            ->where('position', $position)
            ->whereNotNull('submitted_at')
            ->exists();

        if ($dup) {
            throw ValidationException::withMessages([
                'ratings' => "You have already submitted your self-rating for this position in {$period}.",
            ]);
        }

        $competencies = Competency::forPosition($position)->get()->keyBy('id');

        // All competencies (core + elective) must be completed
        $requiredIds = $competencies->keys();
        $submitted   = collect($data['ratings'])->keyBy('competency_id');
        $incomplete  = $requiredIds->first(function ($id) use ($submitted) {
            $r = $submitted->get($id);
            return ! $r
                || is_null($r['criticality'] ?? null)
                || is_null($r['competence'] ?? null)
                || is_null($r['frequency'] ?? null);
        });

        if ($incomplete !== null) {
            throw ValidationException::withMessages([
                'ratings' => 'Please complete all competencies before submitting.',
            ]);
        }

        DB::transaction(function () use ($data, $user, $position, $period, $competencies) {
            $assessment = TnaAssessment::create([
                'user_id'             => $user->id,
                'position'            => $position,
                'period'              => $period,
                'name'                => $data['name'],
                'office'              => $data['office'] ?? null,
                'division'            => $data['division'] ?? null,
                'designation'         => $data['designation'] ?? null,
                'supervisor_empcode'  => $data['supervisor_empcode'],
                'supervisor_name'     => $data['supervisor_name'],
                'supervisor_position' => $data['supervisor_position'] ?? null,
                'signature'           => $data['signature'],
                'submitted_at'        => now(),
            ]);

            foreach ($data['ratings'] as $r) {
                if (! $competencies->has($r['competency_id'])) {
                    continue;
                }

                $hasAnswer = ! is_null($r['criticality'] ?? null)
                    || ! is_null($r['competence'] ?? null)
                    || ! is_null($r['frequency'] ?? null);

                if (! $hasAnswer) {
                    continue; // laktawan ang hindi ginalaw na elective
                }

                $assessment->ratings()->create([
                    'competency_id' => $r['competency_id'],
                    'criticality'   => $r['criticality'] ?? null,
                    'competence'    => $r['competence'] ?? null,
                    'frequency'     => $r['frequency'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('tna.self-rating')
            ->with('success', 'Your self-rating has been submitted. Thank you!');
    }

    /**
     * I-generate ang isinumiteng self-rating bilang PDF (ISO form).
     * Owner lang ang makaka-view.
     */
    public function pdf(TnaAssessment $assessment)
    {
        abort_unless($assessment->user_id === auth()->id(), 403);

        $ratings = $assessment->ratings()->with('competency')->get()
            ->filter(fn ($r) => $r->competency)
            ->sortBy(fn ($r) => $r->competency->sort_order)
            ->values();

        $build = fn (string $type) => $ratings
            ->filter(fn ($r) => $r->competency->type === $type)
            ->groupBy(fn ($r) => $r->competency->unit)
            ->map(fn ($group, $unit) => [
                'unit' => $unit,
                'rows' => $group->map(fn ($r) => [
                    'element'     => $r->competency->element,
                    'criticality' => $r->criticality,
                    'competence'  => $r->competence,
                    'frequency'   => $r->frequency,
                ])->values(),
            ])->values();

        $pdf = Pdf::loadView('pdf.self-rating', [
            'a'        => $assessment,
            'core'     => $build('core'),
            'elective' => $build('elective'),
        ])->setPaper('a4', 'portrait');

        // Formal, nakapangalang filename: nag-self-rate + position + period
        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Self-Rating - ' . $clean($assessment->name)
            . ' - ' . $clean($assessment->position)
            . ' - ' . $clean($assessment->period) . '.pdf';

        // stream() = bukas sa browser (pwedeng i-print o i-save)
        return $pdf->stream($filename);
    }

    /**
     * Palitan LANG ang napiling supervisor (hindi hinahawakan ang ratings).
     * Pinapayagan lang habang hindi pa ni-rate ng supervisor. Owner-only.
     */
    public function updateSupervisor(Request $request, TnaAssessment $assessment)
    {
        abort_unless($assessment->user_id === auth()->id(), 403);

        if ($assessment->supervisor_reviewed_at !== null) {
            return back()->withErrors([
                'supervisor_empcode' => 'Cannot change — this has already been reviewed by your supervisor.',
            ]);
        }

        $data = $request->validate([
            'supervisor_empcode'  => ['required', 'string', 'max:255'],
            'supervisor_name'     => ['required', 'string', 'max:255'],
            'supervisor_position' => ['nullable', 'string', 'max:255'],
        ], [
            'supervisor_empcode.required' => 'Please select a supervisor.',
        ]);

        $assessment->update([
            'supervisor_empcode'  => $data['supervisor_empcode'],
            'supervisor_name'     => $data['supervisor_name'],
            'supervisor_position' => $data['supervisor_position'] ?? null,
        ]);

        return redirect()
            ->route('tna.self-rating')
            ->with('success', 'Your supervisor has been updated.');
    }

    /**
     * Burahin ang self-rating — pinapayagan LANG habang hindi pa
     * ni-rate ng supervisor. Owner lang ang makakabura.
     */
    public function destroy(TnaAssessment $assessment)
    {
        abort_unless($assessment->user_id === auth()->id(), 403);

        if ($assessment->supervisor_reviewed_at !== null) {
            return back()->withErrors([
                'ratings' => 'Cannot delete — this has already been reviewed by your supervisor.',
            ]);
        }

        $assessment->delete(); // kasama nang mabubura ang ratings (cascade)

        return redirect()
            ->route('tna.self-rating')
            ->with('success', 'Your self-rating has been deleted. You may submit a new one.');
    }

    protected function resolveEmployee($user): ?Employee
    {
        if (empty($user->empcode)) {
            return null;
        }

        return Employee::where('EMPCODE', $user->empcode)->first();
    }

    protected function fullName(Employee $e): string
    {
        $mi = $e->MI ? strtoupper(substr($e->MI, 0, 1)) . '.' : '';

        return trim(preg_replace('/\s+/', ' ',
            "{$e->FIRSTNAME} {$mi} {$e->LASTNAME}"
        ));
    }
}