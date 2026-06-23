<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceController extends Controller
{
    /**
     * Search employees for signatory fields.
     * GET /employees/search-signatory?q=...
     */
    public function searchSignatory(Request $request)
    {
        $q = $request->query('q', '');

        $employees = Employee::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('LASTNAME',  'LIKE', "%{$q}%")
                      ->orWhere('FIRSTNAME', 'LIKE', "%{$q}%")
                      ->orWhere('EMPCODE',   'LIKE', "%{$q}%");
                });
            })
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get();

        return response()->json(
            $employees->map(fn ($e) => [
                'empcode'        => $e->EMPCODE,
                'name'           => $e->name,
                'position'       => $e->POSITION,
                'office_division'=> $e->{'OFFICE/DIVISION'},
            ])->values()
        );
    }

    /**
     * Generate the Attendance PDF.
     * GET /attendance/generate?batch_id=&date=&prepared_name=&...
     */
    public function generate(Request $request)
    {
        $request->validate([
            'batch_id'          => 'required|exists:batches,id',
            'date'              => 'required|date',
            'prepared_name'     => 'required|string',
            'prepared_position' => 'required|string',
            'prepared_office'   => 'required|string',
            'noted_name'        => 'required|string',
            'noted_position'    => 'required|string',
            'noted_office'      => 'required|string',
        ]);

        $batch = Batch::with([
            'program',
            'participants' => fn ($q) => $q->orderBy('sort_order')->with('employee'),
        ])->findOrFail($request->batch_id);

        // Format date: "30 October 2025"
        $date = Carbon::parse($request->date)->format('d F Y');

        // Venue
        $venue = $batch->venue ?? '—';

        // Program title
        $programTitle = $batch->program?->title ?? $batch->program_code;

        // Participants list — sorted, with employee data
        $participants = $batch->participants->map(function ($p, $index) {
            $emp = $p->employee;
            return [
                'no'       => $index + 1,
                'name'     => $emp
                    ? strtoupper("{$emp->LASTNAME}, {$emp->FIRSTNAME}" . ($emp->MI ? " {$emp->MI}." : ''))
                    : strtoupper($p->empcode),
                'position' => $emp?->POSITION ?? '',
                'office'   => $emp?->{'OFFICE/DIVISION'} ?? '',
            ];
        })->values()->toArray();

        // Pad rows to the next multiple of 10, minimum 10
        $actualCount = count($participants);
        $minRows     = max(10, (int) ceil($actualCount / 10) * 10);
        while (count($participants) < $minRows) {
            $participants[] = ['no' => count($participants) + 1, 'name' => '', 'position' => '', 'office' => ''];
        }

        $data = [
            'programTitle'      => $programTitle,
            'date'              => $date,
            'venue'             => $venue,
            'participants'      => $participants,
            'prepared_name'     => strtoupper($request->prepared_name),
            'prepared_position' => $request->prepared_position,
            'prepared_office'   => $request->prepared_office,
            'noted_name'        => strtoupper($request->noted_name),
            'noted_position'    => $request->noted_position,
            'noted_office'      => $request->noted_office,
        ];

        $filename = 'Attendance_' . str_replace(' ', '_', $date) . '_' . str_replace(' ', '_', $programTitle) . '.pdf';

        $pdf = Pdf::loadView('pdf.attendance', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'Arial')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('title', 'Attendance - ' . $date . ' | ' . $programTitle);

        return $pdf->stream($filename);
    }
}