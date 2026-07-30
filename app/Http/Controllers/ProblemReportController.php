<?php

namespace App\Http\Controllers;

use App\Models\ProblemReport;
use App\Models\User;
use App\Notifications\ProblemReported;
use Illuminate\Http\Request;

class ProblemReportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => 'required|string|max:2000',
            'page_url' => 'nullable|string|max:2048',
        ]);

        $problemReport = ProblemReport::create([
            'user_id' => $request->user()->id,
            'description' => $data['description'],
            'page_url' => $data['page_url'] ?? null,
        ]);

        $superAdmins = User::where('access', 'superadmin')->get();

        foreach ($superAdmins as $superAdmin) {
            $superAdmin->notify(new ProblemReported($problemReport));
        }

        return back()->with('success', 'Thank you! Your problem report has been sent to the super admin.');
    }
}
