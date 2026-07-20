<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Program;
use App\Models\ProgramCompetency;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProgramController extends Controller
{
    /**
     * Buong listahan ng Category options. Kapag hindi "CO" ang REGION ng
     * naka-login, "Regional" na lang ang pwede.
     */
    public const CATEGORIES = [
        'Benchmarking', 'Capability Building', 'Executive-Office', 'Foreign-Bilateral',
        'Foreign-FSTP', 'Local-In-House', 'Local-Public', 'Other-Foreign', 'Regional', 'Team-Building',
    ];

    /**
     * Buong listahan ng Office Initiated options. Kapag hindi "CO" ang
     * REGION ng naka-login, NTTA at Other Training Provider na lang.
     */
    public const INITIATED_OPTIONS = [
        'TDI', 'NTTA', 'Other Executive Office', 'Other Training Provider',
    ];

    /**
     * REGION ng Employee record na naka-link sa naka-login na user, kung meron.
     * Null kung walang naka-link na Employee (ituturing na "CO" sa mga check).
     */
    protected function resolveUserRegion(): ?string
    {
        $user = auth()->user();
        if (! $user || empty($user->empcode)) {
            return null;
        }

        return Employee::where('EMPCODE', $user->empcode)->value('REGION');
    }

    /**
     * Category/Initiated values na pwede para sa naka-login (base sa region).
     *
     * @return array{0: array<string>, 1: array<string>}
     */
    protected function allowedCategoryAndInitiated(): array
    {
        $region = $this->resolveUserRegion();
        $isRestricted = $region !== null && $region !== 'CO';

        return [
            $isRestricted ? ['Regional'] : self::CATEGORIES,
            $isRestricted ? ['NTTA', 'Other Training Provider'] : self::INITIATED_OPTIONS,
        ];
    }

    public function index()
    {
        $programs = Program::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->withCount('batches')
            ->with([
                'coverPage',
                'batches' => function ($q) {
                    $q->select('id', 'program_code', 'date_start', 'date_end', 'status')
                        ->withCount(['participants', 'requirements']);
                },
            ])
            ->get()
            ->map(function ($program) {
                $program->participants_count = $program->batches->sum('participants_count');
                $program->requirements_count = $program->batches->sum('requirements_count');

                $program->date_start = $program->batches->min('date_start');
                $program->date_end = $program->batches->max('date_end');

                $program->batch_statuses = $program->batches
                    ->pluck('status')
                    ->unique()
                    ->values();

                $program->months = $program->batches
                    ->flatMap(function ($batch) {
                        if (! $batch->date_start || ! $batch->date_end) {
                            return [];
                        }

                        $start = Carbon::parse($batch->date_start)->startOfMonth();
                        $end = Carbon::parse($batch->date_end)->startOfMonth();

                        $months = [];
                        while ($start->lte($end)) {
                            $months[] = $start->format('Y-m');
                            $start->addMonth();
                        }

                        return $months;
                    })
                    ->unique()
                    ->values();

                unset($program->batches);

                return $program;
            });

        return Inertia::render('programs/index', [
            'programs' => $programs,
            'userRegion' => $this->resolveUserRegion(),
        ]);
    }

    public function store(Request $request)
    {
        [$allowedCategories, $allowedInitiated] = $this->allowedCategoryAndInitiated();

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'modality' => 'required|string',
            'pax' => 'required|string',
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'type' => 'required|string',
            'initiated' => ['required', 'string', Rule::in($allowedInitiated)],
            'provider' => 'nullable|string',
            'cost' => 'required|string',
            'fund' => 'required|string',
            'origin' => 'required|string',
        ]);

        Program::create(array_merge($request->all(), [
            'added_by' => auth()->user()->empcode,
        ]));

        return back()->with('success', 'Program created successfully.');
    }

    public function show(Program $program)
    {
        $program->load([
            'competencies',
            'supportingDocuments',
            'resourceSpeakers',
            'coverPage',
            'emailReminderLogs' => function ($q) {
                $q->select(['id', 'sent_by', 'sent_by_name', 'program_id', 'batch_id', 'requirement_id', 'subject', 'recipients', 'recipients_count', 'created_at']);
            },
            'batches' => function ($q) {
                $q->orderBy('sort_order')
                    ->orderBy('date_start')
                    ->with([
                        'requirements',
                        'participants' => function ($pq) {
                            $pq->addSelect([
                                'participants.*',
                                // Pull the matching user's email via a correlated subquery
                                DB::raw('(
                                  SELECT users.email
                                  FROM users
                                  WHERE users.empcode = participants.empcode
                                  LIMIT 1
                              ) AS user_email'),
                            ])
                                ->with([
                                    'employee',
                                    'justification',
                                    'submissions.requirement',
                                    'certificates',
                                ]);
                        },
                    ]);
            },
        ]);

        $submissions = Submission::with(['participant.employee', 'batch', 'requirement'])
            ->where('program_code', $program->program_code)
            ->orderByDesc('submitted_at')
            ->get();

        return Inertia::render('programs/show', [
            'program' => $program,
            'submissions' => $submissions,
        ]);
    }

    public function edit(Program $program)
    {
        return Inertia::render('programs/edit', [
            'program' => $program,
            'userRegion' => $this->resolveUserRegion(),
        ]);
    }

    public function update(Request $request, Program $program)
    {
        [$allowedCategories, $allowedInitiated] = $this->allowedCategoryAndInitiated();

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'modality' => 'required|string',
            'pax' => 'required|string',
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'type' => 'required|string',
            'initiated' => ['required', 'string', Rule::in($allowedInitiated)],
            'provider' => 'nullable|string',
            'cost' => 'required|string',
            'fund' => 'required|string',
            'origin' => 'required|string',
        ]);

        $program->update($request->all());

        return redirect()->route('programs.show', $program)->with('success', 'Program updated successfully.');
    }

    public function destroy(Program $program)
    {
        // Kasama nang mabubura ang mga batches nito (tingnan ang Program::booted()).
        $program->delete();

        return redirect()->route('programs.index')->with('success', 'Program deleted successfully.');
    }

    public function storeCompetencies(Request $request, Program $program)
    {
        $request->validate([
            'competencies' => 'required|array|min:1',
            'competencies.*.domain' => 'required|string',
            'competencies.*.competency' => 'required|string',
        ]);

        foreach ($request->competencies as $item) {
            $program->competencies()->firstOrCreate([
                'domain' => $item['domain'],
                'competency' => $item['competency'],
            ]);
        }

        return back()->with('success', 'Competencies added successfully.');
    }

    public function destroyCompetency(Program $program, ProgramCompetency $competency)
    {
        abort_unless($competency->program_id === $program->id, 404);

        $competency->delete();

        return back()->with('success', 'Competency removed successfully.');
    }
}
