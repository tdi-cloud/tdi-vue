<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\FlashMessage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $wasTemporary = (bool) $user->must_change_password;

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => array_filter([
                'required',
                Password::defaults(),
                'confirmed',
                // Replacing an emailed temporary password with the same text would defeat the point.
                $wasTemporary ? 'different:current_password' : null,
            ]),
        ], [
            'password.different' => 'Choose a new password that is different from your temporary password.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'temp_password_expires_at' => null,
        ]);

        if ($wasTemporary) {
            return redirect()->route('home')->with('success', FlashMessage::make(
                'Your password is set',
                'You can now use the TDI system with your new password.',
                'Find your program under My Programs.',
            ));
        }

        return back();
    }
}
