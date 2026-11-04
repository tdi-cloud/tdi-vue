<?php

namespace App\Support;

use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\PendingNotification;
use App\Models\ProgramRegistration;
use App\Models\ProvisionalEmployee;
use App\Models\User;
use App\Notifications\ParticipantAdded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns an approved registration into a real participant: adds the person to
 * the employees data when they aren't listed (keeping a copy in the holding
 * list), creates their guest account with a random temporary password, and
 * enrolls them in the batch.
 */
class RegistrationApprover
{
    /**
     * @return array{registration: ProgramRegistration, temporaryPassword: ?string}
     *
     * @throws ValidationException when the registration can't be approved
     */
    public function approve(ProgramRegistration $registration, User $approver): array
    {
        $temporaryPassword = null;

        $approved = DB::transaction(function () use ($registration, $approver, &$temporaryPassword) {
            $registration = ProgramRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if (! $registration->isPending()) {
                $this->fail('This registration was already reviewed.');
            }

            $batch = Batch::findOrFail($registration->batch_id);
            $participant = $this->existingParticipant($batch, $registration->empcode);

            if ($participant !== null && $participant->batch_id !== $batch->id) {
                $otherBatch = Batch::find($participant->batch_id)?->batch ?? 'another batch';
                $this->fail("{$registration->fullName()} is already a participant in {$otherBatch} of this program. Remove them there first, or reject this registration.");
            }

            [$employee, $provisional] = $this->ensureEmployee($registration);
            [$user, $accountCreated, $temporaryPassword] = $this->ensureAccount($registration, $employee);

            $provisional?->update(['user_id' => $user->id]);

            if ($participant === null) {
                $participant = Participant::create([
                    'sort_order' => Participant::where('batch_id', $batch->id)->count() + 1,
                    'batch_id' => $batch->id,
                    'empcode' => $employee->EMPCODE,
                    'attendance' => 'Pending',
                    'hours' => 0,
                    'added_by' => $approver->name,
                ]);

                PendingNotification::notifyOrQueue($employee->EMPCODE, new ParticipantAdded($participant));
            }

            $registration->update([
                'empcode' => $employee->EMPCODE,
                'status' => ProgramRegistration::STATUS_APPROVED,
                'reviewed_by' => $approver->empcode,
                'reviewed_at' => now(),
                'participant_id' => $participant->id,
                'user_id' => $user->id,
                'account_created' => $accountCreated,
            ]);

            return $registration;
        });

        return ['registration' => $approved, 'temporaryPassword' => $temporaryPassword];
    }

    public function reject(ProgramRegistration $registration, User $reviewer, string $remarks): ProgramRegistration
    {
        return DB::transaction(function () use ($registration, $reviewer, $remarks) {
            $registration = ProgramRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if (! $registration->isPending()) {
                $this->fail('This registration was already reviewed.');
            }

            $registration->update([
                'status' => ProgramRegistration::STATUS_REJECTED,
                'remarks' => $remarks,
                'reviewed_by' => $reviewer->empcode,
                'reviewed_at' => now(),
            ]);

            return $registration;
        });
    }

    /**
     * Gives the account a brand-new temporary password (the old one can't be
     * recovered because only its hash is stored) and restarts its expiry.
     *
     * @throws ValidationException when the person has already set their own password
     */
    public function resetTemporaryPassword(ProgramRegistration $registration): string
    {
        $user = $registration->user;

        if (! $registration->account_created || $user === null || ! $user->must_change_password) {
            $this->fail('This person has already set their own password, so there are no login details to resend. They can use "Forgot password" on the login page.');
        }

        $temporaryPassword = TemporaryPassword::generate();

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'temp_password_expires_at' => now()->addDays(TemporaryPassword::validDays()),
        ]);

        return $temporaryPassword;
    }

    /**
     * Sends the approval email. The temporary password exists only inside
     * this email, never in the database or the logs.
     */
    public function sendApprovalEmail(ProgramRegistration $registration, ?string $temporaryPassword, bool $resent = false): bool
    {
        $registration->loadMissing(['batch.program', 'user']);
        $recipient = $resent ? ($registration->user?->email ?? $registration->email) : $registration->email;

        try {
            Mail::to($recipient)->send(new RegistrationApprovedMail($registration, $temporaryPassword, $resent));

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send registration approval email', [
                'program_registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendRejectionEmail(ProgramRegistration $registration): bool
    {
        $registration->loadMissing('batch.program');

        try {
            Mail::to($registration->email)->send(new RegistrationRejectedMail($registration));

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send registration rejection email', [
                'program_registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Whether the registration's email already belongs to an account of a
     * different employee, which would block creating their account.
     */
    public static function emailBelongsToSomeoneElse(string $email, string $empcode): bool
    {
        return User::whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->whereRaw('LOWER(empcode) != ?', [Str::lower($empcode)])
            ->exists();
    }

    private function existingParticipant(Batch $batch, string $empcode): ?Participant
    {
        $programBatchIds = Batch::where('program_code', $batch->program_code)->pluck('id');

        return Participant::whereIn('batch_id', $programBatchIds)
            ->whereRaw('LOWER(empcode) = ?', [Str::lower($empcode)])
            ->first();
    }

    /**
     * @return array{0: Employee, 1: ?ProvisionalEmployee}
     */
    private function ensureEmployee(ProgramRegistration $registration): array
    {
        $employee = Employee::whereRaw('LOWER(EMPCODE) = ?', [Str::lower($registration->empcode)])->first();

        if ($employee !== null) {
            return [$employee, null];
        }

        $provisional = ProvisionalEmployee::create(array_merge(
            $registration->only(array_keys(ProgramRegistration::EMPLOYEE_COLUMNS)),
            [
                'program_registration_id' => $registration->id,
                'email' => $registration->email,
                'contact_number' => $registration->contact_number,
                'status' => ProvisionalEmployee::STATUS_WAITING,
                'last_checked_at' => now(),
            ],
        ));

        return [$provisional->insertEmployeeRow(), $provisional];
    }

    /**
     * Reuses the employee's existing account, or creates a guest account with
     * a random temporary password that must be changed on first login.
     *
     * @return array{0: User, 1: bool, 2: ?string}
     */
    private function ensureAccount(ProgramRegistration $registration, Employee $employee): array
    {
        $user = User::whereRaw('LOWER(empcode) = ?', [Str::lower($employee->EMPCODE)])->first();

        if ($user !== null) {
            return [$user, false, null];
        }

        if (self::emailBelongsToSomeoneElse($registration->email, $employee->EMPCODE)) {
            $this->fail("The email {$registration->email} already belongs to another person's account. Edit this registration and use a different email, then approve it.");
        }

        $temporaryPassword = TemporaryPassword::generate();

        $user = User::create([
            'name' => $employee->name,
            'empcode' => $employee->EMPCODE,
            'email' => $registration->email,
            'password' => Hash::make($temporaryPassword),
            'access' => 'guest',
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addDays(TemporaryPassword::validDays()),
        ]);

        // Deliver anything that was queued while they had no account yet.
        PendingNotification::deliverTo($user);

        return [$user, true, $temporaryPassword];
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['registration' => $message]);
    }
}
