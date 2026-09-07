<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Program;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'summary' => [
                'employees' => Employee::count(),
                'programs' => Program::count(),
                'active_batches' => Batch::where('status', 'Active')->count(),
                'pending_submissions' => Submission::where('status', 'Pending')->count(),
            ],
        ]);
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
                $q->where($prefix.'REGION', $region);
            })
            ->when(! empty($statuses), function ($q) use ($statuses, $prefix) {
                $q->whereIn($prefix.'PLANTILLA STATUS', $statuses);
            })
            ->when($officeFilter === 'OPCR', function ($q) use ($prefix) {
                $q->where(function ($query) use ($prefix) {
                    $query->where($prefix.'OFFICE/DIVISION', 'LIKE', 'CO-%')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-ORD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-ROD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-FASD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-PO-%')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-DO-%');
                });
            })
            ->when($office && $office !== 'ALL', function ($q) use ($office, $prefix) {
                $q->where($prefix.'OFFICE', $office);
            });
    }

    /**
     * Naka-scope sa isang date range ($dateFrom/$dateTo, parehong optional,
     * 'Y-m-d' na format) — ginagamit lang ng "Reports" (Download CSV) na
     * feature (Past 7/30/90 days o custom range). String comparison lang
     * (`date_start` ay naka-store bilang 'Y-m-d' string sa DB), kaya sapat
     * na ang lexicographic >=/<= — parehong MySQL at SQLite compatible.
     */
    private function applyDateRangeFilter($query, $dateFrom, $dateTo, string $column = 'batches.date_start')
    {
        return $query
            ->when($dateFrom, function ($q) use ($dateFrom, $column) {
                $q->where($column, '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo, $column) {
                $q->where($column, '<=', $dateTo);
            });
    }

    /**
     * Isang CSV — lahat ng programs kasama ang mga batch, participants, at
     * attendance nila — isang row kada participant enrollment, naka-scope
     * sa parehong shared filters (target/region/office/plantilla status) at
     * sa napiling date range mula sa "Reports" modal.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office = $request->office;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $query = DB::table('programs as prog')
            ->join('batches as b', 'prog.program_code', '=', 'b.program_code')
            ->join('participants as p', 'p.batch_id', '=', 'b.id')
            ->leftJoin('employees as e', 'e.EMPCODE', '=', 'p.empcode')
            ->select(
                'prog.program_code', 'prog.title as program_title', 'prog.type as program_type',
                'b.batch as batch_label', 'b.status as batch_status',
                'b.date_start as batch_date_start', 'b.date_end as batch_date_end',
                'p.empcode', 'e.FIRSTNAME as firstname', 'e.MI as mi', 'e.LASTNAME as lastname',
                DB::raw('e.`OFFICE/DIVISION` as office_division'), 'e.REGION as region', 'e.SG as sg',
                'p.attendance', 'p.hours',
            );

        $query = $this->applyEmployeeFilters($query, $region, $statuses, $officeFilter, $office, 'e.');
        $query = $this->applyDateRangeFilter($query, $dateFrom, $dateTo, 'b.date_start');

        $rows = $query
            ->orderBy('prog.title')->orderBy('b.batch')->orderBy('e.LASTNAME')
            ->get();

        $filename = 'programs_batches_participants_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Program Code', 'Program Title', 'Program Type',
                'Batch', 'Batch Status', 'Batch Start', 'Batch End',
                'EMPCODE', 'Participant Name', 'Office/Division', 'Region', 'SG',
                'Attendance', 'Hours',
            ]);

            foreach ($rows as $row) {
                $mi = trim($row->mi ?? '');
                $mi = $mi !== '' ? ' '.rtrim($mi, '.').'.' : '';
                $name = trim(($row->firstname ?? '').$mi.' '.($row->lastname ?? ''));

                fputcsv($out, [
                    $row->program_code,
                    $row->program_title,
                    $row->program_type,
                    $row->batch_label,
                    $row->batch_status,
                    $row->batch_date_start,
                    $row->batch_date_end,
                    $row->empcode,
                    $name !== '' ? $name : null,
                    $row->office_division,
                    $row->region,
                    $row->sg,
                    $row->attendance,
                    $row->hours,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    public function trainingCompliance(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office = $request->office;
        $sgMin = $request->sg_min ?? 1;

        $allRegions = [
            'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
            'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
            'CAR', 'CARAGA',
        ];

        $totalEmployees = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin), $region, $statuses, $officeFilter, $office
        )->count();

        $trainedQuery = $this->applyEmployeeFilters(
            DB::table('employees')
                ->where('employees.SG', '>=', $sgMin)
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id'),
            $region, $statuses, $officeFilter, $office, 'employees.'
        )
            ->where('participants.attendance', '!=', 'Absent')
            ->whereRaw('CAST(batches.hours AS DECIMAL(10,2)) >= 8');

        $trainedEmployees = $trainedQuery->distinct()->count('employees.EMPCODE');

        $notTrained = $totalEmployees - $trainedEmployees;
        $trainedPercentage = $totalEmployees > 0 ? round(($trainedEmployees / $totalEmployees) * 100, 2) : 0;
        $notTrainedPercentage = $totalEmployees > 0 ? round(($notTrained / $totalEmployees) * 100, 2) : 0;

        // 2 GROUP BY na query lang (sa halip na 2 query * 18 region = 36
        // hiwalay na query) — ito ang pinaka-malaking dahilan kung bakit
        // mabagal mag-load ang Training Compliance na card sa Dashboard.
        $totalByRegion = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin), null, $statuses, $officeFilter, $office
        )
            ->select('REGION as region', DB::raw('COUNT(*) as total'))
            ->groupBy('REGION')
            ->pluck('total', 'region');

        $trainedByRegion = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->where('employees.SG', '>=', $sgMin),
            null, $statuses, $officeFilter, $office, 'employees.'
        )
            ->where('participants.attendance', '!=', 'Absent')
            ->whereRaw('CAST(batches.hours AS DECIMAL(10,2)) >= 8')
            ->select('employees.REGION as region')
            ->selectRaw('COUNT(DISTINCT employees.EMPCODE) as trained')
            ->groupBy('employees.REGION')
            ->pluck('trained', 'region');

        $regionsTrained = [];
        $regionsNotTrained = [];

        foreach ($allRegions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsTrained[] = 0;
                $regionsNotTrained[] = 0;

                continue;
            }

            $regTotal = (int) ($totalByRegion[$reg] ?? 0);
            $regTrained = (int) ($trainedByRegion[$reg] ?? 0);
            $regionsTrained[] = $regTrained;
            $regionsNotTrained[] = $regTotal - $regTrained;
        }

        return response()->json([
            'total' => $totalEmployees,
            'trained' => $trainedEmployees,
            'not_trained' => $notTrained,
            'trained_percentage' => $trainedPercentage,
            'not_trained_percentage' => $notTrainedPercentage,
            'statuses' => $statuses,
            'region' => $region,
            'office_filter' => $officeFilter,
            'office' => $office,
            'sg_min' => $sgMin,
            'regions' => $allRegions,
            'regions_trained' => $regionsTrained,
            'regions_not_trained' => $regionsNotTrained,
        ]);
    }

    public function trainingComplianceList(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office = $request->office;
        $sgMin = $request->sg_min ?? 1;
        $type = $request->type === 'trained' ? 'trained' : 'not_trained';

        $query = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin), $region, $statuses, $officeFilter, $office
        );

        $trainingExists = function ($q) {
            $q->select(DB::raw(1))
                ->from('participants')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->whereColumn('participants.empcode', 'employees.EMPCODE')
                ->where('participants.attendance', '!=', 'Absent')
                ->whereRaw('CAST(batches.hours AS DECIMAL(10,2)) >= 8');
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
                'OFFICE', 'REGION', 'SG', 'PLANTILLA STATUS as plantilla_status',
            )
            ->orderBy('LASTNAME')->orderBy('FIRSTNAME')
            ->get();

        return response()->json([
            'type' => $type,
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    public function supervisoryCompliance(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office = $request->office;
        $sgMin = $request->sg_min ?? 19;

        $allRegions = [
            'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
            'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
            'CAR', 'CARAGA',
        ];

        $hoursSubquery = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('programs', 'batches.program_code', '=', 'programs.program_code')
            ->where('programs.type', 'SUPERVISORY/MANAGERIAL')
            ->where('participants.attendance', '!=', 'Absent')
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
            ->joinSub($hoursSubquery, 'emp_hours', fn ($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '>=', 40)
            ->distinct()->count('employees.EMPCODE');

        $inProgress = $baseQuery()
            ->joinSub($hoursSubquery, 'emp_hours', fn ($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '<', 40)
            ->where('emp_hours.total_hours', '>', 0)
            ->distinct()->count('employees.EMPCODE');

        $completedPct = $totalEmployees > 0 ? round(($completed / $totalEmployees) * 100, 2) : 0;
        $inProgressPct = $totalEmployees > 0 ? round(($inProgress / $totalEmployees) * 100, 2) : 0;

        // Isang GROUP BY na query lang (sa halip na 2 query * 18 region =
        // 36 hiwalay na query) — ito ang pinaka-malaking dahilan kung bakit
        // mabagal mag-load ang Supervisory/Managerial na card sa Dashboard.
        $regionRows = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin),
            null, $statuses, $officeFilter, $office
        )
            ->joinSub($hoursSubquery, 'emp_hours', fn ($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->select('employees.REGION as region')
            ->selectRaw('COUNT(DISTINCT CASE WHEN emp_hours.total_hours >= 40 THEN employees.EMPCODE END) as completed')
            ->selectRaw('COUNT(DISTINCT CASE WHEN emp_hours.total_hours < 40 AND emp_hours.total_hours > 0 THEN employees.EMPCODE END) as in_progress')
            ->groupBy('employees.REGION')
            ->get()
            ->keyBy('region');

        $regionsCompleted = [];
        $regionsInProgress = [];

        foreach ($allRegions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsCompleted[] = 0;
                $regionsInProgress[] = 0;

                continue;
            }

            $row = $regionRows->get($reg);
            $regionsCompleted[] = (int) ($row->completed ?? 0);
            $regionsInProgress[] = (int) ($row->in_progress ?? 0);
        }

        return response()->json([
            'total' => $totalEmployees,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'completed_pct' => $completedPct,
            'in_progress_pct' => $inProgressPct,
            'sg_min' => $sgMin,
            'region' => $region,
            'office' => $office,
            'statuses' => $statuses,
            'office_filter' => $officeFilter,
            'regions' => $allRegions,
            'regions_completed' => $regionsCompleted,
            'regions_in_progress' => $regionsInProgress,
        ]);
    }

    public function supervisoryComplianceList(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $officeFilter = $request->office_filter;
        $office = $request->office;
        $sgMin = $request->sg_min ?? 19;
        $type = $request->type;

        $hoursSubquery = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('programs', 'batches.program_code', '=', 'programs.program_code')
            ->where('programs.type', 'SUPERVISORY/MANAGERIAL')
            ->where('participants.attendance', '!=', 'Absent')
            ->select(
                'participants.empcode',
                DB::raw('SUM(CAST(batches.hours AS DECIMAL(10,2))) as total_hours')
            )
            ->groupBy('participants.empcode');

        $query = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin),
            $region, $statuses, $officeFilter, $office
        )
            ->joinSub($hoursSubquery, 'emp_hours', fn ($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'));

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
            'type' => $type,
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    /**
     * Per-region na total/submitted breakdown gamit ang 2 GROUPED na query
     * lang (sa halip na 2 query * 18 region = 36 hiwalay na query) — dating
     * ginagawa ito sa isang foreach loop ng treap/reap/tdorCompliance(),
     * ang pinaka-malaking dahilan kung bakit mabagal mag-load ang mga
     * "compliance" na card sa Dashboard.
     *
     * @return array{0: array<int>, 1: array<int>} [regionsSubmitted, regionsNotSubmitted]
     */
    private function regionSubmissionBreakdown($baseParticipants, \Closure $submittedCond, array $allRegions, $filteredRegion): array
    {
        $totalByRegion = (clone $baseParticipants)
            ->select('employees.REGION as region')
            ->selectRaw('COUNT(DISTINCT participants.empcode) as total')
            ->groupBy('employees.REGION')
            ->pluck('total', 'region');

        $submittedByRegion = (clone $baseParticipants)
            ->whereExists($submittedCond)
            ->select('employees.REGION as region')
            ->selectRaw('COUNT(DISTINCT participants.empcode) as total')
            ->groupBy('employees.REGION')
            ->pluck('total', 'region');

        $regionsSubmitted = [];
        $regionsNotSubmitted = [];

        foreach ($allRegions as $reg) {
            if ($filteredRegion && $filteredRegion !== 'ALL' && $reg !== $filteredRegion) {
                $regionsSubmitted[] = 0;
                $regionsNotSubmitted[] = 0;

                continue;
            }

            $regTotal = (int) ($totalByRegion[$reg] ?? 0);
            $regSubmitted = (int) ($submittedByRegion[$reg] ?? 0);
            $regionsSubmitted[] = $regSubmitted;
            $regionsNotSubmitted[] = $regTotal - $regSubmitted;
        }

        return [$regionsSubmitted, $regionsNotSubmitted];
    }

    // ── Shared submitted condition builder ────────────────────────────────────
    // Ginagamit ang empcode matching (hindi participant_id) para ma-catch
    // ang mga submission kahit sa ibang batch ng parehong program nag-submit.

    private function submittedCondition(string $title): \Closure
    {
        return function ($q) use ($title) {
            $q->select(DB::raw(1))
                ->from('submissions')
                ->join('requirements as req_sub', 'submissions.requirement_id', '=', 'req_sub.id')
                ->join('participants as p2', 'submissions.participant_id', '=', 'p2.id')
                ->whereColumn('p2.empcode', 'participants.empcode')
                ->where('req_sub.title', $title);
        };
    }

    // ── TREAP Compliance ──────────────────────────────────────────────────────

    public function treapCompliance(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;

        $allRegions = [
            'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
            'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
            'CAR', 'CARAGA',
        ];

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('TREAP');

        $baseParticipants = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'TREAP')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $baseParticipants = $this->applyEmployeeFilters(
            $baseParticipants, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        $totalEmployees = (clone $baseParticipants)->distinct()->count('participants.empcode');
        $submittedEmployees = (clone $baseParticipants)->whereExists($submittedCond)->distinct()->count('participants.empcode');

        $notSubmitted = $totalEmployees - $submittedEmployees;
        $submittedPct = $totalEmployees > 0 ? round(($submittedEmployees / $totalEmployees) * 100, 1) : 0;
        $notSubmittedPct = $totalEmployees > 0 ? round(($notSubmitted / $totalEmployees) * 100, 1) : 0;

        [$regionsSubmitted, $regionsNotSubmitted] = $this->regionSubmissionBreakdown(
            $baseParticipants, $submittedCond, $allRegions, $region
        );

        return response()->json([
            'total' => $totalEmployees,
            'submitted' => $submittedEmployees,
            'not_submitted' => $notSubmitted,
            'submitted_pct' => $submittedPct,
            'not_submitted_pct' => $notSubmittedPct,
            'regions' => $allRegions,
            'regions_submitted' => $regionsSubmitted,
            'regions_not_submitted' => $regionsNotSubmitted,
        ]);
    }

    public function treapComplianceList(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;
        $reg = $request->reg;
        $type = $request->type;

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('TREAP');

        $query = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'TREAP')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $query = $this->applyEmployeeFilters(
            $query, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        if ($reg && $reg !== 'ALL') {
            $query->where('employees.REGION', $reg);
        }

        if ($type === 'submitted') {
            $query->whereExists($submittedCond);
        } else {
            $query->whereNotExists($submittedCond);
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
                $mi = trim($e->mi ?? '');
                $name = trim($e->firstname.($mi ? ' '.$mi : '').' '.$e->lastname);

                return [
                    'empcode' => $e->empcode,
                    'name' => $name,
                    'position' => $e->position,
                    'office_division' => $e->office_division,
                    'office' => $e->office,
                    'region' => $e->region,
                    'plantilla_status' => $e->plantilla_status,
                    'batch_name' => $e->batch_name,
                    'date_end' => $e->date_end,
                    'due_date' => $e->due_date,
                ];
            });

        return response()->json([
            'type' => $type,
            'region' => $reg ?? 'ALL',
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    // ── REAP Compliance ───────────────────────────────────────────────────────

    public function reapCompliance(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;

        $allRegions = [
            'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
            'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
            'CAR', 'CARAGA',
        ];

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('REAP');

        $baseParticipants = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'REAP')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $baseParticipants = $this->applyEmployeeFilters(
            $baseParticipants, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        $totalEmployees = (clone $baseParticipants)->distinct()->count('participants.empcode');
        $submittedEmployees = (clone $baseParticipants)->whereExists($submittedCond)->distinct()->count('participants.empcode');

        $notSubmitted = $totalEmployees - $submittedEmployees;
        $submittedPct = $totalEmployees > 0 ? round(($submittedEmployees / $totalEmployees) * 100, 1) : 0;
        $notSubmittedPct = $totalEmployees > 0 ? round(($notSubmitted / $totalEmployees) * 100, 1) : 0;

        [$regionsSubmitted, $regionsNotSubmitted] = $this->regionSubmissionBreakdown(
            $baseParticipants, $submittedCond, $allRegions, $region
        );

        return response()->json([
            'total' => $totalEmployees,
            'submitted' => $submittedEmployees,
            'not_submitted' => $notSubmitted,
            'submitted_pct' => $submittedPct,
            'not_submitted_pct' => $notSubmittedPct,
            'regions' => $allRegions,
            'regions_submitted' => $regionsSubmitted,
            'regions_not_submitted' => $regionsNotSubmitted,
        ]);
    }

    public function reapComplianceList(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;
        $reg = $request->reg;
        $type = $request->type;

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('REAP');

        $query = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'REAP')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $query = $this->applyEmployeeFilters(
            $query, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        if ($reg && $reg !== 'ALL') {
            $query->where('employees.REGION', $reg);
        }

        if ($type === 'submitted') {
            $query->whereExists($submittedCond);
        } else {
            $query->whereNotExists($submittedCond);
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
                $mi = trim($e->mi ?? '');
                $name = trim($e->firstname.($mi ? ' '.$mi : '').' '.$e->lastname);

                return [
                    'empcode' => $e->empcode,
                    'name' => $name,
                    'position' => $e->position,
                    'office_division' => $e->office_division,
                    'office' => $e->office,
                    'region' => $e->region,
                    'plantilla_status' => $e->plantilla_status,
                    'batch_name' => $e->batch_name,
                    'date_end' => $e->date_end,
                    'due_date' => $e->due_date,
                ];
            });

        return response()->json([
            'type' => $type,
            'region' => $reg ?? 'ALL',
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    // ── TDOR Compliance ───────────────────────────────────────────────────────

    public function tdorCompliance(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;

        $allRegions = [
            'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
            'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
            'CAR', 'CARAGA',
        ];

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('TDOR');

        $baseParticipants = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'TDOR')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $baseParticipants = $this->applyEmployeeFilters(
            $baseParticipants, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        $totalEmployees = (clone $baseParticipants)->distinct()->count('participants.empcode');
        $submittedEmployees = (clone $baseParticipants)->whereExists($submittedCond)->distinct()->count('participants.empcode');

        $notSubmitted = $totalEmployees - $submittedEmployees;
        $submittedPct = $totalEmployees > 0 ? round(($submittedEmployees / $totalEmployees) * 100, 1) : 0;
        $notSubmittedPct = $totalEmployees > 0 ? round(($notSubmitted / $totalEmployees) * 100, 1) : 0;

        [$regionsSubmitted, $regionsNotSubmitted] = $this->regionSubmissionBreakdown(
            $baseParticipants, $submittedCond, $allRegions, $region
        );

        return response()->json([
            'total' => $totalEmployees,
            'submitted' => $submittedEmployees,
            'not_submitted' => $notSubmitted,
            'submitted_pct' => $submittedPct,
            'not_submitted_pct' => $notSubmittedPct,
            'regions' => $allRegions,
            'regions_submitted' => $regionsSubmitted,
            'regions_not_submitted' => $regionsNotSubmitted,
        ]);
    }

    public function tdorComplianceList(Request $request)
    {
        $region = $request->region;
        $statuses = $request->plant_status;
        $office = $request->office;
        $officeFilter = $request->office_filter;
        $reg = $request->reg;
        $type = $request->type;

        $today = now()->toDateString();
        $submittedCond = $this->submittedCondition('TDOR');

        $query = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->join('requirements', 'requirements.batch_id', '=', 'batches.id')
            ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
            ->where('requirements.title', 'TDOR')
            ->where('requirements.due_date', '<=', $today)
            ->where('participants.attendance', '!=', 'Absent');

        $query = $this->applyEmployeeFilters(
            $query, $region, $statuses, $officeFilter, $office, 'employees.'
        );

        if ($reg && $reg !== 'ALL') {
            $query->where('employees.REGION', $reg);
        }

        if ($type === 'submitted') {
            $query->whereExists($submittedCond);
        } else {
            $query->whereNotExists($submittedCond);
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
                $mi = trim($e->mi ?? '');
                $name = trim($e->firstname.($mi ? ' '.$mi : '').' '.$e->lastname);

                return [
                    'empcode' => $e->empcode,
                    'name' => $name,
                    'position' => $e->position,
                    'office_division' => $e->office_division,
                    'office' => $e->office,
                    'region' => $e->region,
                    'plantilla_status' => $e->plantilla_status,
                    'batch_name' => $e->batch_name,
                    'date_end' => $e->date_end,
                    'due_date' => $e->due_date,
                ];
            });

        return response()->json([
            'type' => $type,
            'region' => $reg ?? 'ALL',
            'count' => $employees->count(),
            'employees' => $employees,
        ]);
    }
}
