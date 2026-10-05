<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessForeignPrograms
{
    /**
     * Restrict Foreign Programs pages to FSTP unit members (and superadmins).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->canAccessForeignPrograms()) {
            abort(403, 'Foreign Programs are restricted to FSTP unit members.');
        }

        return $next($request);
    }
}
