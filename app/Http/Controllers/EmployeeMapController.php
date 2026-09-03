<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\Concerns\FiltersEmployees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeMapController extends Controller
{
    use FiltersEmployees;

    /**
     * Status label thresholds para sa regional performance table / attention
     * panel — batay sa completion percentage ng rehiyon.
     */
    private function statusLabel(float $completionPct): string
    {
        if ($completionPct >= 85) {
            return 'Excellent';
        }
        if ($completionPct >= 70) {
            return 'Good';
        }
        if ($completionPct >= 50) {
            return 'Needs Monitoring';
        }

        return 'Needs Attention';
    }

    /**
     * "L&D Workforce Intelligence" na dashboard — 3D na mapa ng Pilipinas
     * kasama ang totoong per-region na training metrics, KPI summary, at
     * mga karagdagang panel (regional performance table, attention panel,
     * training coverage) — lahat galing sa parehong proven query patterns
     * na ginagamit na ng DashboardController/RequirementsTrackerController.
     */
    public function index(Request $request)
    {
        $region = $request->region;
        $office = $request->office;
        $year = $request->year;
        $statuses = $request->plant_status;
        $sgMin = $request->sg_min ?? 1;
        $today = now()->toDateString();

        $totalEmployees = $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>=', $sgMin), $region, $statuses, null, $office
        )->count();

        $currentlyInTraining = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->where('employees.SG', '>=', $sgMin)
                ->where('batches.status', 'Active')
                ->where('participants.attendance', '!=', 'Absent'),
            $region, $statuses, null, $office, 'employees.'
        )->distinct()->count('employees.EMPCODE');

        $trainedQuery = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->where('employees.SG', '>=', $sgMin)
                ->where('participants.attendance', '!=', 'Absent')
                ->whereRaw('CAST(batches.hours AS DECIMAL(10,2)) >= 8'),
            $region, $statuses, null, $office, 'employees.'
        )->when($year && $year !== 'ALL', fn ($q) => $q->where('batches.date_start', 'LIKE', $year.'%'));
        $trainingCompleted = $trainedQuery->distinct()->count('employees.EMPCODE');

        $reqBase = $this->applyEmployeeFilters(
            RequirementsTrackerController::missingRequirementsBaseQuery()->where('e.SG', '>=', $sgMin),
            $region, $statuses, null, $office, 'e.'
        );
        $requirementsPending = (clone $reqBase)->count();
        $needsAttention = (clone $reqBase)->whereDate('r.due_date', '<', $today)->count();

        $regionMetrics = [];
        foreach (self::$regions as $reg) {
            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionMetrics[] = $this->emptyRegionMetric($reg);

                continue;
            }

            $regionMetrics[] = $this->regionSnapshot($reg, $statuses, $office, $year, $sgMin);
        }

        $regionalPerformance = collect($regionMetrics)
            ->filter(fn ($r) => $r['total'] > 0)
            ->sortByDesc('completion_pct')
            ->values()
            ->map(function ($r, $i) {
                $r['rank'] = $i + 1;
                $r['status'] = $this->statusLabel($r['completion_pct']);

                return $r;
            });

        $attention = collect($regionMetrics)
            ->filter(fn ($r) => $r['total'] > 0 && ($r['overdue'] > 0 || $r['completion_pct'] < 50))
            ->sortByDesc('overdue')
            ->values()
            ->take(6);

        $trainingCoverageQuery = $this->applyEmployeeFilters(
            DB::table('participants')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->join('programs', 'batches.program_code', '=', 'programs.program_code')
                ->join('employees', 'participants.empcode', '=', 'employees.EMPCODE')
                ->where('employees.SG', '>=', $sgMin)
                ->where('participants.attendance', '!=', 'Absent')
                ->whereNotNull('programs.type')
                ->where('programs.type', '!=', ''),
            $region, $statuses, null, $office, 'employees.'
        )->when($year && $year !== 'ALL', fn ($q) => $q->where('batches.date_start', 'LIKE', $year.'%'));

        $trainingCoverage = $trainingCoverageQuery
            ->selectRaw('programs.type as type, COUNT(DISTINCT employees.EMPCODE) as total')
            ->groupBy('programs.type')
            ->orderByDesc('total')
            ->get();

        $plantillaStatuses = DB::table('employees')
            ->select('PLANTILLA STATUS')
            ->distinct()
            ->whereNotNull('PLANTILLA STATUS')
            ->where('PLANTILLA STATUS', '!=', '')
            ->orderBy('PLANTILLA STATUS')
            ->pluck('PLANTILLA STATUS');

        $regionCounts = collect($regionMetrics)
            ->filter(fn ($r) => $r['total'] > 0)
            ->map(fn ($r) => ['region' => $r['region'], 'total' => $r['total']])
            ->values();

        return Inertia::render('EmployeesMap/index', [
            'regionCounts' => $regionCounts,
            'totalEmployees' => $totalEmployees,
            'regionMetrics' => $regionMetrics,
            'kpi' => [
                'total_personnel' => $totalEmployees,
                'currently_in_training' => $currentlyInTraining,
                'training_completed' => $trainingCompleted,
                'requirements_pending' => $requirementsPending,
                'needs_attention' => $needsAttention,
            ],
            'regionalPerformance' => $regionalPerformance,
            'attention' => $attention,
            'trainingCoverage' => $trainingCoverage,
            'filters' => [
                'region' => $region ?: 'ALL',
                'office' => $office ?: 'ALL',
                'year' => $year ?: 'ALL',
                'plant_status' => $statuses ?: [],
                'sg_min' => $sgMin,
            ],
            'filterOptions' => [
                'regions' => self::$regions,
                'plantilla_statuses' => $plantillaStatuses,
            ],
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Listahan ng mga empleyado sa isang partikular na REGION (para sa side
     * panel na lumalabas kapag pinindot ang isang region block sa 3D mapa) —
     * kasama na ngayon ang "Regional Training Overview" mini metrics.
     */
    public function byRegion(Request $request)
    {
        $request->validate([
            'region' => 'required|string',
        ]);

        $region = $request->string('region')->toString();

        $query = Employee::query()->where('REGION', $region);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('EMPCODE', 'like', "%{$search}%")
                    ->orWhere('FIRSTNAME', 'like', "%{$search}%")
                    ->orWhere('LASTNAME', 'like', "%{$search}%")
                    ->orWhere('OFFICE/DIVISION', 'like', "%{$search}%");
            });
        }

        if ($request->filled('office') && $request->office !== 'all') {
            $query->where('OFFICE', $request->office);
        }

        $employees = $query
            ->orderBy('LASTNAME')
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        // Totoong (hindi fabricated) na training snapshot kada empleyado sa
        // kasalukuyang page lang (bounded, mura) — parehong pattern na
        // ginagamit na ng EmployeeProgressController::index().
        $empcodes = $employees->getCollection()->pluck('EMPCODE');
        $progressStats = DB::table('participants as p')
            ->join('batches as b', 'p.batch_id', '=', 'b.id')
            ->whereIn('p.empcode', $empcodes)
            ->where('p.attendance', '!=', 'Absent')
            ->selectRaw('p.empcode,
                COUNT(*) as programs_attended,
                SUM(CASE WHEN p.attendance = "Complete" THEN 1 ELSE 0 END) as programs_completed,
                SUM(CAST(b.hours AS DECIMAL(10,2))) as training_hours')
            ->groupBy('p.empcode')
            ->get()
            ->keyBy('empcode');

        $employees->through(function (Employee $employee) use ($progressStats) {
            $stats = $progressStats->get($employee->EMPCODE);
            $data = $employee->toArray();
            $data['programs_attended'] = $stats ? (int) $stats->programs_attended : 0;
            $data['programs_completed'] = $stats ? (int) $stats->programs_completed : 0;
            $data['training_hours'] = $stats ? round((float) $stats->training_hours, 1) : 0.0;

            return $data;
        });

        $officeBreakdown = Employee::query()
            ->where('REGION', $region)
            ->whereNotNull('OFFICE')
            ->where('OFFICE', '!=', '')
            ->selectRaw('OFFICE as office, count(*) as total')
            ->groupBy('OFFICE')
            ->orderByDesc('total')
            ->get();

        $overview = $this->regionSnapshot($region, null, null, null, 1);

        return response()->json([
            'employees' => $employees,
            'officeBreakdown' => $officeBreakdown,
            'overview' => $overview,
        ]);
    }

    private function emptyRegionMetric(string $reg): array
    {
        return [
            'region' => $reg,
            'total' => 0,
            'participation' => 0,
            'participation_pct' => 0,
            'completed' => 0,
            'completion_pct' => 0,
            'avg_hours' => 0,
            'pending' => 0,
            'overdue' => 0,
        ];
    }

    /**
     * Totoong (hindi hardcoded) na training snapshot ng isang region:
     * headcount, participation (nag-attend, kahit anong hours), completion
     * (ang proven na "trained" na definition sa DashboardController —
     * attendance != Absent AT batch hours >= 8), average training hours, at
     * bilang ng pending/overdue na requirements.
     */
    private function regionSnapshot(string $reg, $statuses, $office, $year, $sgMin): array
    {
        $today = now()->toDateString();

        $regTotal = $this->applyEmployeeFilters(
            DB::table('employees')->where('REGION', $reg)->where('SG', '>=', $sgMin),
            null, $statuses, null, $office
        )->count();

        $regParticipation = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->where('employees.REGION', $reg)
                ->where('employees.SG', '>=', $sgMin)
                ->where('participants.attendance', '!=', 'Absent'),
            null, $statuses, null, $office, 'employees.'
        )->distinct()->count('employees.EMPCODE');

        $regCompleted = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->where('employees.REGION', $reg)
                ->where('employees.SG', '>=', $sgMin)
                ->where('participants.attendance', '!=', 'Absent')
                ->whereRaw('CAST(batches.hours AS DECIMAL(10,2)) >= 8'),
            null, $statuses, null, $office, 'employees.'
        )
            ->when($year && $year !== 'ALL', fn ($q) => $q->where('batches.date_start', 'LIKE', $year.'%'))
            ->distinct()->count('employees.EMPCODE');

        $hoursSubquery = DB::table('participants')
            ->join('batches', 'participants.batch_id', '=', 'batches.id')
            ->where('participants.attendance', '!=', 'Absent')
            ->when($year && $year !== 'ALL', fn ($q) => $q->where('batches.date_start', 'LIKE', $year.'%'))
            ->select('participants.empcode', DB::raw('SUM(CAST(batches.hours AS DECIMAL(10,2))) as total_hours'))
            ->groupBy('participants.empcode');

        $avgHoursRow = $this->applyEmployeeFilters(
            DB::table('employees')->where('REGION', $reg)->where('SG', '>=', $sgMin),
            null, $statuses, null, $office
        )
            ->leftJoinSub($hoursSubquery, 'emp_hours', fn ($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->selectRaw('AVG(COALESCE(emp_hours.total_hours, 0)) as avg_hours')
            ->first();

        $regReqBase = $this->applyEmployeeFilters(
            RequirementsTrackerController::missingRequirementsBaseQuery()
                ->where('e.REGION', $reg)->where('e.SG', '>=', $sgMin),
            null, $statuses, null, $office, 'e.'
        );
        $regPending = (clone $regReqBase)->count();
        $regOverdue = (clone $regReqBase)->whereDate('r.due_date', '<', $today)->count();

        return [
            'region' => $reg,
            'total' => $regTotal,
            'participation' => $regParticipation,
            'participation_pct' => $regTotal > 0 ? round($regParticipation / $regTotal * 100, 1) : 0,
            'completed' => $regCompleted,
            'completion_pct' => $regTotal > 0 ? round($regCompleted / $regTotal * 100, 1) : 0,
            'avg_hours' => round((float) ($avgHoursRow->avg_hours ?? 0), 1),
            'pending' => $regPending,
            'overdue' => $regOverdue,
        ];
    }
}
