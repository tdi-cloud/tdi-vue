<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\SearchSuggester;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public const ACCESS_LEVELS = ['guest', 'user', 'admin', 'superadmin'];

    // Isang user ay itinuturing na "online" kung meron siyang session na
    // may activity sa loob ng ganitong bilang ng minuto.
    public const ONLINE_THRESHOLD_MINUTES = 5;

    public function index(Request $request): Response
    {
        $onlineThreshold = now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES)->timestamp;

        // Pinaka-huling activity per user, mula sa "sessions" table (database
        // session driver — meron nang user_id + last_activity dito). Naka-join
        // na ito bago mag-paginate para ma-sort natin ang buong list base dito
        // (active users muna), hindi lang yung nasa kasalukuyang page.
        $sessionActivity = DB::table('sessions')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        $query = User::query()
            ->leftJoinSub($sessionActivity, 'session_activity', function ($join) {
                $join->on('users.id', '=', 'session_activity.user_id');
            })
            ->leftJoin('employees', 'employees.EMPCODE', '=', 'users.empcode')
            ->select(
                'users.*',
                'session_activity.last_activity as session_last_activity',
                DB::raw('employees.`OFFICE/DIVISION` as office_division')
            );

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.empcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('access') && $request->access !== 'all') {
            $query->where('users.access', $request->access);
        }

        if ($request->boolean('fstp')) {
            $query->where('users.is_fstp_member', true);
        }

        $users = $query
            ->orderByRaw('CASE WHEN session_activity.last_activity >= ? THEN 0 ELSE 1 END', [$onlineThreshold])
            ->orderByDesc('session_activity.last_activity')
            ->orderBy('users.name')
            ->paginate(LiteMode::perPage($request, 15))
            ->withQueryString();

        $users->getCollection()->transform(function (User $user) use ($onlineThreshold) {
            $activity = $user->session_last_activity;
            $user->last_active_at = $activity ? Carbon::createFromTimestamp($activity)->toISOString() : null;
            $user->is_online = $activity !== null && $activity >= $onlineThreshold;
            unset($user->session_last_activity);

            return $user;
        });

        return Inertia::render('UserManagement/index', [
            'users' => $users,
            'accessLevels' => self::ACCESS_LEVELS,
            'filters' => $request->only(['search', 'access', 'fstp']),
            'suggestion' => SearchSuggester::forEmptyResults(
                $request->string('search'),
                $users->total(),
                fn () => User::query()->get(['name', 'email'])->flatMap(fn (User $user) => [$user->name, strtok((string) $user->email, '@')]),
            ),
        ]);
    }

    // Handles the inline "Change Access" select (sends only `access`), the
    // "FSTP Unit" toggle (sends only `is_fstp_member`) and the edit modal's
    // name field (sends only `name`) — each caller only sends the field it's
    // actually changing.
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'access' => ['sometimes', 'required', Rule::in(self::ACCESS_LEVELS)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_fstp_member' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('access', $data) && $user->id === $request->user()->id) {
            return back()->with('error', FlashMessage::make(
                "You can't change your own access level",
                'This keeps an admin from locking themselves out.',
                'Ask another superadmin to change it for you.',
            ));
        }

        $user->update($data);

        return back()->with('success', FlashMessage::make("{$user->name} updated", 'The change is saved and applies the next time they open a page.'));
    }

    public function updateAvatar(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->getRawOriginal('avatar')) {
            Storage::disk('public')->delete($user->getRawOriginal('avatar'));
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return back()->with('success', FlashMessage::make('Profile picture updated', "{$user->name}'s new picture now shows across the app."));
    }

    public function destroyAvatar(Request $request, User $user): RedirectResponse
    {
        if ($user->getRawOriginal('avatar')) {
            Storage::disk('public')->delete($user->getRawOriginal('avatar'));
            $user->update(['avatar' => null]);
        }

        return back()->with('success', FlashMessage::make('Profile picture removed', "{$user->name} now shows their initials instead."));
    }
}
