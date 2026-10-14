<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Listahan ng pinakabagong notifications ng naka-login na user (JSON,
     * para sa bell dropdown).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json($notifications);
    }

    /**
     * GET /notifications/all — dedicated page with every notification of the
     * signed-in user, paginated, with an All / Unread filter.
     */
    public function page(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $user = $request->user();

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
                'created_at_full' => $n->created_at->format('M j, Y g:i A'),
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * I-mark as read ang isang notification ng naka-login na user.
     * Inertia POST (hindi raw JSON), kaya `back()` ang tamang response.
     */
    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return back();
    }

    /**
     * I-mark as read ang lahat ng unread notifications ng naka-login na user.
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
