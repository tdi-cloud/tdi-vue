<?php

use App\Http\Middleware\AddSearchSuggestionHeader;
use App\Http\Middleware\EnsureUserCanAccessForeignPrograms;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNhrdcMember;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Set by the browser (lite mode for slow connections), so it isn't encrypted.
        $middleware->encryptCookies(except: [LiteMode::COOKIE]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'superadmin' => EnsureUserIsSuperAdmin::class,
            'nhrdc' => EnsureUserIsNhrdcMember::class,
            'fstp' => EnsureUserCanAccessForeignPrograms::class,
            'search.suggest' => AddSearchSuggestionHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Outside local/debug, page visits that fail get a friendly Inertia
        // error page instead of Laravel's default (or a stack trace). JSON
        // requests (axios) keep their normal response for the client to handle.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Expired page/session (CSRF token mismatch) on a page visit: send the
            // user somewhere useful with a plain explanation instead of a raw 419.
            // Axios requests keep the 419 so the session-expired dialog can show.
            if ($status === 419 && ! $request->expectsJson()) {
                // Sign-in and sign-up forms left open too long: just ask to try again on the same page.
                if (! $request->user() && $request->routeIs('login', 'register*', 'password.*', 'verification.*')) {
                    return back()->with('error', FlashMessage::make(
                        'This page expired',
                        'For your security, forms expire after a while.',
                        'Please try again.',
                    ));
                }

                if (! $request->user()) {
                    return redirect()->setIntendedUrl(url()->previous())
                        ->to(route('login'))
                        ->with('error', FlashMessage::make(
                            'Your session has expired',
                            'Sign in again to continue. You will return to the page you were on.',
                        ));
                }

                return back()->with('error', FlashMessage::make(
                    'This page expired',
                    'Your last action was not saved, but what you entered is still on the page.',
                    'Please try again.',
                ));
            }

            if (! app()->isLocal() && ! config('app.debug') && ! $request->expectsJson()
                && in_array($status, [403, 404, 429, 500, 503], true)) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
