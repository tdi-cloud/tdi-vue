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

    private function applyEmployeeFilters($query, $region, $statuses, $officeFilter, $prefix = '')
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
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%ROD%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%ORD%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%PO-%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%DO%')
                        ->orWhere($prefix . 'OFFICE/DIVISION', 'LIKE', '%FASD%');
                });
            });
    }

    public function trainingCompliance(Request $request)
    {
        $region       = $request->region;
        $statuses     = $request->plant_status;
        $officeFilter = $request->office_filter;

        $allRegions = [
            'CO','NCR','R1','R2','R3','R4A','R4B','R5',
            'NIR','R6','R7','R8','R9','R10','R11','R12',
            'CAR','CARAGA',
        ];

        // TOTAL EMPLOYEES
        $totalEmployees = $this->applyEmployeeFilters(
            DB::table('employees'), $region, $statuses, $officeFilter
        )->count();

        // TRAINED EMPLOYEES
        $trainedEmployees = $this->applyEmployeeFilters(
            DB::table('employees')
                ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                ->join('batches', 'participants.batch_id', '=', 'batches.id'),
            $region, $statuses, $officeFilter, 'employees.'
        )
            ->where('participants.attendance', '!=', 'Absent')
            ->where('batches.hours', '>=', 8)
            ->distinct()
            ->count('employees.EMPCODE');

        $notTrained = $totalEmployees - $trainedEmployees;

        $trainedPercentage = $totalEmployees > 0
            ? round(($trainedEmployees / $totalEmployees) * 100, 2)
            : 0;

        $notTrainedPercentage = $totalEmployees > 0
            ? round(($notTrained / $totalEmployees) * 100, 2)
            : 0;

        // REGIONAL BREAKDOWN (always all regions)
        $regionsBreakdown = [];

        foreach ($allRegions as $reg) {

            if ($region && $region !== 'ALL' && $reg !== $region) {
                $regionsBreakdown[] = ['total' => 0, 'trained' => 0, 'not_trained' => 0];
                continue;
            }

            $regTotal = $this->applyEmployeeFilters(
                DB::table('employees')->where('REGION', $reg),
                null, $statuses, $officeFilter
            )->count();

            $regTrained = $this->applyEmployeeFilters(
                DB::table('employees')
                    ->join('participants', 'employees.EMPCODE', '=', 'participants.empcode')
                    ->join('batches', 'participants.batch_id', '=', 'batches.id')
                    ->where('employees.REGION', $reg),
                null, $statuses, $officeFilter, 'employees.'
            )
                ->where('participants.attendance', '!=', 'Absent')
                ->where('batches.hours', '>=', 8)
                ->distinct()
                ->count('employees.EMPCODE');

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
        $type         = $request->type === 'trained' ? 'trained' : 'not_trained';

        $query = $this->applyEmployeeFilters(
            DB::table('employees'), $region, $statuses, $officeFilter
        );

        // Correlated subquery: may valid training ba ang empleyadong ito?
        $trainingExists = function ($q) {
            $q->select(DB::raw(1))
                ->from('participants')
                ->join('batches', 'participants.batch_id', '=', 'batches.id')
                ->whereColumn('participants.empcode', 'employees.EMPCODE')
                ->where('participants.attendance', '!=', 'Absent')
                ->where('batches.hours', '>=', 8);
        };

        if ($type === 'trained') {
            $query->whereExists($trainingExists);
        } else {
            $query->whereNotExists($trainingExists);
        }

        $employees = $query
            ->select(
                'EMPCODE',
                'LASTNAME',
                'FIRSTNAME',
                'MI',
                'POSITION',
                'OFFICE/DIVISION as office_division',
                'REGION',
                'PLANTILLA STATUS as plantilla_status',
            )
            ->orderBy('LASTNAME')
            ->orderBy('FIRSTNAME')
            ->get();

        return response()->json([
            'type'      => $type,
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }

    /**
 * Stats para sa Supervisory/Managerial Training Compliance card.
 * Target: 40 cumulative hours sa SUPERVISORY/MANAGERIAL program type.
 */
public function supervisoryCompliance(Request $request)
{
    $region       = $request->region;
    $statuses     = $request->plant_status;
    $officeFilter = $request->office_filter;
    $sgMin        = $request->sg_min ?? 19; // default: SG > 18

    $allRegions = [
        'CO','NCR','R1','R2','R3','R4A','R4B','R5',
        'NIR','R6','R7','R8','R9','R10','R11','R12',
        'CAR','CARAGA',
    ];

    // BASE: employees na SG > $sgMin (or >= depending on "greater than 18" = SG 19+)
    $baseQuery = function () use ($region, $statuses, $officeFilter, $sgMin) {
        return $this->applyEmployeeFilters(
            DB::table('employees')->where('SG', '>', $sgMin - 1), // SG >= sgMin
            $region, $statuses, $officeFilter
        );
    };

    $totalEmployees = $baseQuery()->count();

    // Subquery: sum ng hours ng SUPERVISORY/MANAGERIAL na attended na participants
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

    // COMPLETED: 40 hours+
    $completed = $baseQuery()
        ->joinSub($hoursSubquery, 'emp_hours', function ($join) {
            $join->on('employees.EMPCODE', '=', 'emp_hours.empcode');
        })
        ->where('emp_hours.total_hours', '>=', 40)
        ->distinct()
        ->count('employees.EMPCODE');

    // IN PROGRESS: may hours pero < 40
    $inProgress = $baseQuery()
        ->joinSub($hoursSubquery, 'emp_hours', function ($join) {
            $join->on('employees.EMPCODE', '=', 'emp_hours.empcode');
        })
        ->where('emp_hours.total_hours', '<', 40)
        ->where('emp_hours.total_hours', '>', 0)
        ->distinct()
        ->count('employees.EMPCODE');

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

        $regBase = function () use ($reg, $statuses, $officeFilter, $sgMin) {
            return $this->applyEmployeeFilters(
                DB::table('employees')
                    ->where('REGION', $reg)
                    ->where('SG', '>', $sgMin - 1),
                null, $statuses, $officeFilter
            );
        };

        $regCompleted = $regBase()
            ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '>=', 40)
            ->distinct()->count('employees.EMPCODE');

        $regInProgress = $regBase()
            ->joinSub($hoursSubquery, 'emp_hours', fn($j) => $j->on('employees.EMPCODE', '=', 'emp_hours.empcode'))
            ->where('emp_hours.total_hours', '<', 40)
            ->where('emp_hours.total_hours', '>', 0)
            ->distinct()->count('employees.EMPCODE');

        $regionsCompleted[]  = $regCompleted;
        $regionsInProgress[] = $regInProgress;
    }

    return response()->json([
        'total'               => $totalEmployees,
        'completed'           => $completed,
        'in_progress'         => $inProgress,
        'completed_pct'       => $completedPct,
        'in_progress_pct'     => $inProgressPct,
        'sg_min'              => $sgMin,
        'region'              => $region,
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
        $sgMin        = $request->sg_min ?? 19;
        $type         = $request->type; // 'completed' | 'in_progress'

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
            DB::table('employees')->where('SG', '>', $sgMin - 1),
            $region, $statuses, $officeFilter
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
                'employees.EMPCODE',
                'employees.LASTNAME',
                'employees.FIRSTNAME',
                'employees.MI',
                'employees.POSITION',
                'employees.OFFICE/DIVISION as office_division',
                'employees.REGION',
                'employees.SG',
                'employees.PLANTILLA STATUS as plantilla_status',
                'emp_hours.total_hours',
            )
            ->orderBy('employees.LASTNAME')
            ->orderBy('employees.FIRSTNAME')
            ->get();

        return response()->json([
            'type'      => $type,
            'count'     => $employees->count(),
            'employees' => $employees,
        ]);
    }
}