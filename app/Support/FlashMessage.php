<?php

namespace App\Support;

/**
 * Structured flash message for the `success` / `error` session keys.
 *
 * Every success should say what was done (title), reassure the user
 * (message), and say what happens next (next). An optional action links to
 * the place the user is likely to go next. The frontend (useFlash.ts) still
 * accepts the older plain-string flashes.
 *
 * Usage: back()->with('success', FlashMessage::make('Program created', 'It is saved …', 'Add a batch next.'))
 */
final class FlashMessage
{
    /**
     * @param  array{label: string, href: string}|null  $action
     * @return array{title: string, message: string, next: string|null, action: array{label: string, href: string}|null}
     */
    public static function make(string $title, string $message, ?string $next = null, ?array $action = null): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'next' => $next,
            'action' => $action,
        ];
    }
}
