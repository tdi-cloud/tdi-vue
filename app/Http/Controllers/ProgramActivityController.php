<?php

namespace App\Http\Controllers;

use App\Models\ProgramActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Hidden na "daily monitoring" na page para sa Programs module — walang
 * link dito sa navigation, admin lang ang pwedeng pumasok (direktang URL).
 */
class ProgramActivityController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date') ?: now()->toDateString();

        $stats = [
            'created' => ProgramActivityLog::whereDate('created_at', $date)->where('action', 'created')->count(),
            'updated' => ProgramActivityLog::whereDate('created_at', $date)->where('action', 'updated')->count(),
            'deleted' => ProgramActivityLog::whereDate('created_at', $date)->where('action', 'deleted')->count(),
        ];

        $logs = ProgramActivityLog::whereDate('created_at', $date)
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('programs/ActivityLog', [
            'stats' => $stats,
            'logs' => $logs,
            'date' => $date,
        ]);
    }
}
