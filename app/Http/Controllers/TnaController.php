<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;
use App\Notifications\FasdAssigned;
use App\Notifications\NewSubordinateToRate;
use App\Notifications\SelfRatingReviewed;
use App\Notifications\SelfRatingSubmitted;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TnaController extends Controller
{
    /**
     * Uploaded signed copy (hindi ang auto-generated PDF) na maaaring
     * i-attach sa isang assessment. Key = route param, value = column.
     */
    public const SCAN_TYPES = [
        'self' => 'self_rating_scan_path',
        'supervisory' => 'supervisor_rating_scan_path',
        'result-subordinate' => 'result_scan_subordinate_path',
        'result-supervisor' => 'result_scan_supervisor_path',
    ];

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
            'period' => $period,
            'position' => $employee->POSITION,
            'submitted' => (bool) $assessment,
            'assessment_id' => $assessment?->id,
            'reviewed' => $assessment?->supervisor_reviewed_at !== null,
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

        $base = TnaAssessment::where('supervisor_empcode', $user->empcode)
            ->whereNotNull('submitted_at');

        $total = (clone $base)->count();

        if ($total === 0) {
            return null;
        }

        $pending = (clone $base)->whereNull('supervisor_reviewed_at')->count();

        return [
            'pending' => $pending,
            'rated' => $total - $pending,
            'total' => $total,
        ];
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
                'id' => $a->id,
                'name' => $a->name,
                'position' => $a->position,
                'office' => $a->office,
                'division' => $a->division,
                'period' => $a->period,
                'submitted_at' => $a->submitted_at?->format('M d, Y g:i A'),
                'reviewed' => $a->supervisor_reviewed_at !== null,
                'reviewed_at' => $a->supervisor_reviewed_at?->format('M d, Y g:i A'),
                'supervisor_scan_uploaded' => (bool) $a->supervisor_rating_scan_path,
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
                'unit' => $unit,
                'elements' => $group->map(fn ($r) => [
                    'competency_id' => $r->competency_id,
                    'element' => $r->competency->element,
                    'self' => [
                        'criticality' => $r->criticality,
                        'competence' => $r->competence,
                        'frequency' => $r->frequency,
                    ],
                ])->values(),
            ])->values();

        return Inertia::render('Tna/SupervisoryRating', [
            'assessment' => [
                'id' => $assessment->id,
                'period' => $assessment->period,
                'subordinate_name' => $assessment->name,
                'subordinate_position' => $assessment->position,
            ],
            'supervisor' => [
                'name' => $supEmployee ? $this->fullName($supEmployee) : $assessment->supervisor_name,
                'office' => $supEmployee?->OFFICE,
                'division' => $supEmployee?->SECTION,
            ],
            'coreUnits' => $toUnits('core'),
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
            'name' => ['required', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'subordinate_name' => ['required', 'string', 'max:255'],
            'subordinate_position' => ['required', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'max:3000000', 'regex:/^data:image\/(png|jpeg);base64,[A-Za-z0-9+\/]+=*$/'],
            'fasd_empcode' => ['required', 'string', 'max:255'],
            'fasd_name' => ['required', 'string', 'max:255'],
            'fasd_position' => ['nullable', 'string', 'max:255'],
            'fasd_office' => ['nullable', 'string', 'max:255'],
            'ratings' => ['required', 'array', 'min:1'],
            'ratings.*.competency_id' => ['required', 'integer'],
            'ratings.*.criticality' => ['required', 'integer', 'between:1,3'],
            'ratings.*.competence' => ['required', 'integer', 'between:0,4'],
            'ratings.*.frequency' => ['required', 'integer', 'between:1,3'],
        ], [
            'fasd_empcode.required' => 'Please select the FASD signatory before submitting.',
        ]);

        $ratingRows = $assessment->ratings()->get()->keyBy('competency_id');
        $submitted = collect($data['ratings'])->keyBy('competency_id');

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
                    'sup_competence' => $r['competence'],
                    'sup_frequency' => $r['frequency'],
                ]);
            }

            $assessment->update([
                'supervisor_form' => [
                    'name' => $data['name'],
                    'office' => $data['office'] ?? null,
                    'division' => $data['division'] ?? null,
                    'subordinate_name' => $data['subordinate_name'],
                    'subordinate_position' => $data['subordinate_position'],
                    'signature' => $data['signature'],
                    'fasd_empcode' => $data['fasd_empcode'] ?? null,
                    'fasd_name' => $data['fasd_name'] ?? null,
                    'fasd_position' => $data['fasd_position'] ?? null,
                    'fasd_office' => $data['fasd_office'] ?? null,
                ],
                'supervisor_reviewed_at' => now(),
            ]);
        });

        $assessment->user?->notify(new SelfRatingReviewed($assessment));
        $assessment->user?->notify(new FasdAssigned($assessment));

        return redirect()
            ->route('tna.supervisory.index')
            ->with('success', 'Supervisory rating submitted. Thank you!');
    }

    /**
     * Burahin ang supervisory rating para makapag-rate ulit.
     * Ang napiling supervisor lang ang makakagawa nito.
     */
    public function supervisoryRedo(TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );

        if ($assessment->supervisor_reviewed_at === null) {
            return back()->withErrors([
                'ratings' => 'This assessment has not been rated yet.',
            ]);
        }

        DB::transaction(function () use ($assessment) {
            // I-clear ang mga rating ng supervisor (hindi hinahawakan ang self-rating)
            $assessment->ratings()->update([
                'sup_criticality' => null,
                'sup_competence' => null,
                'sup_frequency' => null,
            ]);

            // I-unlock: mababalik ang assessment sa "Pending"
            $assessment->update([
                'supervisor_form' => null,
                'supervisor_reviewed_at' => null,
            ]);
        });

        return redirect()
            ->route('tna.supervisory.index')
            ->with('success', 'Supervisory rating cleared. You may rate this assessment again.');
    }

    /**
     * Ipakita ang form para palitan LANG ang FASD signatory ("Noted by" sa
     * TNA Result) ng isang na-review nang assessment. Hindi hinahawakan ang
     * ratings o ibang bahagi ng supervisor_form. Napiling supervisor lang.
     */
    public function editFasd(TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );
        abort_if($assessment->supervisor_reviewed_at === null, 404);

        $form = $assessment->supervisor_form ?? [];

        return Inertia::render('Tna/EditFasd', [
            'assessment' => [
                'id' => $assessment->id,
                'subordinate_name' => $form['subordinate_name'] ?? $assessment->name,
                'subordinate_position' => $form['subordinate_position'] ?? $assessment->position,
            ],
            'fasd' => [
                'empcode' => $form['fasd_empcode'] ?? null,
                'name' => $form['fasd_name'] ?? null,
                'position' => $form['fasd_position'] ?? null,
                'office' => $form['fasd_office'] ?? null,
            ],
        ]);
    }

    /**
     * I-save ang bagong FASD signatory. Hindi hinahawakan ang ratings o
     * ibang bahagi ng supervisor_form.
     */
    public function updateFasd(Request $request, TnaAssessment $assessment)
    {
        $user = auth()->user();
        abort_unless(
            ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            403
        );
        abort_if($assessment->supervisor_reviewed_at === null, 404);

        $data = $request->validate([
            'fasd_empcode' => ['required', 'string', 'max:255'],
            'fasd_name' => ['required', 'string', 'max:255'],
            'fasd_position' => ['nullable', 'string', 'max:255'],
            'fasd_office' => ['nullable', 'string', 'max:255'],
        ], [
            'fasd_empcode.required' => 'Please select the FASD signatory.',
        ]);

        $assessment->update([
            'supervisor_form' => array_merge($assessment->supervisor_form ?? [], $data),
        ]);

        $assessment->user?->notify(new FasdAssigned($assessment));

        return redirect()
            ->route('tna.supervisory.index')
            ->with('success', 'FASD signatory updated.');
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
                    'element' => $r->competency->element,
                    'criticality' => $r->sup_criticality,
                    'competence' => $r->sup_competence,
                    'frequency' => $r->sup_frequency,
                ])->values(),
            ])->values();

        $form = $assessment->supervisor_form ?? [];

        $pdf = Pdf::loadView('pdf.supervisory-rating', [
            'a' => $assessment,
            'form' => $form,
            'core' => $build('core'),
            'elective' => $build('elective'),
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Supervisor-Rating - '.$clean($form['subordinate_name'] ?? $assessment->name)
            .' - '.$clean($form['subordinate_position'] ?? $assessment->position)
            .' - '.$clean($assessment->period).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * ── TNA RESULT ────────────────────────────────────────────────
     * Weighted score kada element:
     *   w = 0.4*self + 0.6*supervisor  (kada scale)
     *   Competency Profile Result = w_criticality × w_competence × w_frequency
     * Max = 3 × 4 × 3 = 36.
     */
    public const RESULT_BANDS = [
        [0,   4,  'Not Competent',        true],
        [5,   12, 'Slightly Competent',   true],
        [13,  20, 'Moderately Competent', true],
        [21,  28, 'Competent',            false],
        [29,  36, 'Highly Competent',     false],
    ];

    protected function bandFor(float $score): array
    {
        // Ang RESULT_BANDS ay integer boundaries (hal. 13-20, 21-28), pero
        // decimal ang aktwal na score (rounded to 1 decimal). Kung hi lang
        // (hal. 20) ang gagamitin bilang exclusive-or-equal na upper bound,
        // may nalalaktawang saklaw (hal. 20.1-20.9) -> "—" ang lumalabas.
        // Kaya ang upper bound ng bawat band (maliban sa huli) ay ang lo ng
        // susunod na band, para tuluy-tuloy walang puwang ang saklaw.
        $bands = self::RESULT_BANDS;
        $lastIndex = count($bands) - 1;

        foreach ($bands as $i => [$lo, $hi, $label, $needsTraining]) {
            $isLast = $i === $lastIndex;
            $upper = $isLast ? $hi : $bands[$i + 1][0];
            $inRange = $isLast
                ? ($score >= $lo && $score <= $upper)
                : ($score >= $lo && $score < $upper);

            if ($inRange) {
                return ['label' => $label, 'needs_training' => $needsTraining];
            }
        }

        return ['label' => '—', 'needs_training' => false];
    }

    /**
     * Buuin ang buong result data ng isang assessment.
     */
    protected function buildResult(TnaAssessment $assessment): array
    {
        $w = fn ($self, $sup) => (0.4 * (float) $self) + (0.6 * (float) $sup);

        $ratings = $assessment->ratings()->with('competency')->get()
            ->filter(fn ($r) => $r->competency)
            ->sortBy(fn ($r) => $r->competency->sort_order)
            ->values();

        $rows = $ratings->map(function ($r) use ($w) {
            $wc = $w($r->criticality, $r->sup_criticality);
            $wl = $w($r->competence, $r->sup_competence);
            $wf = $w($r->frequency, $r->sup_frequency);
            $score = round($wc * $wl * $wf, 1);
            $band = $this->bandFor($score);

            // Revised formula (bago, hiwalay sa orihinal na TNA formula sa
            // itaas): Criticality × (4 - Competence). Dito, MATAAS ang
            // resulta kapag kritikal ang element sa trabaho PERO mababa ang
            // competence -> mas malaking training need. Baligtad ito sa
            // orihinal na formula kung saan mababang score ang nangangailangan
            // ng training.
            $revisedScore = round($wc * (4 - $wl), 1);

            return [
                'competency_id' => $r->competency_id,
                'unit' => $r->competency->unit,
                'element' => $r->competency->element,
                'type' => $r->competency->type,
                'crit_self' => $r->criticality,
                'crit_sup' => $r->sup_criticality,
                'comp_self' => $r->competence,
                'comp_sup' => $r->sup_competence,
                'freq_self' => $r->frequency,
                'freq_sup' => $r->sup_frequency,
                'score' => $score,
                'label' => $band['label'],
                'needs_training' => $band['needs_training'],
                'revised_score' => $revisedScore,
            ];
        });

        // Group by unit (para sa merged na Unit cell)
        $units = $rows->groupBy('unit')
            ->map(fn ($group, $unit) => ['unit' => $unit, 'rows' => $group->values()])
            ->values();

        // Top 3 UNIT na pinakamababa ang competency (base sa pinakamababang
        // element score sa loob ng unit) at nangangailangan ng training.
        $priority = $rows
            ->groupBy('unit')
            ->map(fn ($group, $unit) => [
                'unit' => $unit,
                'score' => $group->min('score'),
                'label' => $this->bandFor((float) $group->min('score'))['label'],
                'needs_training' => $this->bandFor((float) $group->min('score'))['needs_training'],
            ])
            ->filter(fn ($u) => $u['needs_training'])
            ->sortBy('score')
            ->take(3)
            ->values();

        // Revised formula priority: top 3 UNIT na pinakamataas ang revised
        // score (base sa pinakamataas na element revised score sa loob ng
        // unit) -> mataas na resulta dito ang may training need.
        $revisedPriority = $rows
            ->groupBy('unit')
            ->map(fn ($group, $unit) => [
                'unit' => $unit,
                'revised_score' => $group->max('revised_score'),
            ])
            ->sortByDesc('revised_score')
            ->take(3)
            ->values();

        return [
            'units' => $units,
            'priority' => $priority,
            'revised_priority' => $revisedPriority,
        ];
    }

    /**
     * Access check: subordinate (may-ari) o ang napiling supervisor.
     */
    protected function canViewResult(TnaAssessment $assessment): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($assessment->user_id === $user->id) {
            return true;
        }

        return ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode;
    }

    /**
     * Sino ang pwedeng mag-upload sa isang partikular na scan slot.
     */
    protected function canUploadScan(string $type, TnaAssessment $assessment): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return match ($type) {
            'self', 'result-subordinate' => $assessment->user_id === $user->id,
            'supervisory', 'result-supervisor' => ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
            default => false,
        };
    }

    /**
     * I-upload (o palitan) ang signed copy ng isang assessment. Karagdagang
     * attachment lang ito — hindi pinapalitan ang auto-generated na PDF.
     */
    public function uploadScan(Request $request, TnaAssessment $assessment, string $type)
    {
        abort_unless(array_key_exists($type, self::SCAN_TYPES), 404);
        abort_unless($this->canUploadScan($type, $assessment), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $column = self::SCAN_TYPES[$type];

        if ($assessment->{$column}) {
            Storage::disk('local')->delete($assessment->{$column});
        }

        $path = $request->file('file')->storeAs("tna-scans/{$assessment->id}", "{$type}.pdf", 'local');

        $assessment->update([$column => $path]);

        return back()->with('success', 'Scanned copy uploaded.');
    }

    /**
     * Ipakita ang na-upload na signed copy sa browser (inline, hindi
     * force-download). Owner o naka-assign na supervisor lang ang
     * makaka-access, kahit sinong slot ito.
     */
    public function downloadScan(TnaAssessment $assessment, string $type)
    {
        abort_unless(array_key_exists($type, self::SCAN_TYPES), 404);
        abort_unless($this->canViewResult($assessment), 403);

        $column = self::SCAN_TYPES[$type];
        $path = $assessment->{$column};

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'TNA-Scan-'.$clean($type).' - '.$clean($assessment->name).'.pdf';

        return Storage::disk('local')->response($path, $filename);
    }

    /**
     * Burahin ang na-upload na signed copy. Kaparehong access-rule ng
     * upload — sino man ang pwedeng mag-upload sa slot, pwede ring magbura.
     */
    public function deleteScan(TnaAssessment $assessment, string $type)
    {
        abort_unless(array_key_exists($type, self::SCAN_TYPES), 404);
        abort_unless($this->canUploadScan($type, $assessment), 403);

        $column = self::SCAN_TYPES[$type];
        $path = $assessment->{$column};

        abort_if(! $path, 404);

        Storage::disk('local')->delete($path);
        $assessment->update([$column => null]);

        return back()->with('success', 'Signed copy deleted.');
    }

    /**
     * Result page (nakikita ng subordinate at ng supervisor).
     */
    public function resultShow(TnaAssessment $assessment)
    {
        abort_unless($this->canViewResult($assessment), 403);
        abort_if($assessment->supervisor_reviewed_at === null, 404);

        $result = $this->buildResult($assessment);
        $form = $assessment->supervisor_form ?? [];
        $user = auth()->user();

        return Inertia::render('Tna/Result', [
            'assessment' => [
                'id' => $assessment->id,
                'period' => $assessment->period,
                'employee_name' => $assessment->name,
                'position' => $assessment->position,
                'office' => $assessment->office,
                'division' => $assessment->division,
                'supervisor_name' => $form['name'] ?? $assessment->supervisor_name,
                'reviewed_at' => $assessment->supervisor_reviewed_at->format('M d, Y g:i A'),
                'is_owner' => $assessment->user_id === $user->id,
                'is_supervisor' => ! empty($user->empcode) && $assessment->supervisor_empcode === $user->empcode,
                'result_scan_subordinate_uploaded' => (bool) $assessment->result_scan_subordinate_path,
                'result_scan_supervisor_uploaded' => (bool) $assessment->result_scan_supervisor_path,
            ],
            'units' => $result['units'],
            'priority' => $result['priority'],
            'revisedPriority' => $result['revised_priority'],
            'bands' => collect(self::RESULT_BANDS)->map(fn ($b) => [
                'range' => $b[0].'-'.$b[1],
                'label' => $b[2],
            ]),
        ]);
    }

    /**
     * PDF ng TNA Result (ISO form F03).
     */
    public function resultPdf(TnaAssessment $assessment)
    {
        abort_unless($this->canViewResult($assessment), 403);
        abort_if($assessment->supervisor_reviewed_at === null, 404);

        $result = $this->buildResult($assessment);
        $form = $assessment->supervisor_form ?? [];

        $pdf = Pdf::loadView('pdf.tna-result', [
            'a' => $assessment,
            'form' => $form,
            'units' => $result['units'],
            'priority' => $result['priority'],
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'TNA-Result - '.$clean($assessment->name)
            .' - '.$clean($assessment->position)
            .' - '.$clean($assessment->period).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Ipakita ang self-rating form ng naka-login na employee.
     */
    public function selfRating()
    {
        $user = auth()->user();
        $employee = $this->resolveEmployee($user);

        if (! $employee) {
            return Inertia::render('Tna/NoTool', [
                'reason' => 'no_employee',
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
                'reason' => 'no_competencies',
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
                'unit' => $unit,
                'elements' => $group->map(fn ($c) => [
                    'id' => $c->id,
                    'element' => $c->element,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Tna/SelfRating', [
            'period' => $period,
            'employee' => [
                'name' => $this->fullName($employee),
                'office' => $employee->OFFICE,
                'division' => $employee->SECTION,
                'position' => $position,
            ],
            'coreUnits' => $toUnits($competencies->where('type', 'core')),
            'electiveUnits' => $toUnits($competencies->where('type', 'elective')),
            'alreadySubmitted' => $existing ? [
                'id' => $existing->id,
                'submitted_at' => $existing->submitted_at->format('M d, Y g:i A'),
                'reviewed' => $existing->supervisor_reviewed_at !== null,
                'supervisor_name' => $existing->supervisor_name,
                'self_scan_uploaded' => (bool) $existing->self_rating_scan_path,
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
                'empcode' => $e->EMPCODE,
                'name' => $this->fullName($e),
                'position' => $e->POSITION,
                'office' => $e->{'OFFICE/DIVISION'},
            ]);

        return response()->json($results);
    }

    /**
     * Search ng FASD signatory ("Noted by" ng TNA Result) mula sa employees
     * table, naka-scope sa REGION ng naka-login na supervisor.
     */
    public function searchFasd(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $me = $this->resolveEmployee(auth()->user());
        if (! $me) {
            return response()->json([]);
        }

        // Hinahati ang "Juan Santos" -> unang salita sa FIRSTNAME, huli sa
        // LASTNAME, para hindi kailangan ng CONCAT() na hindi portable
        // (walang CONCAT ang SQLite, ginagamit sa tests).
        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

        $results = Employee::query()
            ->where('REGION', $me->REGION)
            ->where('EMPCODE', '!=', $me->EMPCODE)
            ->where(function ($query) use ($q, $words) {
                $query->where('FIRSTNAME', 'like', "%{$q}%")
                    ->orWhere('LASTNAME', 'like', "%{$q}%")
                    ->orWhere('EMPCODE', 'like', "%{$q}%");

                if (count($words) >= 2) {
                    $query->orWhere(function ($nameQuery) use ($words) {
                        $nameQuery->where('FIRSTNAME', 'like', '%'.$words[0].'%')
                            ->where('LASTNAME', 'like', '%'.end($words).'%');
                    });
                }
            })
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'empcode' => $e->EMPCODE,
                'name' => $this->fullName($e),
                'position' => $e->POSITION,
                'office' => $e->{'OFFICE/DIVISION'},
            ]);

        return response()->json($results);
    }

    /**
     * I-save ang isinumiteng self-rating.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'supervisor_empcode' => ['required', 'string', 'max:255'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_position' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'max:3000000', 'regex:/^data:image\/(png|jpeg);base64,[A-Za-z0-9+\/]+=*$/'],
            'ratings' => ['required', 'array', 'min:1'],
            'ratings.*.competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'ratings.*.criticality' => ['nullable', 'integer', 'between:1,3'],
            'ratings.*.competence' => ['nullable', 'integer', 'between:0,4'],
            'ratings.*.frequency' => ['nullable', 'integer', 'between:1,3'],
        ], [
            'supervisor_empcode.required' => 'Please select a supervisor before submitting.',
        ]);

        $user = auth()->user();
        $employee = $this->resolveEmployee($user);
        abort_if(! $employee, 403, 'No employee record found.');

        $position = $employee->POSITION;
        $period = config('tna.period');

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
        $submitted = collect($data['ratings'])->keyBy('competency_id');
        $incomplete = $requiredIds->first(function ($id) use ($submitted) {
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

        $assessment = DB::transaction(function () use ($data, $user, $position, $period, $competencies) {
            $assessment = TnaAssessment::create([
                'user_id' => $user->id,
                'position' => $position,
                'period' => $period,
                'name' => $data['name'],
                'office' => $data['office'] ?? null,
                'division' => $data['division'] ?? null,
                'designation' => $data['designation'] ?? null,
                'supervisor_empcode' => $data['supervisor_empcode'],
                'supervisor_name' => $data['supervisor_name'],
                'supervisor_position' => $data['supervisor_position'] ?? null,
                'signature' => $data['signature'],
                'submitted_at' => now(),
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
                    'criticality' => $r['criticality'] ?? null,
                    'competence' => $r['competence'] ?? null,
                    'frequency' => $r['frequency'] ?? null,
                ]);
            }

            return $assessment;
        });

        $user->notify(new SelfRatingSubmitted($assessment));

        $supervisorUser = User::where('empcode', $data['supervisor_empcode'])->first();
        if ($supervisorUser) {
            $supervisorUser->notify(new NewSubordinateToRate($assessment));
        }

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
                    'element' => $r->competency->element,
                    'criticality' => $r->criticality,
                    'competence' => $r->competence,
                    'frequency' => $r->frequency,
                ])->values(),
            ])->values();

        $pdf = Pdf::loadView('pdf.self-rating', [
            'a' => $assessment,
            'core' => $build('core'),
            'elective' => $build('elective'),
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        // Formal, nakapangalang filename: nag-self-rate + position + period
        $clean = fn ($s) => trim(str_replace(['/', '\\', '"'], '-', (string) $s));
        $filename = 'Self-Rating - '.$clean($assessment->name)
            .' - '.$clean($assessment->position)
            .' - '.$clean($assessment->period).'.pdf';

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
            'supervisor_empcode' => ['required', 'string', 'max:255'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_position' => ['nullable', 'string', 'max:255'],
        ], [
            'supervisor_empcode.required' => 'Please select a supervisor.',
        ]);

        $assessment->update([
            'supervisor_empcode' => $data['supervisor_empcode'],
            'supervisor_name' => $data['supervisor_name'],
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
        $mi = $e->MI ? strtoupper(substr($e->MI, 0, 1)).'.' : '';

        return trim(preg_replace('/\s+/', ' ',
            "{$e->FIRSTNAME} {$mi} {$e->LASTNAME}"
        ));
    }
}
