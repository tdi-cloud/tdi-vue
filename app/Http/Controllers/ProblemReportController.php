<?php

namespace App\Http\Controllers;

use App\Models\ProblemReport;
use App\Models\User;
use App\Notifications\ProblemReported;
use App\Notifications\ProblemReportReplied;
use App\Notifications\ProblemReportResolved;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\SearchSuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProblemReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProblemReport::with(['user:id,name,email,empcode', 'replies.user:id,name'])->latest();

        // ?report= comes from a reply notification: show just that report.
        if ($request->filled('report')) {
            $query->whereKey($request->integer('report'));
        }

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
            'filters' => $request->only(['search', 'status', 'report']),
            'suggestion' => SearchSuggester::forEmptyResults(
                $request->string('search'),
                $reports->total(),
                fn () => User::whereIn('id', ProblemReport::select('user_id'))->pluck('name')
                    ->merge(ProblemReport::latest()->limit(300)->pluck('description')),
            ),
        ]);
    }

    /**
     * GET /problem-reports/mine — the signed-in user's own reports with their
     * conversations, so reporters can read and answer the super admins' replies.
     */
    public function mine(Request $request): Response
    {
        $reports = ProblemReport::whereBelongsTo($request->user())
            ->with('replies.user:id,name')
            // ?report= comes from a notification: show just that report.
            ->when($request->filled('report'), fn ($q) => $q->whereKey($request->integer('report')))
            ->latest()
            ->paginate(LiteMode::perPage($request, 10))
            ->withQueryString();

        return Inertia::render('ProblemReports/Mine', [
            'reports' => $reports,
            'filters' => $request->only(['report']),
        ]);
    }

    public function updateStatus(Request $request, ProblemReport $problemReport)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ProblemReport::STATUS_OPEN, ProblemReport::STATUS_RESOLVED])],
        ]);

        $problemReport->update($data);

        $reporter = $problemReport->user;
        $notifyReporter = $problemReport->wasChanged('status')
            && $problemReport->status === ProblemReport::STATUS_RESOLVED
            && $reporter
            && ! $reporter->is($request->user());

        if ($notifyReporter) {
            $reporter->notify(new ProblemReportResolved($problemReport));
        }

        return back()->with('success', FlashMessage::make(
            "Report marked {$problemReport->status}",
            $notifyReporter ? 'The status change is saved and the reporter has been notified.' : 'The status change is saved.',
        ));
    }

    /**
     * POST /problem-reports/{problemReport}/replies — super admins answer any
     * report; the reporter answers back on their own.
     */
    public function reply(Request $request, ProblemReport $problemReport): RedirectResponse
    {
        abort_unless($problemReport->canBeRepliedToBy($request->user()), 403);

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $reply = $problemReport->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $reply->setRelation('problemReport', $problemReport);

        $fromReporter = $problemReport->user()->is($request->user());
        $reopened = false;

        if ($fromReporter) {
            // Writing back on a resolved report reopens it so the super admins see it again.
            if ($problemReport->status === ProblemReport::STATUS_RESOLVED) {
                $problemReport->update(['status' => ProblemReport::STATUS_OPEN]);
                $reopened = true;
            }

            $recipients = User::where('access', 'superadmin')->whereKeyNot($request->user()->id)->get();
        } else {
            $recipients = collect([$problemReport->user])->filter();
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new ProblemReportReplied($reply));
        }

        return back()->with('success', FlashMessage::make(
            'Reply sent',
            'Your reply was added to the conversation.'.($reopened ? ' The report is open again.' : ''),
            $fromReporter ? 'The super admins have been notified.' : 'The reporter has been notified.',
        ));
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
            "You'll get a notification when they reply.",
            ['label' => 'View my reports', 'href' => route('problem-reports.mine')],
        ));
    }
}
