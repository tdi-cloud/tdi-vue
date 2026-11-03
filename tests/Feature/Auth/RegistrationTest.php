<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

/**
 * Registration is two steps: the OTP is emailed (register.send-otp), then the
 * user confirms it here (register.store), which creates the account.
 */
function seedRegistrationOtp(string $otp, array $overrides = []): void
{
    DB::table('registration_otps')->insert([
        'email' => 'test@example.com',
        'otp' => Hash::make($otp),
        'form_data' => json_encode([
            'name' => 'Test User',
            'empcode' => 'EMP-REG-01',
            'email' => 'test@example.com',
            'password' => 'password',
        ]),
        'expires_at' => now()->addMinutes(10),
        'created_at' => now(),
        'updated_at' => now(),
    ] + $overrides);
}

test('new users can register by confirming the emailed OTP', function () {
    seedRegistrationOtp('123456');

    $response = $this->post(route('register.store'), ['email' => 'test@example.com', 'otp' => '123456']);

    $this->assertAuthenticated();
    $response->assertRedirect('/');
    expect(User::where('email', 'test@example.com')->value('access'))->toBe('guest');
});

test('a wrong or expired OTP does not create the account', function () {
    seedRegistrationOtp('123456');

    $this->post(route('register.store'), ['email' => 'test@example.com', 'otp' => '000000'])
        ->assertSessionHasErrors(['otp' => 'Invalid OTP.']);

    DB::table('registration_otps')->update(['expires_at' => now()->subMinute()]);

    $this->post(route('register.store'), ['email' => 'test@example.com', 'otp' => '123456'])
        ->assertSessionHasErrors(['otp' => 'OTP has expired. Please register again.']);

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
