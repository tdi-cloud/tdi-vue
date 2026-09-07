<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementsTrackerController extends Controller
{
    /**
     * Performance note: ang `scopedQuery()` ay isang mabigat na query (4-way
     * join + isang correlated NOT EXISTS subquery) — dating tumatakbo ito ng
     * 4x kada page load (ang internal COUNT ng ->paginate(), ang SELECT
     * mismo, saka 2 pang hiwalay na ->count() para sa stats cards). Dito,
     * pinagsama ang total/overdue sa IISANG query (conditional aggregation
     * sa halip na 2 ->count() calls), at ang pagination ay ginawa nang
     * MANUAL gamit ang total na kinuha na natin, para hindi na mag-double
     * COUNT pa ang Laravel — 4 na query bawat load, naging 2 na lang.
     */
    public function index(Request $request)
    {
        $today = now()->toDateString();

        $statsRow = $this->scopedQuery($request)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN r.due_date < ? THEN 1 ELSE 0 END) as overdue', [$today])
            ->first();

        $total = (int) $statsRow->total;
        $overdue = (int) $statsRow->overdue;

        $query = $this->scopedQuery($request);
        if ($request->boolean('overdue_only')) {
            $query->whereDate('r.due_date', '<', $today);
        }

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $rows = $this->selectColumns($query)
            ->orderBy('r.due_date')
            ->forPage($currentPage, $perPage)
            ->get()
            ->map(fn ($row) => $this->decorateRow($row));

        $items = new LengthAwarePaginator(
            $rows,
            $request->boolean('overdue_only') ? $overdue : $total,
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
        $items->withQueryString();

        return Inertia::render('RequirementsTracker/index', [
            'items' => $items,
            'requirementTitles' => Requirement::query()->select('title')->distinct()->orderBy('title')->pluck('title'),
            'stats' => [
                'total_missing' => $total,
                'overdue' => $overdue,
                'on_time' => $total - $overdue,
            ],
            'filters' => $request->only(['search', 'requirement_title', 'overdue_only']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->scopedQuery($request);

        if ($request->boolean('overdue_only')) {
            $query->whereDate('r.due_date', '<', now()->toDateString());
        }

        $rows = $this->selectColumns($query)->orderBy('r.due_date')->get()
            ->map(fn ($row) => $this->decorateRow($row));

        $filename = 'post_training_requirements_tracker_'.now()->format('Ymd_His').'.csv';

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
                'EMPCODE', 'Employee Name', 'Office/Division', 'Region',
                'Program Code', 'Program Title', 'Batch', 'Batch End Date',
                'Requirement', 'Requirement Name', 'Due Date', 'Days Overdue',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->empcode,
                    $row->employee_name,
                    $row->office_division,
                    $row->region,
                    $row->program_code,
                    $row->program_title,
                    $row->batch_label,
                    $row->batch_date_end,
                    $row->requirement_title,
                    $row->requirement_name,
                    $row->due_date,
                    $row->days_overdue,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Requirements na "required" pero wala pang matching Submission
     * mula sa employee (via empcode) para dito, hindi absent ang participant.
     */
    private function scopedQuery(Request $request)
    {
        $query = DB::table('requirements as r')
            ->join('batches as b', 'r.batch_id', '=', 'b.id')
            ->join('programs as prog', 'b.program_code', '=', 'prog.program_code')
            ->join('participants as p', 'p.batch_id', '=', 'b.id')
            ->join('employees as e', 'e.EMPCODE', '=', 'p.empcode')
            ->where('r.is_required', true)
            ->where('p.attendance', '!=', 'Absent')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('submissions as s')
                    ->join('participants as p2', 's.participant_id', '=', 'p2.id')
                    ->whereColumn('s.requirement_id', 'r.id')
                    ->whereColumn('p2.empcode', 'p.empcode');
            });

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('e.FIRSTNAME', 'like', "%{$search}%")
                    ->orWhere('e.LASTNAME', 'like', "%{$search}%")
                    ->orWhere('e.EMPCODE', 'like', "%{$search}%")
                    ->orWhere('prog.title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('requirement_title')) {
            $query->where('r.title', $request->string('requirement_title'));
        }

        return $query;
    }

    private function selectColumns($query)
    {
        return $query->select([
            'e.EMPCODE as empcode',
            'e.FIRSTNAME as firstname',
            'e.LASTNAME as lastname',
            'e.MI as mi',
            DB::raw('e.`OFFICE/DIVISION` as office_division'),
            'e.REGION as region',
            'prog.id as program_id',
            'prog.program_code as program_code',
            'prog.title as program_title',
            'b.id as batch_id',
            'b.batch as batch_label',
            'b.date_end as batch_date_end',
            'r.id as requirement_id',
            'r.title as requirement_title',
            'r.name as requirement_name',
            'r.due_date as due_date',
        ]);
    }

    private function decorateRow($row)
    {
        $mi = trim($row->mi ?? '');
        $mi = $mi !== '' ? ' '.rtrim($mi, '.').'.' : '';
        $row->employee_name = trim("{$row->firstname}{$mi} {$row->lastname}");

        $dueDate = Carbon::parse($row->due_date)->startOfDay();
        $today = now()->startOfDay();
        $row->is_overdue = $dueDate->lt($today);
        $row->days_overdue = $row->is_overdue ? $today->diffInDays($dueDate) : 0;

        return $row;
    }
}
