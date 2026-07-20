<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeMapController extends Controller
{
    /**
     * 3D na mapa ng Pilipinas — bilang ng empleyado kada REGION.
     */
    public function index()
    {
        $regionCounts = Employee::query()
            ->whereNotNull('REGION')
            ->where('REGION', '!=', '')
            ->selectRaw('REGION as region, count(*) as total')
            ->groupBy('REGION')
            ->orderByDesc('total')
            ->get();

        return Inertia::render('EmployeesMap/index', [
            'regionCounts' => $regionCounts,
            'totalEmployees' => $regionCounts->sum('total'),
        ]);
    }

    /**
     * Listahan ng mga empleyado sa isang partikular na REGION (para sa side
     * panel na lumalabas kapag pinindot ang isang region block sa 3D mapa).
     */
    public function byRegion(Request $request)
    {
        $request->validate([
            'region' => 'required|string',
        ]);

        $query = Employee::query()->where('REGION', $request->string('region'));

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

        $officeBreakdown = Employee::query()
            ->where('REGION', $request->string('region'))
            ->whereNotNull('OFFICE')
            ->where('OFFICE', '!=', '')
            ->selectRaw('OFFICE as office, count(*) as total')
            ->groupBy('OFFICE')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'employees' => $employees,
            'officeBreakdown' => $officeBreakdown,
        ]);
    }
}
