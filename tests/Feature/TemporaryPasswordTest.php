<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function tempPasswordUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'empcode' => 'EMP-TMP-0001',
        'email' => 'new.employee@example.com',
        'password' => Hash::make('Temp4ryPass9'),
        'access' => 'guest',
        'must_change_password' => true,
        'temp_password_expires_at' => now()->addDays(7),
    ], $overrides));
}

test('temporary passwords are 12 characters, mix letters and digits, and avoid look-alike characters', function () {
    foreach (range(1, 50) as $ignored) {
        $password = TemporaryPassword::generate();

        expect(strlen($password))->toBe(12)
            ->and($password)->toMatch('/[A-Z]/')
            ->toMatch('/[a-z]/')
            ->toMatch('/[0-9]/')
            ->not->toMatch('/[0O1lI]/');
    }
});

test('a user who still has a temporary password is sent to the password page from every page', function () {
    $user = tempPasswordUser();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('password.edit'));
    $this->actingAs($user)->get(route('programs.my-programs'))->assertRedirect(route('password.edit'));

    $inertiaVersion = app(HandleInertiaRequests::class)->version(request());

    $this->actingAs($user)
        ->get(route('home'), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $inertiaVersion, 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertRedirect(route('password.edit'));
});

test('the password page and background requests still work while the password must be changed', function () {
    $user = tempPasswordUser();

    $this->actingAs($user)->get(route('password.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Password')->where('auth.user.must_change_password', true));

    $this->actingAs($user)->getJson(route('notifications.index'))->assertOk();
});

test('users without a temporary password are not redirected', function () {
    $user = User::factory()->create(['access' => 'guest']);

    $this->actingAs($user)->get(route('password.edit'))->assertOk();
    $this->actingAs($user)->get(route('home'))->assertOk();
});

test('setting a new password clears the temporary password flags and goes home', function () {
    $user = tempPasswordUser();

    $this->actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'Temp4ryPass9',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->refresh())
        ->must_change_password->toBeFalse()
        ->temp_password_expires_at->toBeNull()
        ->and(Hash::check('my-new-password', $user->password))->toBeTrue();

    $this->actingAs($user)->get(route('home'))->assertOk();
});

test('the new password cannot be the same as the temporary one', function () {
    $user = tempPasswordUser();

    $this->actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'Temp4ryPass9',
            'password' => 'Temp4ryPass9',
            'password_confirmation' => 'Temp4ryPass9',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('a temporary password still within its time lets the person log in', function () {
    tempPasswordUser();

    $this->post(route('login'), ['email' => 'new.employee@example.com', 'password' => 'Temp4ryPass9']);

    $this->assertAuthenticated();
});

test('an expired temporary password is refused at login', function () {
    tempPasswordUser(['temp_password_expires_at' => now()->subMinute()]);

    $this->post(route('login'), ['email' => 'new.employee@example.com', 'password' => 'Temp4ryPass9'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('resetting the password through "Forgot password" also clears the temporary password', function () {
    Notification::fake();
    $user = tempPasswordUser(['temp_password_expires_at' => now()->subDay()]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->refresh())
        ->must_change_password->toBeFalse()
        ->temp_password_expires_at->toBeNull();
});
