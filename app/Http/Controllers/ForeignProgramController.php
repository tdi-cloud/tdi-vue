<?php

namespace App\Http\Controllers;

use App\Models\ForeignProgram;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ForeignParticipant;

class ForeignProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = ForeignProgram::withCount('nominees')
        ->addSelect([
            'id', 'program_title', 'description', 'program_start', 'program_end',
            'slots', 'modality', 'organizing_sponsor', 'status', 'category',
            'online_start', 'online_end', 'inperson_start', 'inperson_end',
            'program_cost', 'fund_source', 'submission_date', 'embassy_deadline',
            'interview_date', 'invited_agencies', 'attached_agency', 'created_at',
        ]);

        if ($request->filled('search')) {
            $query->where('program_title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('program_start', $request->year);
        }

        if ($request->filled('semester')) {
            if ($request->semester == 1) {
                $query->whereMonth('program_start', '>=', 1)
                    ->whereMonth('program_start', '<=', 6);
            } else {
                $query->whereMonth('program_start', '>=', 7)
                    ->whereMonth('program_start', '<=', 12);
            }
        }

        if ($request->filled('organization')) {
            $query->where('organizing_sponsor', 'like', '%' . $request->organization . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('embassy_deadline')) {
            $query->whereDate('embassy_deadline', $request->embassy_deadline);
        }

        if ($request->filled('interview_date')) {
            $query->whereDate('interview_date', $request->interview_date);
        }

        $programs = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $years = ForeignProgram::selectRaw('YEAR(program_start) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return Inertia::render('ForeignPrograms/index', [
            'programs' => $programs,
            'years'    => $years,
            'filters'  => $request->only([
                'search', 'status', 'year', 'semester',
                'organization', 'category', 'embassy_deadline', 'interview_date',
            ]),
        ]);
    }


    public function show(ForeignProgram $foreignProgram)
    {
        return Inertia::render('ForeignPrograms/show', [
            'program' => $foreignProgram->load(['nominees.submissions.requirement']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProgram($request);
        ForeignProgram::create($data);

        return back()->with('success', 'Foreign program created successfully.');
    }

    public function update(Request $request, ForeignProgram $foreignProgram)
    {
        $data = $this->validateProgram($request);
        $foreignProgram->update($data);

        return back()->with('success', 'Foreign program updated successfully.');
    }

    public function destroy(ForeignProgram $foreignProgram)
    {
        $foreignProgram->delete();

        return redirect()->route('foreign-programs.index')
            ->with('success', 'Foreign program deleted successfully.');
    }

    private function validateProgram(Request $request): array
    {
        return $request->validate([
            'program_title'      => 'required|string|max:255',
            'description'        => 'nullable|string',
            'program_start'      => 'required|date',
            'program_end'        => 'required|date|after_or_equal:program_start',
            'slots'              => 'required|integer|min:1',
            'modality'           => 'required|in:in-person,online,hybrid',
            'online_start'       => 'nullable|date',
            'online_end'         => 'nullable|date',
            'inperson_start'     => 'nullable|date',
            'inperson_end'       => 'nullable|date',
            'program_cost'       => 'nullable|string|max:255',
            'fund_source'        => 'nullable|in:SDP,Other Office,Sponsoring Organization',
            'category'           => 'required|in:Foreign,Bilateral',
            'organizing_sponsor' => 'required|string|max:255',
            'status'             => 'required|string',
            'submission_date'    => 'nullable|date',
            'embassy_deadline'   => 'nullable|date',
            'interview_date'     => 'nullable|date',
            'invited_agencies'   => 'nullable|string',
            'attached_agency'    => 'nullable|string|max:255',
        ]);
    }

    public function dashboardData(Request $request)
    {
        $year   = $request->input('year');
        $agency = $request->input('agency');
        $status = $request->input('status', 'accepted');
 
        // ----- Nominees -----
        $nomineesQuery = \App\Models\ForeignNominee::query()
            ->whereHas('program', function ($q) use ($year) {
                if ($year) {
                    $q->whereYear('program_start', $year);
                }
            });
 
        if ($agency) {
            $nomineesQuery->where('agency', 'like', '%' . $agency . '%');
        }
 
        $statusBreakdown = (clone $nomineesQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
 
        $statusLabels = [
            'for_interview'  => 'For Interview',
            'endorsed'       => 'Endorsed',
            'waiting_result' => 'Waiting Result',
            'not_endorsed'   => 'Not Endorsed',
            'accepted'       => 'Accepted',
            'regret'         => 'Regret',
            'cancelled'      => 'Cancelled',
        ];
 
        $statusChartLabels = [];
        $statusSeries      = [];
        foreach ($statusLabels as $key => $label) {
            $statusChartLabels[] = $label;
            $statusSeries[]      = (int) ($statusBreakdown[$key] ?? 0);
        }
 
        $selectedStatusCount = (int) ($statusBreakdown[$status] ?? 0);
        $totalNominees       = array_sum($statusSeries);
 
        $agencies = \App\Models\ForeignNominee::query()
            ->select('agency')->distinct()->orderBy('agency')->pluck('agency');
 
        $years = ForeignProgram::selectRaw('YEAR(program_start) as year')
            ->distinct()->orderByDesc('year')->pluck('year');
 
        // ----- Programs per organizing_sponsor -----
        $programQuery = ForeignProgram::query();
        if ($year) {
            $programQuery->whereYear('program_start', $year);
        }
 
        $bySponsor = (clone $programQuery)
            ->selectRaw("organizing_sponsor,
                count(*) as received,
                sum(case when status != 'for_dissemination' then 1 else 0 end) as disseminated")
            ->groupBy('organizing_sponsor')
            ->orderByDesc('received')
            ->get();
 
        return response()->json([
            'agencies' => $agencies,
            'years'    => $years,
            'participants' => [
                'selectedStatusCount' => $selectedStatusCount,
                'totalParticipants'   => $totalNominees,
                'statusLabels'        => $statusChartLabels,
                'statusSeries'        => $statusSeries,
            ],
            'programs' => [
                'sponsors'     => $bySponsor->pluck('organizing_sponsor'),
                'received'     => $bySponsor->pluck('received'),
                'disseminated' => $bySponsor->pluck('disseminated'),
            ],
        ]);
    }

    public function byOrganizingSponsor(Request $request)
    {
        $sponsor = trim($request->query('sponsor'));

        $query = ForeignProgram::query()
            ->where('organizing_sponsor', $sponsor); // exact match

        // ── Itago ang mga lampas na ang submission date kaysa ngayon ──
        // Pinapakita pa rin ang mga walang submission_date (null = walang deadline).
        $query->where(function ($q) {
            $q->whereNull('submission_date')
            ->orWhereDate('submission_date', '>=', now()->toDateString());
        });

        // Year filter (optional)
        if ($request->filled('year')) {
            $query->whereYear('program_start', $request->query('year'));
        }

        $programs = $query
            ->orderBy('program_start', 'desc')
            ->get(['id', 'program_title', 'program_start', 'program_end', 'modality', 'slots', 'status', 'submission_date']);

        $years = ForeignProgram::where('organizing_sponsor', $sponsor)
            ->selectRaw('YEAR(program_start) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return response()->json([
            'programs' => $programs,
            'years'    => $years,
        ]);
    }
}