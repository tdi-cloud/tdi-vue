<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNhrdcMember
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // NHRDC members, and the representatives the FSTP unit assigned to them.
        if (! $request->user() || ! $request->user()->canRateAsNhrdc()) {
            abort(403, 'You do not have access to this resource.');
        }

        return $next($request);
    }
}
