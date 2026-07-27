<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public const ACCESS_LEVELS = ['guest', 'user', 'admin', 'superadmin'];

    public function index(Request $request): Response
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('empcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('access') && $request->access !== 'all') {
            $query->where('access', $request->access);
        }

        $users = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('UserManagement/index', [
            'users' => $users,
            'accessLevels' => self::ACCESS_LEVELS,
            'filters' => $request->only(['search', 'access']),
        ]);
    }

    // Handles both the inline "Change Access" select (sends only `access`)
    // and the edit modal's name field (sends only `name`) — each caller only
    // sends the field it's actually changing.
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'access' => ['sometimes', 'required', Rule::in(self::ACCESS_LEVELS)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        if (array_key_exists('access', $data) && $user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own access level.');
        }

        $user->update($data);

        return back()->with('success', "Updated {$user->name}.");
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

        return back()->with('success', "Updated {$user->name}'s profile picture.");
    }

    public function destroyAvatar(Request $request, User $user): RedirectResponse
    {
        if ($user->getRawOriginal('avatar')) {
            Storage::disk('public')->delete($user->getRawOriginal('avatar'));
            $user->update(['avatar' => null]);
        }

        return back()->with('success', "Removed {$user->name}'s profile picture.");
    }
}
