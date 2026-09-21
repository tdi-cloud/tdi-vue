<?php

namespace App\Http\Controllers;

use App\Models\SubmissionActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Hidden na "daily monitoring" na page para sa Requirements Submissions —
 * walang link dito sa navigation, admin lang ang pwedeng pumasok (direktang
 * URL). Ipinapakita kung ilan at ano-ano ang na-encode/na-edit/na-review/
 * na-delete na submission kada user, kada araw.
 */
class SubmissionActivityController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date') ?: now()->toDateString();
        $user = $request->get('user') ?: null;

        $stats = [
            'encoded' => SubmissionActivityLog::whereDate('created_at', $date)->where('action', 'encoded')->count(),
            'updated' => SubmissionActivityLog::whereDate('created_at', $date)->where('action', 'updated')->count(),
            'reviewed' => SubmissionActivityLog::whereDate('created_at', $date)->where('action', 'reviewed')->count(),
            'deleted' => SubmissionActivityLog::whereDate('created_at', $date)->where('action', 'deleted')->count(),
        ];

        $userStats = SubmissionActivityLog::whereDate('created_at', $date)
            ->selectRaw(
                "performed_by, COUNT(*) as total,
                SUM(CASE WHEN action = 'encoded' THEN 1 ELSE 0 END) as encoded,
                SUM(CASE WHEN action = 'updated' THEN 1 ELSE 0 END) as updated,
                SUM(CASE WHEN action = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
                SUM(CASE WHEN action = 'deleted' THEN 1 ELSE 0 END) as deleted"
            )
            ->groupBy('performed_by')
            ->orderByDesc('total')
            ->get();

        $logs = SubmissionActivityLog::whereDate('created_at', $date)
            ->when($user, fn ($query) => $query->where('performed_by', $user))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('programs/SubmissionActivityLog', [
            'stats' => $stats,
            'userStats' => $userStats,
            'logs' => $logs,
            'date' => $date,
            'user' => $user,
        ]);
    }
}
