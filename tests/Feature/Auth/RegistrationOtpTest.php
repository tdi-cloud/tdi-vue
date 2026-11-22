<?php

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A registration waiting on its emailed code, as register.send-otp leaves it.
 */
function createPendingRegistration(string $otp, array $overrides = []): void
{
    DB::table('registration_otps')->insert(array_merge([
        'email' => 'otp@example.com',
        'otp' => Hash::make($otp),
        'form_data' => json_encode([
            'name' => 'Otp User',
            'empcode' => 'EMP-OTP-01',
            'email' => 'otp@example.com',
            'password' => 'password',
        ]),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
        'last_sent_at' => now()->subMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function pendingRegistration(): ?object
{
    return DB::table('registration_otps')->where('email', 'otp@example.com')->first();
}

beforeEach(function () {
    Mail::fake();
});

test('verifying the right code over JSON logs the user in and returns the redirect', function () {
    createPendingRegistration('123456');

    $this->postJson(route('register.store'), ['email' => 'otp@example.com', 'otp' => '123456'])
        ->assertSuccessful()
        ->assertJson(['success' => true, 'redirect' => '/']);

    $this->assertAuthenticatedAs(User::where('email', 'otp@example.com')->firstOrFail());
    expect(pendingRegistration())->toBeNull();
});

test('a wrong code over JSON returns 422 with fewer attempts left each try', function () {
    createPendingRegistration('123456');

    foreach ([4, 3, 2] as $attemptsLeft) {
        $this->postJson(route('register.store'), ['email' => 'otp@example.com', 'otp' => '000000'])
            ->assertUnprocessable()
            ->assertJson(['success' => false, 'message' => 'Invalid OTP.', 'attempts_left' => $attemptsLeft]);
    }

    $this->assertGuest();
    expect((int) pendingRegistration()->attempts)->toBe(3);
});

test('the fifth wrong code deletes the pending registration and locks it', function () {
    createPendingRegistration('123456');

    foreach (range(1, 4) as $attempt) {
        $this->postJson(route('register.store'), ['email' => 'otp@example.com', 'otp' => '000000'])->assertUnprocessable();
    }

    $this->postJson(route('register.store'), ['email' => 'otp@example.com', 'otp' => '000000'])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'locked' => true, 'message' => 'Too many attempts. Please register again.']);

    expect(pendingRegistration())->toBeNull();

    // Even the right code is useless now.
    $this->postJson(route('register.store'), ['email' => 'otp@example.com', 'otp' => '123456'])
        ->assertUnprocessable()
        ->assertJson(['expired' => true]);

    $this->assertGuest();
});

test('the attempt limit also applies to regular form posts', function () {
    createPendingRegistration('123456', ['attempts' => 4]);

    $this->post(route('register.store'), ['email' => 'otp@example.com', 'otp' => '000000'])
        ->assertSessionHasErrors(['otp' => 'Too many attempts. Please register again.']);

    expect(pendingRegistration())->toBeNull();
});

test('resending emails a new code, resets attempts and keeps the form data', function () {
    createPendingRegistration('123456', ['attempts' => 3]);
    $before = pendingRegistration();

    $this->postJson(route('register.resend-otp'), ['email' => 'otp@example.com'])
        ->assertSuccessful()
        ->assertJson(['success' => true, 'message' => 'A new code has been sent.', 'resend_available_in' => 60]);

    $after = pendingRegistration();
    $sentOtp = null;

    Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use (&$sentOtp) {
        $sentOtp = $mail->otp;

        return $mail->hasTo('otp@example.com');
    });

    expect($after->otp)->not->toBe($before->otp)
        ->and(Hash::check($sentOtp, $after->otp))->toBeTrue()
        ->and((int) $after->attempts)->toBe(0)
        ->and($after->form_data)->toBe($before->form_data);
});

test('resending within 60 seconds returns 429 with retry_after', function () {
    $this->freezeSecond();
    createPendingRegistration('123456', ['last_sent_at' => now()->subSeconds(20)]);

    $this->postJson(route('register.resend-otp'), ['email' => 'otp@example.com'])
        ->assertTooManyRequests()
        ->assertJson(['success' => false, 'retry_after' => 40]);

    Mail::assertNothingSent();
});

test('resending without a pending registration asks the user to register again', function () {
    $this->postJson(route('register.resend-otp'), ['email' => 'nobody@example.com'])
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'expired' => true]);

    Mail::assertNothingSent();
});

test('the OTP page redirects to register when there is no pending registration', function () {
    $this->get(route('register.otp-page', ['email' => 'nobody@example.com']))
        ->assertRedirect(route('register'));
});

test('the OTP page passes the seconds left on the resend cooldown', function () {
    $this->freezeSecond();
    createPendingRegistration('123456', ['last_sent_at' => now()->subSeconds(15)]);

    $this->get(route('register.otp-page', ['email' => 'otp@example.com']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/VerifyOtp')
            ->where('email', 'otp@example.com')
            ->where('resendAvailableIn', 45)
        );
});
