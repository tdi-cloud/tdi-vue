<?php

use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // CSRF checks are skipped while testing, so throw what VerifyCsrfToken throws on an expired token.
    Route::middleware('web')->post('/__session-expired-test', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
});

test('an expired-page submit by a signed-in user redirects back with a flash message', function () {
    $this->actingAs(User::factory()->create());

    $this->from('/programs')
        ->post('/__session-expired-test', ['title' => 'Unsaved work'])
        ->assertRedirect('/programs')
        ->assertSessionHas('error.title', 'This page expired')
        ->assertSessionHas('error.message', 'Your last action was not saved, but what you entered is still on the page.');
});

test('an expired-session submit by a signed-out user goes to login and returns to the same page afterwards', function () {
    $this->from('/my-programs/5')
        ->post('/__session-expired-test')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error.title', 'Your session has expired')
        ->assertSessionHas('url.intended', url('/my-programs/5'));
});

test('axios requests keep the 419 status so the client can show the session-expired dialog', function () {
    $this->postJson('/__session-expired-test')->assertStatus(419);
});

test('an expired sign-in or sign-up form asks the guest to try again on the same page', function () {
    Route::middleware('web')->post('/__register-expired-test', fn () => throw new TokenMismatchException('CSRF token mismatch.'))
        ->name('register.expired-test');

    $this->from('/register')
        ->post('/__register-expired-test')
        ->assertRedirect('/register')
        ->assertSessionHas('error.title', 'This page expired')
        ->assertSessionHas('error.next', 'Please try again.');
});
