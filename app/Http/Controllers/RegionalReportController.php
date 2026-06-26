<?php

namespace App\Http\Controllers;

use App\Models\RegionalReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RegionalReportController extends Controller
{
    const REGIONS = [
        'CO'    => 'Central Office',
        'NCR'    => 'National Capital Region',
        'R1'     => 'Ilocos Region',
        'R2'     => 'Cagayan Valley Region',
        'R3'     => 'Central Luzon',
        'R4A'    => 'CALABARZON',
        'R4B'    => 'MIMAROPA',
        'R5'     => 'Bicol Region',
        'R6'     => 'Western Visayas',
        'NIR'     => 'Negros Island Region',
        'R7'     => 'Central Visayas',
        'R8'     => 'Eastern Visayas',
        'R9'     => 'Zamboanga Peninsula',
        'R10'    => 'Northern Mindanao',
        'R11'    => 'Davao Region',
        'R12'    => 'SOCCSKSARGEN',
        'CAR'    => 'Cordillera Administrative Region',
        'CARAGA' => 'CARAGA',
    ];

    const DIRECTORS = [
        'CO'    => '',
        'NCR'    => '',
        'R1'     => '',
        'R2'     => '',
        'R3'     => '',
        'R4A'    => '',
        'R4B'    => '',
        'R5'     => '',
        'R6'     => '',
        'NIR'     => '',
        'R7'     => '',
        'R8'     => '',
        'R9'     => '',
        'R10'    => '',
        'R11'    => '',
        'R12'    => '',
        'CAR'    => '',
        'CARAGA' => '',
    ];

    const MONTHS = [
        1  => 'January',  2  => 'February', 3  => 'March',
        4  => 'April',    5  => 'May',       6  => 'June',
        7  => 'July',     8  => 'August',    9  => 'September',
        10 => 'October',  11 => 'November',  12 => 'December',
    ];

    private function format(RegionalReport $r): array
    {
        return [
            'id'           => $r->id,
            'region'       => $r->region,
            'month'        => $r->month,
            'year'         => $r->year,
            'file_name'    => $r->file_name,
            'file_path'    => $r->file_path,
            'submitted_at' => $r->submitted_at?->toISOString(),
            'notes'        => $r->notes,
            'added_by'     => $r->added_by,
        ];
    }

    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);

        // --- Matrix: kailangan pa rin ng LAHAT ng reports sa taon ---
        $reports = RegionalReport::where('year', $year)->get();

        $matrix = [];
        foreach (array_keys(self::REGIONS) as $code) {
            $matrix[$code] = [];
            for ($m = 1; $m <= 12; $m++) {
                $matrix[$code][$m] = null;
            }
        }

        foreach ($reports as $r) {
            $monthNum = array_search($r->month, self::MONTHS);
            if ($monthNum !== false && isset($matrix[$r->region])) {
                $matrix[$r->region][$monthNum] = $this->format($r);
            }
        }

        $currentMonth  = now()->month;
        $totalRequired = count(self::REGIONS) * $currentMonth;
        $submitted     = $reports->count();
        $pending       = max(0, $totalRequired - $submitted);
        $rate          = $totalRequired > 0 ? round(($submitted / $totalRequired) * 100) : 0;

        // --- Recent Submissions: may search + pagination ---
        $search = trim((string) $request->get('search', ''));

        $recentSubmissions = RegionalReport::where('year', $year)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('region',    'like', "%{$search}%")
                        ->orWhere('month',     'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('added_by',  'like', "%{$search}%")
                        ->orWhere('notes',     'like', "%{$search}%");
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate(8)
            ->withQueryString()                       // panatilihin ang year + search sa page links
            ->through(fn ($r) => $this->format($r));  // i-format bawat item

        $availableYears = RegionalReport::selectRaw('DISTINCT year')
            ->orderByDesc('year')
            ->pluck('year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('tpmr/index', [
            'matrix'            => $matrix,
            'regions'           => self::REGIONS,
            'directors'         => self::DIRECTORS,
            'months'            => self::MONTHS,
            'year'              => (int) $year,
            'currentMonth'      => $currentMonth,
            'stats'             => compact('totalRequired', 'submitted', 'pending', 'rate'),
            'recentSubmissions' => $recentSubmissions,
            'availableYears'    => $availableYears,
            'filters'           => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'region' => 'required|string|max:50',
            'month'  => 'required|string|max:20',
            'year'   => 'required|integer|min:2000|max:2100',
            'notes'  => 'nullable|string|max:1000',
            'pdf'    => 'required|file|mimes:pdf|max:10240',
        ]);

        $exists = RegionalReport::where('region', $validated['region'])
            ->where('month',  $validated['month'])
            ->where('year',   $validated['year'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'pdf' => 'A report for this region and month already exists.',
            ]);
        }

        $file     = $request->file('pdf');
        $fileName = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('regional_reports', $fileName, 'public');

        RegionalReport::create([
            'region'       => $validated['region'],
            'month'        => $validated['month'],
            'year'         => $validated['year'],
            'file_name'    => $fileName,
            'file_path'    => $filePath,
            'submitted_at' => now(),
            'notes'        => $validated['notes'] ?? null,
            'added_by'     => Auth::check() ? Auth::user()->empcode : 'unknown',
        ]);

        return back()->with('success', 'Report submitted successfully.');
    }

    public function destroy(RegionalReport $regionalReport)
    {
        Storage::disk('public')->delete($regionalReport->file_path);
        $regionalReport->delete();

        return back()->with('success', 'Report deleted.');
    }
}