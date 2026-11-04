<?php

namespace App\Http\Middleware;

use App\Support\FlashMessage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created from an approved registration start with an emailed
 * temporary password. Until the person sets their own, every page sends them
 * to the password settings. Background JSON calls (e.g. the notification
 * bell) pass through so the page itself keeps working.
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes that stay reachable while the password still has to be changed.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['password.edit', 'password.update', 'logout', 'join.*'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $isPageVisit = $request->header('X-Inertia') || ! $request->expectsJson();

        if (! $isPageVisit) {
            return $next($request);
        }

        return redirect()->route('password.edit')->with('error', FlashMessage::make(
            'Please set your own password',
            'Your account still uses the temporary password from your approval email.',
            'Enter it as your current password, then choose a new one.',
        ));
    }
}
