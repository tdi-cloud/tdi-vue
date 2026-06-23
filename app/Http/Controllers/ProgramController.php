<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramCompetency;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->withCount('batches')
            ->with(['batches' => function ($q) {
                $q->select('id', 'program_code', 'date_start', 'date_end', 'status')
                  ->withCount(['participants', 'requirements']);
            }])
            ->get()
            ->map(function ($program) {
                $program->participants_count = $program->batches->sum('participants_count');
                $program->requirements_count = $program->batches->sum('requirements_count');

                $program->date_start = $program->batches->min('date_start');
                $program->date_end   = $program->batches->max('date_end');

                $program->batch_statuses = $program->batches
                    ->pluck('status')
                    ->unique()
                    ->values();

                $program->months = $program->batches
                    ->flatMap(function ($batch) {
                        if (! $batch->date_start || ! $batch->date_end) {
                            return [];
                        }

                        $start = \Carbon\Carbon::parse($batch->date_start)->startOfMonth();
                        $end   = \Carbon\Carbon::parse($batch->date_end)->startOfMonth();

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
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string',
            'description' => 'nullable|string',
            'modality'    => 'required|string',
            'pax'         => 'required|string',
            'category'    => 'required|string',
            'type'        => 'required|string',
            'initiated'   => 'required|string',
            'provider'    => 'nullable|string',
            'cost'        => 'required|string',
            'fund'        => 'required|string',
            'origin'      => 'required|string',
        ]);

        Program::create($request->all());

        return back()->with('success', 'Program created successfully.');
    }

    public function show(Program $program)
    {
        $program->load([
            'competencies',
            'supportingDocuments',
            'resourceSpeakers',
            'coverPage',
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
                              'certificates'
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
            'program'     => $program,
            'submissions' => $submissions,
        ]);
    }

    public function edit(Program $program)
    {
        return Inertia::render('programs/edit', [
            'program' => $program,
        ]);
    }

    public function update(Request $request, Program $program)
    {
        $request->validate([
            'title'       => 'required|string',
            'description' => 'nullable|string',
            'modality'    => 'required|string',
            'pax'         => 'required|string',
            'category'    => 'required|string',
            'type'        => 'required|string',
            'initiated'   => 'required|string',
            'provider'    => 'nullable|string',
            'cost'        => 'required|string',
            'fund'        => 'required|string',
            'origin'      => 'required|string',
        ]);

        $program->update($request->all());

        return redirect()->route('programs.show', $program)->with('success', 'Program updated successfully.');
    }

    public function storeCompetencies(Request $request, Program $program)
    {
        $request->validate([
            'competencies'              => 'required|array|min:1',
            'competencies.*.domain'     => 'required|string',
            'competencies.*.competency' => 'required|string',
        ]);

        foreach ($request->competencies as $item) {
            $program->competencies()->firstOrCreate([
                'domain'     => $item['domain'],
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