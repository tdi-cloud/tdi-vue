<?php

namespace App\Http\Controllers;

use App\Models\ProblemReport;
use App\Models\User;
use App\Notifications\ProblemReported;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\SearchSuggester;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProblemReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProblemReport::with('user:id,name,email,empcode')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $reports = $query->paginate(LiteMode::perPage($request, 15))->withQueryString();

        return Inertia::render('ProblemReports/index', [
            'reports' => $reports,
            'filters' => $request->only(['search', 'status']),
            'suggestion' => SearchSuggester::forEmptyResults(
                $request->string('search'),
                $reports->total(),
                fn () => User::whereIn('id', ProblemReport::select('user_id'))->pluck('name')
                    ->merge(ProblemReport::latest()->limit(300)->pluck('description')),
            ),
        ]);
    }

    public function updateStatus(Request $request, ProblemReport $problemReport)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ProblemReport::STATUS_OPEN, ProblemReport::STATUS_RESOLVED])],
        ]);

        $problemReport->update($data);

        return back()->with('success', FlashMessage::make("Report marked {$problemReport->status}", 'The status change is saved.'));
    }

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

        return back()->with('success', FlashMessage::make(
            'Problem report sent',
            'Thank you! The super admins have been notified and will look into it.',
            "You don't need to do anything else.",
        ));
    }
}
