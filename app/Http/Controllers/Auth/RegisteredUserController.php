<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\PendingNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /** Wrong codes allowed before the pending registration is discarded. */
    private const MAX_OTP_ATTEMPTS = 5;

    /** Seconds a user has to wait before another code can be emailed. */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /** Minutes an emailed code stays valid. */
    private const OTP_TTL_MINUTES = 10;

    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    // Step 1: Validate form and return employee info for confirmation modal
    public function verify(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'empcode' => [
                'required', 'string', 'max:255',
                'unique:users,empcode',
                Rule::exists('employees', 'EMPCODE')->where(function ($query) use ($request) {
                    $query->whereRaw('LOWER(EMPCODE) = ?', [strtolower($request->empcode)]);
                }),
            ],
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'empcode.unique' => 'The empcode has already been registered.',
            'email.unique' => 'The email has already been registered.',
        ]);

        // Get employee info
        $employee = DB::table('employees')
            ->whereRaw('LOWER(EMPCODE) = ?', [strtolower($request->empcode)])
            ->first();

        return response()->json([
            'employee' => [
                'fullname' => trim($employee->FIRSTNAME.' '.$employee->MI.' '.$employee->LASTNAME),
                'empcode' => $employee->EMPCODE,
                'office_division' => $employee->{'OFFICE/DIVISION'},
            ],
        ]);
    }

    // Step 2: Send OTP after confirmation
    public function sendOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'empcode' => 'required|string',
            ]);

            $otp = $this->generateOtp();

            DB::table('registration_otps')->updateOrInsert(
                ['email' => $request->email],
                [
                    'otp' => Hash::make($otp),
                    'form_data' => json_encode($request->all()),
                    'attempts' => 0,
                    'expires_at' => Carbon::now()->addMinutes(self::OTP_TTL_MINUTES),
                    'last_sent_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );

            Mail::to($request->email)->send(new RegistrationOtpMail($otp));

            return response()->json(['message' => 'OTP sent successfully.']);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Step 3: Verify OTP and create account
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $record = DB::table('registration_otps')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return $this->otpRejected($request, 'OTP not found. Please register again.', ['expired' => true]);
        }

        if (Carbon::now()->isAfter($record->expires_at)) {
            DB::table('registration_otps')->where('email', $request->email)->delete();

            return $this->otpRejected($request, 'OTP has expired. Please register again.', ['expired' => true]);
        }

        if (! Hash::check($request->otp, $record->otp)) {
            // Atomic increment so parallel guesses can't slip past the limit.
            DB::table('registration_otps')->where('email', $request->email)->increment('attempts');
            $attempts = (int) DB::table('registration_otps')->where('email', $request->email)->value('attempts');

            if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                DB::table('registration_otps')->where('email', $request->email)->delete();

                return $this->otpRejected($request, 'Too many attempts. Please register again.', ['locked' => true]);
            }

            return $this->otpRejected($request, 'Invalid OTP.', ['attempts_left' => self::MAX_OTP_ATTEMPTS - $attempts]);
        }

        $data = json_decode($record->form_data, true);

        $user = User::create([
            'name' => $data['name'],
            'empcode' => $data['empcode'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'access' => 'guest',
        ]);

        DB::table('registration_otps')->where('email', $request->email)->delete();

        // I-deliver ang anumang notification na na-queue habang wala pa
        // siyang account (hal. "na-add ka sa isang program" bago siya
        // nakapag-register).
        PendingNotification::deliverTo($user);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Email verified. Your account is ready.',
                'redirect' => '/',
            ]);
        }

        return redirect('/');
    }

    /**
     * Emails a fresh code for a pending registration. The form data from
     * step 2 is kept, so the user doesn't have to register again.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $record = DB::table('registration_otps')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'expired' => true,
                'message' => 'Registration not found. Please register again.',
            ], 422);
        }

        $retryAfter = $this->resendAvailableIn($record);

        if ($retryAfter > 0) {
            return response()->json([
                'success' => false,
                'message' => "Please wait {$retryAfter} seconds before requesting a new code.",
                'retry_after' => $retryAfter,
            ], 429);
        }

        $otp = $this->generateOtp();

        DB::table('registration_otps')->where('email', $request->email)->update([
            'otp' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => Carbon::now()->addMinutes(self::OTP_TTL_MINUTES),
            'last_sent_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Mail::to($request->email)->send(new RegistrationOtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'A new code has been sent.',
            'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function otpPage(Request $request): Response|RedirectResponse
    {
        $email = $request->string('email')->toString();

        $record = DB::table('registration_otps')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return redirect()->route('register');
        }

        return Inertia::render('auth/VerifyOtp', [
            'email' => $email,
            'resendAvailableIn' => $this->resendAvailableIn($record),
        ]);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Seconds left on the resend cooldown of a pending registration (0 when a new code can be sent now).
     */
    private function resendAvailableIn(object $record): int
    {
        if (! $record->last_sent_at) {
            return 0;
        }

        $availableAt = Carbon::parse($record->last_sent_at)->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return max(0, (int) ceil(Carbon::now()->diffInSeconds($availableAt, false)));
    }

    /**
     * Rejects an OTP attempt: JSON for the animated OTP page, or the original
     * redirect with an `otp` error for regular form posts.
     *
     * @param  array{expired?: bool, locked?: bool, attempts_left?: int}  $details
     */
    private function otpRejected(Request $request, string $message, array $details = []): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message, ...$details], 422);
        }

        return back()->withErrors(['otp' => $message]);
    }
}
