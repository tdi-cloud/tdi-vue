<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TPMRController extends Controller
{
    
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'region' => 'nullable|string',
            'filter' => 'required|in:all,monthly,annual',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'prepared_name' => 'nullable|string',
            'prepared_position' => 'nullable|string',
            'prepared_date' => 'nullable|string',
            'noted_name' => 'nullable|string',
            'noted_position' => 'nullable|string',
            'noted_date' => 'nullable|string',
        ]);

        $region = $validated['region'] ?? 'all';
        $filter = $validated['filter'];
        $year = isset($validated['year']) ? (int) $validated['year'] : now()->year;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;

        // ---- 1. Kunin ang mga batches sa loob ng period, kasama ang program nila ----
        $batchesQuery = Batch::query()->with('program');

        if ($filter === 'monthly' && $month) {
           
            $batchesQuery->whereYear('date_start', $year)
                ->whereMonth('date_start', $month);
        } elseif ($filter === 'annual') {
            $batchesQuery->whereYear('date_start', $year);
        }

        $batches = $batchesQuery->orderBy('date_start')->get();

        $allEmpCodes = $batches->flatMap(function ($batch) {
            return $batch->participants->pluck('empcode');
        })->unique()->values();

        $employees = Employee::whereIn('EMPCODE', $allEmpCodes)->get()->keyBy('EMPCODE');

        $rows = [];

        foreach ($batches as $batch) {
            foreach ($batch->participants as $participant) {
                $employee = $employees->get($participant->empcode);

                if (!$employee) {
                    continue; // walang match sa employees table, skip
                }

                if ($region !== 'all' && $employee->REGION !== $region) {
                    continue; // hindi kabilang sa piniling region
                }

                $rows[] = [
                    'program_title' => optional($batch->program)->title ?? '(Unknown Program)',
                    'start' => $batch->date_start,
                    'end' => $batch->date_end,
                    'name' => trim($employee->FIRSTNAME . ' ' . $employee->MI . ' ' . $employee->LASTNAME),
                    'office' => $employee->{'OFFICE/DIVISION'},
                    'position' => $employee->POSITION,
                    // ito ang requirement mo: attendance === 'Complete' -> Completed
                    'status' => strcasecmp(trim($participant->attendance), 'Complete') === 0
                        ? 'Completed'
                        : 'Not Complete',
                ];
            }
        }

        $monthName = $month ? Carbon::createFromDate($year, $month, 1)->format('F') : null;

        $periodLabel = match ($filter) {
            'monthly' => "For the Month of {$monthName}, {$year}",
            'annual' => "For the Year {$year}",
            default => 'All Records',
        };

        $regionLabel = $region === 'all' ? 'All Regions' : $region;

        $pdf = Pdf::loadView('pdf.tpmr', [
            'rows' => $rows,
            'periodLabel' => $periodLabel,
            'regionLabel' => $regionLabel,
            'prepared' => [
                'name' => $validated['prepared_name'] ?? '',
                'position' => $validated['prepared_position'] ?? '',
                'date' => $validated['prepared_date'] ?? '',
            ],
            'noted' => [
                'name' => $validated['noted_name'] ?? '',
                'position' => $validated['noted_position'] ?? '',
                'date' => $validated['noted_date'] ?? '',
            ],
        ]);

        $pdf->setPaper('legal', 'portrait');

        return $pdf->stream('TPMR-' . now()->format('Ymd-His') . '.pdf');
    }

    public function searchEmployees(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $employees = Employee::query()
            ->where('PLANTILLA STATUS', '!=', 'JOB ORDER')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('FIRSTNAME', 'like', "%{$q}%")
                        ->orWhere('LASTNAME', 'like', "%{$q}%")
                        ->orWhere('EMPCODE', 'like', "%{$q}%")
                        ->orWhere('POSITION', 'like', "%{$q}%");
                });
            })
            ->orderBy('LASTNAME')
            ->limit(20)
            ->get([
                'EMPCODE',
                'FIRSTNAME',
                'MI',
                'LASTNAME',
                'POSITION',
                'OFFICE/DIVISION as OFFICE_DIVISION',
            ]);

        return response()->json($employees);
    }
}