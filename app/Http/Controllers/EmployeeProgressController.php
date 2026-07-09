<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Participant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Submission;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeProgressController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('EMPCODE', 'like', "%$search%")
                  ->orWhere('FIRSTNAME', 'like', "%$search%")
                  ->orWhere('LASTNAME', 'like', "%$search%")
                  ->orWhere('OFFICE/DIVISION', 'like', "%$search%");
            });
        }

        if ($request->filled('region') && $request->region !== 'all') {
            $query->where('REGION', $request->region);
        }

        if ($request->filled('plantilla') && $request->plantilla !== 'all') {
            $query->where('PLANTILLA STATUS', $request->plantilla);
        }

        $employees = $query
            ->orderBy('LASTNAME')
            ->paginate($request->get('per_page', 10))
            ->withQueryString();

        $regions = Employee::distinct()->orderBy('REGION')->pluck('REGION');
        $plantillaStatuses = Employee::distinct()->orderBy('PLANTILLA STATUS')->pluck('PLANTILLA STATUS');

        return Inertia::render('employees/index', [
            'employees'        => $employees,
            'regions'          => $regions,
            'plantillaStatuses'=> $plantillaStatuses,
            'filters'          => $request->only(['search', 'region', 'plantilla', 'per_page']),
        ]);
    }

    private function buildProgress(string $empcode): array
    {
        $employee = Employee::where('EMPCODE', $empcode)->firstOrFail();

        $participants = Participant::with(['batch.program.coverPage'])
            ->where('empcode', $empcode)
            ->get();

        $enrolledPrograms = $participants->map(function ($participant) {
            $batch   = $participant->batch;
            $program = $batch?->program;
            if (!$program) return null;

            $requirements = Requirement::where('batch_id', $batch->id)->get();
            $submissions  = Submission::where('participant_id', $participant->id)->get();

            $totalReqs    = $requirements->count();
            $approvedSubs = $submissions->where('status', 'Approved')->count();
            $pendingSubs  = $submissions->whereIn('status', ['Pending', 'Rejected'])->count();

            $requirementDetails = $requirements->map(function ($req) use ($submissions, $participant) {
                $sub = $submissions->firstWhere('requirement_id', $req->id);

                if (!$sub) {
                    $sameTitle = Requirement::where('title', $req->title)->pluck('id');
                    $sub = Submission::where('participant_id', $participant->id)
                        ->whereIn('requirement_id', $sameTitle)
                        ->first();
                }

                return [
                    'id'          => $req->id,
                    'title'       => $req->title,
                    'name'        => $req->name,
                    'due_date'    => $req->due_date,
                    'description' => $req->note,
                    'required'    => $req->is_required,
                    'submission'  => $sub ? [
                        'id'           => $sub->id,
                        'status'       => $sub->status,
                        'file_path'    => $sub->file_path,
                        'submitted_at' => $sub->submitted_at,
                        'reviewed_at'  => $sub->reviewed_at,
                        'reviewed_by'  => $sub->reviewed_by,
                        'notes'        => $sub->notes,
                        'remarks'      => $sub->remarks,
                    ] : null,
                ];
            });

            $isCompleted = $participant->attendance === 'Complete'
                && ($totalReqs === 0 || $approvedSubs >= $totalReqs);

            return [
                'participant_id'       => $participant->id,
                'batch_id'             => $batch->id,
                'program_id'           => $program->id,
                'program_code'         => $program->program_code,
                'program_title'        => $program->title,
                'batch_label'          => $batch->batch,
                'date_start'           => $batch->date_start,
                'date_end'             => $batch->date_end,
                'hours'                => $participant->hours ?? $batch->hours,
                'attendance'           => $participant->attendance,
                'batch_status'         => $batch->status,
                'is_completed'         => $isCompleted,
                'total_requirements'   => $totalReqs,
                'approved_submissions' => $approvedSubs,
                'pending_submissions'  => $pendingSubs,
                'cover_image'          => $program->coverPage?->image,
                'requirements'         => $requirementDetails,
            ];
        })->filter()->values();

        $programsAttended  = $enrolledPrograms->count();
        $programsCompleted = $enrolledPrograms->where('is_completed', true)->count();

        $allSubmissions = Submission::whereHas('participant', fn($p) => $p->where('empcode', $empcode))->count();
        $approvedAll    = Submission::whereHas('participant', fn($p) => $p->where('empcode', $empcode))
            ->where('status', 'Approved')->count();

        $completionRate = $allSubmissions > 0
            ? round(($approvedAll / $allSubmissions) * 100)
            : ($programsAttended > 0 && $programsCompleted === $programsAttended ? 100 : 0);

        return [
            'employee' => $employee,
            'stats'    => [
                'programs_attended'  => $programsAttended,
                'programs_completed' => $programsCompleted,
                'total_hours'        => $enrolledPrograms->sum('hours'),
                'total_submissions'  => $enrolledPrograms->sum('approved_submissions'),
                'not_approved'       => $enrolledPrograms->sum('pending_submissions'),
                'completion_rate'    => $completionRate,
            ],
            'enrolled_programs' => $enrolledPrograms,
        ];
    }

    public function show(string $empcode)
    {
        return response()->json($this->buildProgress($empcode));
    }

    public function exportCsv(string $empcode): StreamedResponse
    {
        $data      = $this->buildProgress($empcode);
        $employee  = $data['employee'];
        $programs  = $data['enrolled_programs'];

        $filename = 'employee_' . $employee->EMPCODE . '_programs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        return response()->stream(function () use ($employee, $programs) {
            $out = fopen('php://output', 'w');

            // Excel UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'EMPCODE', 'Employee Name', 'Position', 'Office/Division', 'Region',
                'Plantilla Status',
                'Program Code', 'Program Title', 'Batch', 'Date Start', 'Date End',
                'Hours', 'Attendance', 'Program Completed',
                'Requirement', 'Requirement Name', 'Due Date', 'Is Required',
                'Submission Status', 'Submitted At', 'Reviewed By', 'Reviewed At',
                'File Path', 'Remarks',
            ]);

            $base = [
                $employee->EMPCODE,
                $employee->name,
                $employee->POSITION,
                $employee->{'OFFICE/DIVISION'},
                $employee->REGION,
                $employee->{'PLANTILLA STATUS'},
            ];

            if ($programs->isEmpty()) {
                fputcsv($out, array_merge($base, array_fill(0, 18, '')));
            }

            foreach ($programs as $prog) {
                $progCols = [
                    $prog['program_code'],
                    $prog['program_title'],
                    $prog['batch_label'],
                    $prog['date_start'],
                    $prog['date_end'],
                    $prog['hours'],
                    $prog['attendance'],
                    $prog['is_completed'] ? 'Yes' : 'No',
                ];

                // Walang requirement — isang row pa rin para lumabas ang program
                if (count($prog['requirements']) === 0) {
                    fputcsv($out, array_merge($base, $progCols, [
                        'No requirements', '', '', '', 'N/A', '', '', '', '', '',
                    ]));
                    continue;
                }

                foreach ($prog['requirements'] as $req) {
                    $sub = $req['submission'];
                    fputcsv($out, array_merge($base, $progCols, [
                        $req['title'],
                        $req['name'] ?? '',
                        $req['due_date'] ?? '',
                        $req['required'] ? 'Required' : 'Optional',
                        $sub['status'] ?? 'Not Submitted',
                        $sub['submitted_at'] ?? '',
                        $sub['reviewed_by'] ?? '',
                        $sub['reviewed_at'] ?? '',
                        $sub['file_path'] ?? '',
                        $sub['remarks'] ?? '',
                    ]));
                }
            }

            fclose($out);
        }, 200, $headers);
    }
}