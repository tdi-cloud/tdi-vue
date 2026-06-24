<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('dashboard');
    }

    public function offices(Request $request)
    {
        $region = $request->region;

        $offices = DB::table('employees')
            ->select('OFFICE')
            ->distinct()
            ->whereNotNull('OFFICE')
            ->where('OFFICE', '!=', '')
            ->when($region && $region !== 'ALL', function ($q) use ($region) {
                $q->where('REGION', $region);
            })
            ->orderBy('OFFICE')
            ->pluck('OFFICE');

        return response()->json($offices);
    }

    private function applyEmployeeFilters($query, $region, $statuses, $officeFilter, $office = null, $prefix = '')
    {
        return $query
            ->when($region && $region !== 'ALL', function ($q) use ($region, $prefix) {
                $q->where($prefix . 'REGION', $region);
            })
            ->when(!empty($statuses), function ($q) use ($statuses, $prefix) {
                $q->whereIn($prefix . 'PLANTILLA STATUS', $statuses);
            })
            ->when($officeFilter === 'OPCR', function ($q) use ($prefix) {
                $q->where(function ($query) use ($prefix) {
                    $query->where($prefix . 'OFFICE/DIVISION', 'LIKE', 'CO-%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%-ORD')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%-ROD')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%-FASD')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%-PO-%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%-DO-%');
                });
            })
            // NEW: filter by OFFICE column
            ->when($office && $office !== 'ALL', function ($q) use ($office, $prefix) {
                $q->where($prefix . 'OFFICE', $office);
            });
    }

    // ── Helper: apply year filter on a batches-joined query ─────────────────
    private function applyYearFilter($query, $year)
    {
        return $query->when($year && $year !== 'ALL', function ($q) use ($year) {
            $q->where('batches.date_start', 'LIKE', $year . '%');
        });
    }

    // ── NEW: returns unique years from batches.date_start ───────────────────
    public function batchYears()
    {
        $years = DB::table('batches')
            ->selectRaw('DISTINCT SUBSTRING(date_start, 1, 4) as year')
            ->whereNotNull('date_start')
            ->where('date_start', '!=', '')
            ->orderByDesc('year')
            ->pluck('year');

        return response()->json($years);
    }

    public function trainingCompliance(Request $request)
    {
        $region       = $request->region;
        $statuses     = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office       = $request->office;       // NEW
        $year         = $request->year;         // NEW

        $allRegions = [
            'CO','NCR','R1','R2','R3','R4A','R4B','R5',
            'NIR','R6','R7','R8','R9','R10','R11','R12',
            'CAR','CARAGA',
        ];

        // TOTAL EMPLOYEES
        $totalEmployees = $this->applyEmployeeFilters(
            DB::table('employees'), $region, $statuses, $officeFilter, $office
        )->count();

        // TRAINED EMPLOYEES
        $trainedQuery = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id'),
            $region, $statuses, $officeFilter, $office, 'employees.'
        )
            ->where('participants.attendance', '!=', 'Absent')
            ->where('batches.hours', '>=', 8);

        $trainedQuery = $this->applyYearFilter($trainedQuery, $year); // NEW

        $trainedEmployees = $trainedQuery->distinct()->count('employees.EMPCODE');

        $notTrained           = $totalEmployees - $trainedEmployees;
        $trainedPercentage    = $totalEmployees > 0 ? round(($trainedEmployees / $totalEmployees) * 100, 2) : 0;
        $notTrainedPercentage = $totalEmployees > 0 ? round(($notTrained      / $totalEmployees) * 100, 2) : 0;

        // REGIONAL BREAKDOWN
        $regionsBreakdown = [];

        foreach ($allRegions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsBreakdown[] = ['total' => 0, 'trained' => 0, 'not_trained' => 0];
                continue;
            }

            $regTotal = $this->applyEmployeeFilters(
                DB::table('employees')->where('REGION', $reg),
                null, $statuses, $officeFilter, $office
            )->count();

            $regTrainedQuery = $this->applyEmployeeFilters(
                DB::table('employees')
                    ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                    ->join('batches', 'participants.batch_id', '=', 'batches.id')
                    ->where('employees.REGION', $reg),
                null, $statuses, $officeFilter, $office, 'employees.'
            )
                ->where('participants.attendance', '!=', 'Absent')
                ->where('batches.hours', '>=', 8);

            $regTrainedQuery  = $this->applyYearFilter($regTrainedQuery, $year); // NEW
            $regTrained       = $regTrainedQuery->distinct()->count('employees.EMPCODE');

            $regionsBreakdown[] = [
                'total'       => $regTotal,
                'trained'     => $regTrained,
                'not_trained' => $regTotal - $regTrained,
            ];
        }

        $regionsTrained    = array_values(array_map(fn($v) => $v['trained'],     $regionsBreakdown));
        $regionsNotTrained = array_values(array_map(fn($v) => $v['not_trained'], $regionsBreakdown));

        return response()->json([
            'total'                  => $totalEmployees,
            'trained'                => $trainedEmployees,
            'not_trained'            => $notTrained,
            'trained_percentage'     => $trainedPercentage,
            'not_trained_percentage' => $notTrainedPercentage,
            'statuses'               => $statuses,
            'region'                 => $region,
            'office_filter'          => $officeFilter,
            'office'                 => $office,
            'year'                   => $year,
            'regions'                => $allRegions,
            'regions_trained'        => $regionsTrained,
            'regions_not_trained'    => $regionsNotTrained,
        ]);
    }

    public function trainingComplianceList(Request $request)
    {
        $region       = $request->region;
        $statuses     = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office       = $request->office;   // NEW
        $year         = $request->year;     // NEW
        $type         = $request->type === 'trained' ? 'trained' : 'not_trained';

        $query = $this->applyEmployeeFilters(
            DB::table('employees'), $region, $statuses, $officeFilter, $office
        );

        $trainingExists = function ($q) use ($year) {
            $q->select(DB::raw(1))
                ->from('participants')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->whereColumn('participants.empcode', 'employees.EMPCODE')
                ->where('participants.attendance', '!=', 'Absent')
                ->where('batches.hours', '>=', 8)
                // NEW: year filter inside subquery
                ->when($year && $year !== 'ALL', function ($q) use ($year) {
                    $q->where('batches.date_start', 'LIKE', $year . '%');
                });
        };

        if ($type === 'trained') {
            $query->whereExists($trainingExists);
        } else {
            $query->whereNotExists($trainingExists);
        }

        $employees = $query
            ->select(
                'EMPCODE', 'LASTNAME', 'FIRSTNAME', 'MI',
                'POSITION', 'OFFICE/DIVISION as office_division',
                'OFFICE', 'REGION', 'PLANTILLA STATUS as plantilla_status',
            )
            ->orderBy('LASTNAME')->orderBy('FIRSTNAME')
            ->get();

        return response()->json([
            'type'      => $type,
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }

    public function supervisoryCompliance(Request $request)
    {
        $region       = $request->region;
        $statuses     = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office       = $request->office;   // NEW
        $year         = $request->year;     // NEW
        $sgMin        = $request->sg_min ?? 19;

        $allRegions = [
            'CO','NCR','R1','R2','R3','R4A','R4B','R5',
            'NIR','R6','R7','R8','R9','R10','R11','R12',
            'CAR','CARAGA',
        ];

        // Supervisory hours subquery — year-aware
        $hoursSubquery = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('programs', 'batches.program_code', '=', 'programs.program_code')
            ->where('programs.type', 'SUPERVISORY/MANAGERIAL')
            ->where('participants.attendance', '!=', 'Absent')
            // NEW: year filter
            ->when($year && $year !== 'ALL', function ($q) use ($year) {
                $q->where('batches.date_start', 'LIKE', $year . '%');
            })
            ->select(
                'participants.empcode',
                DB::raw('SUM(CAST(batches.hours AS DECIMAL(10,2))) as total_hours')
            )
            ->groupBy('participants.empcode');

        $baseQuery = function () use ($region, $statuses, $officeFilter, $office, $sgMin) {
            return $this->applyEmployeeFilters(
                DB::table('employees')->where('SG', '>=', $sgMin),
                $region, $statuses, $officeFilter, $office
            );
        };

        $totalEmployees = $baseQuery()->count();

        $completed = $baseQuery()
            ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '>=', 40)
            ->distinct()->count('employees.EMPCODE');

        $inProgress = $baseQuery()
            ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '<', 40)
            ->where('emp_hours.total_hours', '>', 0)
            ->distinct()->count('employees.EMPCODE');

        $completedPct  = $totalEmployees > 0 ? round(($completed  / $totalEmployees) * 100, 2) : 0;
        $inProgressPct = $totalEmployees > 0 ? round(($inProgress / $totalEmployees) * 100, 2) : 0;

        // REGIONAL BREAKDOWN
        $regionsCompleted  = [];
        $regionsInProgress = [];

        foreach ($allRegions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsCompleted[]  = 0;
                $regionsInProgress[] = 0;
                continue;
            }

            $regBase = function () use ($reg, $statuses, $officeFilter, $office, $sgMin) {
                return $this->applyEmployeeFilters(
                    DB::table('employees')->where('REGION', $reg)->where('SG', '>=', $sgMin),
                    null, $statuses, $officeFilter, $office
                );
            };

            $regionsCompleted[] = $regBase()
                ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
                ->where('emp_hours.total_hours', '>=', 40)
                ->distinct()->count('employees.EMPCODE');

            $regionsInProgress[] = $regBase()
                ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
                ->where('emp_hours.total_hours', '<', 40)
                ->where('emp_hours.total_hours', '>', 0)
                ->distinct()->count('employees.EMPCODE');
        }

        return response()->json([
            'total'               => $totalEmployees,
            'completed'           => $completed,
            'in_progress'         => $inProgress,
            'completed_pct'       => $completedPct,
            'in_progress_pct'     => $inProgressPct,
            'sg_min'              => $sgMin,
            'region'              => $region,
            'office'              => $office,
            'year'                => $year,
            'statuses'            => $statuses,
            'office_filter'       => $officeFilter,
            'regions'             => $allRegions,
            'regions_completed'   => $regionsCompleted,
            'regions_in_progress' => $regionsInProgress,
        ]);
    }

    public function supervisoryComplianceList(Request $request)
    {
        $region       = $request->region;
        $statuses     = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office       = $request->office;   // NEW
        $year         = $request->year;     // NEW
        $sgMin        = $request->sg_min ?? 19;
        $type         = $request->type;

        $hoursSubquery = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('programs', 'batches.program_code', '=', 'programs.program_code')
            ->where('programs.type', 'SUPERVISORY/MANAGERIAL')
            ->where('participants.attendance', '!=', 'Absent')
            ->when($year && $year !== 'ALL', function ($q) use ($year) {
                $q->where('batches.date_start', 'LIKE', $year . '%');
            })
            ->select(
                'participants.empcode',
                DB::raw('SUM(CAST(batches.hours AS DECIMAL(10,2))) as total_hours')
            )
            ->groupBy('participants.empcode');

        $query = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin),
            $region, $statuses, $officeFilter, $office
        )
        ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'));

        if ($type === 'completed') {
            $query->where('emp_hours.total_hours', '>=', 40);
        } else {
            $query->where('emp_hours.total_hours', '<', 40)
                  ->where('emp_hours.total_hours', '>', 0);
        }

        $employees = $query
            ->select(
                'employees.EMPCODE', 'employees.LASTNAME', 'employees.FIRSTNAME', 'employees.MI',
                'employees.POSITION', 'employees.OFFICE/DIVISION as office_division',
                'employees.OFFICE', 'employees.REGION', 'employees.SG',
                'employees.PLANTILLA STATUS as plantilla_status',
                'emp_hours.total_hours',
            )
            ->orderBy('employees.LASTNAME')->orderBy('employees.FIRSTNAME')
            ->get();

        return response()->json([
            'type'      => $type,
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }

    public function treapCompliance(Request $request)
    {
        $region   = $request->region;
        $statuses = $request->plant_status;
        $office   = $request->office;
        $year     = $request->year;
 
        $allRegions = [
            'CO','NCR','R1','R2','R3','R4A','R4B','R5',
            'NIR','R6','R7','R8','R9','R10','R11','R12',
            'CAR','CARAGA',
        ];
 
        $today = now()->toDateString();
 
        // Subquery: participants with overdue TREAP requirement
        $baseParticipants = DB::table('participants')
            ->join('batches',      'participants.batch_id',  '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id',  '=', 'batches.id')
            ->join('employees',    'participants.empcode',   '=', 'employees.EMPCODE')
            ->where('requirements.title',              'TREAP')
            ->where('requirements.due_date',           '<=', $today)
            ->where('participants.attendance',         '!=', 'Absent');
 
        // Apply shared filters
        $baseParticipants = $this->applyEmployeeFilters(
            $baseParticipants, $region, $statuses, null, $office, 'employees.'
        );
 
        // Year filter on batch
        if ($year && $year !== 'ALL') {
            $baseParticipants->where('batches.date_start', 'LIKE', $year . '%');
        }
 
        // Total unique employees required to submit
        $totalEmployees = (clone $baseParticipants)
            ->distinct()
            ->count('participants.empcode');
 
        // Submitted: may submission na may title match sa 'TREAP' sa same batch
        $submittedEmployees = (clone $baseParticipants)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('submissions')
                    ->whereColumn('submissions.participant_id', 'participants.id')
                    ->whereColumn('submissions.batch_id',       'batches.id')
                    ->whereColumn('submissions.program_code',   'batches.program_code')
                    ->whereIn('submissions.requirement_id',
                        DB::table('requirements')
                            ->whereColumn('requirements.batch_id', 'batches.id')
                            ->where('requirements.title', 'TREAP')
                            ->select('requirements.id')
                    );
            })
            ->distinct()
            ->count('participants.empcode');
 
        $notSubmitted        = $totalEmployees - $submittedEmployees;
        $submittedPct        = $totalEmployees > 0 ? round(($submittedEmployees / $totalEmployees) * 100, 1) : 0;
        $notSubmittedPct     = $totalEmployees > 0 ? round(($notSubmitted       / $totalEmployees) * 100, 1) : 0;
 
        // ── Regional breakdown ────────────────────────────────────────────────
        $regionsSubmitted    = [];
        $regionsNotSubmitted = [];
 
        foreach ($allRegions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsSubmitted[]    = 0;
                $regionsNotSubmitted[] = 0;
                continue;
            }
 
            $regBase = DB::table('participants')
                ->join('batches',      'participants.batch_id', '=', 'batches.id')
                ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
                ->join('employees',    'participants.empcode',  '=', 'employees.EMPCODE')
                ->where('requirements.title',      'TREAP')
                ->where('requirements.due_date',   '<=', $today)
                ->where('participants.attendance', '!=', 'Absent')
                ->where('employees.REGION',        $reg);
 
            $regBase = $this->applyEmployeeFilters(
                $regBase, null, $statuses, null, $office, 'employees.'
            );
 
            if ($year && $year !== 'ALL') {
                $regBase->where('batches.date_start', 'LIKE', $year . '%');
            }
 
            $regTotal = (clone $regBase)->distinct()->count('participants.empcode');
 
            $regSubmitted = (clone $regBase)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('submissions')
                        ->whereColumn('submissions.participant_id', 'participants.id')
                        ->whereColumn('submissions.batch_id',       'batches.id')
                        ->whereColumn('submissions.program_code',   'batches.program_code')
                        ->whereIn('submissions.requirement_id',
                            DB::table('requirements')
                                ->whereColumn('requirements.batch_id', 'batches.id')
                                ->where('requirements.title', 'TREAP')
                                ->select('requirements.id')
                        );
                })
                ->distinct()
                ->count('participants.empcode');
 
            $regionsSubmitted[]    = $regSubmitted;
            $regionsNotSubmitted[] = $regTotal - $regSubmitted;
        }
 
        return response()->json([
            'total'                 => $totalEmployees,
            'submitted'             => $submittedEmployees,
            'not_submitted'         => $notSubmitted,
            'submitted_pct'         => $submittedPct,
            'not_submitted_pct'     => $notSubmittedPct,
            'regions'               => $allRegions,
            'regions_submitted'     => $regionsSubmitted,
            'regions_not_submitted' => $regionsNotSubmitted,
        ]);
    }

    public function treapComplianceList(Request $request)
    {
        $region   = $request->region;
        $statuses = $request->plant_status;
        $office   = $request->office;
        $year     = $request->year;
        $reg      = $request->reg;   // specific region para sa bar chart click
        $type     = $request->type;  // 'submitted' o 'not_submitted'
 
        $today = now()->toDateString();
 
        $query = DB::table('participants')
            ->join('batches',      'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees',    'participants.empcode',  '=', 'employees.EMPCODE')
            ->where('requirements.title',      'TREAP')
            ->where('requirements.due_date',   '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');
 
        $query = $this->applyEmployeeFilters(
            $query, $region, $statuses, null, $office, 'employees.'
        );
 
        if ($year && $year !== 'ALL') {
            $query->where('batches.date_start', 'LIKE', $year . '%');
        }
 
        // Filter by specific region (from bar chart click)
        if ($reg && $reg !== 'ALL') {
            $query->where('employees.REGION', $reg);
        }
 
        // Filter submitted vs not submitted
        $submittedCondition = function ($q) {
            $q->select(DB::raw(1))
                ->from('submissions')
                ->whereColumn('submissions.participant_id', 'participants.id')
                ->whereColumn('submissions.batch_id',       'batches.id')
                ->whereColumn('submissions.program_code',   'batches.program_code')
                ->whereIn('submissions.requirement_id',
                    DB::table('requirements')
                        ->whereColumn('requirements.batch_id', 'batches.id')
                        ->where('requirements.title', 'TREAP')
                        ->select('requirements.id')
                );
        };
 
        if ($type === 'submitted') {
            $query->whereExists($submittedCondition);
        } else {
            $query->whereNotExists($submittedCondition);
        }
 
        $employees = $query
            ->select(
                'employees.EMPCODE as empcode',
                'employees.LASTNAME as lastname',
                'employees.FIRSTNAME as firstname',
                'employees.MI as mi',
                'employees.POSITION as position',
                DB::raw('employees.`OFFICE/DIVISION` as office_division'),
                'employees.OFFICE as office',
                'employees.REGION as region',
                DB::raw('employees.`PLANTILLA STATUS` as plantilla_status'),
                'batches.batch as batch_name',
                'batches.date_end as date_end',
                'requirements.due_date as due_date',
            )
            ->distinct()
            ->orderBy('employees.LASTNAME')
            ->orderBy('employees.FIRSTNAME')
            ->get()
            ->map(function ($e) {
                $mi   = trim($e->mi ?? '');
                $name = trim($e->firstname . ($mi ? ' ' . $mi : '') . ' ' . $e->lastname);
                return [
                    'empcode'          => $e->empcode,
                    'name'             => $name,
                    'position'         => $e->position,
                    'office_division'  => $e->office_division,
                    'office'           => $e->office,
                    'region'           => $e->region,
                    'plantilla_status' => $e->plantilla_status,
                    'batch_name'       => $e->batch_name,
                    'date_end'         => $e->date_end,
                    'due_date'         => $e->due_date,
                ];
            });
 
        return response()->json([
            'type'      => $type,
            'region'    => $reg ?? 'ALL',
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }


}