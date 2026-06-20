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

    public function show(string $empcode)
    {
        $employee = Employee::where('EMPCODE', $empcode)->firstOrFail();

        // Get all participant records for this employee
        $participants = Participant::with(['batch.program.coverPage'])
        ->where('empcode', $empcode)  
        ->get();

        $enrolledPrograms = $participants->map(function ($participant) {
            $batch   = $participant->batch;
            $program = $batch?->program;

            if (!$program) return null;

            // Requirements are keyed by batch_id, NOT program_code
            $requirements = Requirement::where('batch_id', $batch->id)->get();

            // Load submissions for this specific participant
            $submissions = Submission::where('participant_id', $participant->id)->get();

            $totalReqs    = $requirements->count();
            $approvedSubs = $submissions->where('status', 'Approved')->count();
            $pendingSubs  = $submissions->whereIn('status', ['Pending', 'Rejected'])->count();

           

            // Kunin ang lahat ng requirement IDs ng parehong title across all batches
            $requirementDetails = $requirements->map(function ($req) use ($submissions, $participant, $batch) {
                // Una, try exact match sa requirement_id
                $sub = $submissions->firstWhere('requirement_id', $req->id);

                // Kung wala, hanapin lahat ng requirements na parehong title
                // sa lahat ng batches ng parehong program, tapos i-match ang submission
                if (!$sub) {
                    $sameTitle = Requirement::where('title', $req->title)
                        ->pluck('id');

                    $sub = Submission::where('participant_id', $participant->id)
                        ->whereIn('requirement_id', $sameTitle)
                        ->first();
                }

                return [
                    'id'          => $req->id,
                    'title'       => $req->title,
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
                'participant_id'      => $participant->id,
                'batch_id'            => $batch->id,
                'program_id'          => $program->id,
                'program_code'        => $program->program_code,
                'program_title'       => $program->title,
                'batch_label'         => $batch->batch,
                'date_start'          => $batch->date_start,
                'date_end'            => $batch->date_end,
                'hours'               => $participant->hours ?? $batch->hours,
                'attendance'          => $participant->attendance,
                'batch_status'        => $batch->status,
                'is_completed'        => $isCompleted,
                'total_requirements'  => $totalReqs,
                'approved_submissions'=> $approvedSubs,
                'pending_submissions' => $pendingSubs,
                'cover_image'         => $program->coverPage?->image,
                'requirements'        => $requirementDetails,
            ];
        })->filter()->values();

        $programsAttended  = $enrolledPrograms->count();
        $programsCompleted = $enrolledPrograms->where('is_completed', true)->count();
        $totalHours        = $enrolledPrograms->sum('hours');
        $totalSubmissions  = $enrolledPrograms->sum('approved_submissions');
        $notApproved       = $enrolledPrograms->sum('pending_submissions');

        $allSubmissions = Submission::where(function ($q) use ($empcode) {
            $q->whereHas('participant', fn($p) => $p->where('empcode', $empcode));
        })->count();

        $approvedAll = Submission::whereHas('participant', fn($p) => $p->where('empcode', $empcode))
            ->where('status', 'Approved')->count();

        $completionRate = $allSubmissions > 0
            ? round(($approvedAll / $allSubmissions) * 100)
            : ($programsAttended > 0 && $programsCompleted === $programsAttended ? 100 : 0);

        return response()->json([
            'employee'          => $employee,
            'stats'             => [
                'programs_attended'  => $programsAttended,
                'programs_completed' => $programsCompleted,
                'total_hours'        => $totalHours,
                'total_submissions'  => $totalSubmissions,
                'not_approved'       => $notApproved,
                'completion_rate'    => $completionRate,
            ],
            'enrolled_programs' => $enrolledPrograms,
        ]);
    }
}