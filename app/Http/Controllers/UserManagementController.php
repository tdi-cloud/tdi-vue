<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'access' => ['required', Rule::in(self::ACCESS_LEVELS)],
        ]);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own access level.');
        }

        $user->update(['access' => $request->string('access')->toString()]);

        return back()->with('success', "Updated {$user->name}'s access to {$request->access}.");
    }
}
