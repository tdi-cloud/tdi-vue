<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    // Step 1: Validate form and return employee info for confirmation modal
    public function verify(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'empcode'  => [
                'required', 'string', 'max:255',
                'unique:users,empcode',
                Rule::exists('employees', 'EMPCODE')->where(function ($query) use ($request) {
                    $query->whereRaw('LOWER(EMPCODE) = ?', [strtolower($request->empcode)]);
                }),
            ],
            'email'    => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'empcode.unique' => 'The empcode has already been registered.',
            'email.unique'   => 'The email has already been registered.',
        ]);

        // Get employee info
        $employee = DB::table('employees')
            ->whereRaw('LOWER(EMPCODE) = ?', [strtolower($request->empcode)])
            ->first();

        return response()->json([
            'employee' => [
                'fullname'       => trim($employee->FIRSTNAME . ' ' . $employee->MI . ' ' . $employee->LASTNAME),
                'empcode'        => $employee->EMPCODE,
                'office_division'=> $employee->{'OFFICE/DIVISION'},
            ]
        ]);
    }

    // Step 2: Send OTP after confirmation
    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'email'   => 'required|email',
                'empcode' => 'required|string',
            ]);

            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('registration_otps')->updateOrInsert(
                ['email' => $request->email],
                [
                    'otp'        => Hash::make($otp),
                    'form_data'  => json_encode($request->all()),
                    'expires_at' => Carbon::now()->addMinutes(10),
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $record = DB::table('registration_otps')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return back()->withErrors(['otp' => 'OTP not found. Please register again.']);
        }

        if (Carbon::now()->isAfter($record->expires_at)) {
            DB::table('registration_otps')->where('email', $request->email)->delete();
            return back()->withErrors(['otp' => 'OTP has expired. Please register again.']);
        }

        if (!Hash::check($request->otp, $record->otp)) {
            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $data = json_decode($record->form_data, true);

        $user = User::create([
            'name'     => $data['name'],
            'empcode'  => $data['empcode'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'access'   => 'guest',
        ]);

        DB::table('registration_otps')->where('email', $request->email)->delete();

        event(new Registered($user));
        Auth::login($user);

        return redirect('/');
    }

    public function otpPage(Request $request): Response
    {
        return Inertia::render('auth/VerifyOtp', [
            'email' => $request->query('email'),
        ]);
    }
}