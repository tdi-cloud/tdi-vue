<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Participant;
use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Support\EmployeeFieldOptions;
use App\Support\FlashMessage;
use App\Support\RegistrationApprover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Approve, reject or correct the registrations people submitted through a
 * batch's public form. Only the form's owner, its editors or a superadmin can
 * do this (see RegistrationForm::canBeEditedBy()).
 */
class RegistrationReviewController extends Controller
{
    public function __construct(private RegistrationApprover $approver) {}

    // POST /program-registrations/{programRegistration}/approve
    public function approve(Request $request, ProgramRegistration $programRegistration): RedirectResponse
    {
        $this->ensureCanReview($request, $programRegistration->form);

        try {
            ['registration' => $registration, 'temporaryPassword' => $password] = $this->approver->approve($programRegistration, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', FlashMessage::make('Registration not approved', Arr::first(Arr::flatten($e->errors()))));
        }

        $name = $registration->fullName();
        $emailed = $this->approver->sendApprovalEmail($registration, $password);

        if (! $emailed) {
            return back()->with('error', FlashMessage::make(
                "{$name} is approved, but the email didn't go out",
                'They were added to the batch, but we could not send their approval email.',
                $password ? 'Use "Resend login details" so they get a new temporary password.' : 'Let them know they were approved.',
            ));
        }

        return back()->with('success', FlashMessage::make(
            "{$name} is approved",
            $password
                ? "They were added to the batch, and their login details were emailed to {$registration->email}."
                : 'They were added to the batch. They already had an account, so we emailed them to log in as usual.',
        ));
    }

    // POST /registration-forms/{registrationForm}/approve
    // Approves the selected pending registrations (or all of them when none are given).
    public function bulkApprove(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanReview($request, $registrationForm);

        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $pending = $registrationForm->registrations()
            ->where('status', ProgramRegistration::STATUS_PENDING)
            ->when($data['ids'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->orderBy('created_at')
            ->get();

        $approved = 0;
        $emailFailures = 0;
        $skipped = [];

        foreach ($pending as $registration) {
            try {
                ['registration' => $done, 'temporaryPassword' => $password] = $this->approver->approve($registration, $request->user());
            } catch (ValidationException $e) {
                $skipped[] = $registration->fullName().': '.Arr::first(Arr::flatten($e->errors()));

                continue;
            }

            $approved++;

            if (! $this->approver->sendApprovalEmail($done, $password)) {
                $emailFailures++;
            }
        }

        if ($approved === 0) {
            return back()->with('error', FlashMessage::make(
                'No registrations approved',
                $skipped ? implode(' ', $skipped) : 'There were no pending registrations to approve.',
            ));
        }

        $message = 'They were added to the batch and emailed.';

        if ($emailFailures > 0) {
            $message = "They were added to the batch, but {$emailFailures} email(s) could not be sent. Use \"Resend login details\" for those people.";
        }

        if ($skipped) {
            $message .= ' Not approved: '.implode(' ', $skipped);
        }

        return back()->with($skipped || $emailFailures ? 'error' : 'success', FlashMessage::make(
            "{$approved} registration(s) approved",
            $message,
        ));
    }

    // POST /program-registrations/{programRegistration}/reject
    public function reject(Request $request, ProgramRegistration $programRegistration): RedirectResponse
    {
        $this->ensureCanReview($request, $programRegistration->form);

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ], [], ['remarks' => 'reason']);

        try {
            $registration = $this->approver->reject($programRegistration, $request->user(), $data['remarks']);
        } catch (ValidationException $e) {
            return back()->with('error', FlashMessage::make('Registration not rejected', Arr::first(Arr::flatten($e->errors()))));
        }

        $emailed = $this->approver->sendRejectionEmail($registration);

        return back()->with('success', FlashMessage::make(
            "{$registration->fullName()}'s registration was rejected",
            $emailed ? 'We emailed them the reason you gave.' : "The email with your reason couldn't be sent, so please let them know.",
            'Their slot is free again for someone else.',
        ));
    }

    // PUT /program-registrations/{programRegistration}
    // Corrects a pending registration before it is approved. Employee details can
    // only be edited for people who typed them in; listed ones come from HR's list.
    public function update(Request $request, ProgramRegistration $programRegistration): RedirectResponse
    {
        $this->ensureCanReview($request, $programRegistration->form);

        if (! $programRegistration->isPending()) {
            return back()->with('error', FlashMessage::make('Registration not changed', 'Only pending registrations can be edited.'));
        }

        $rules = [
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],
        ];

        $isManual = $programRegistration->source === ProgramRegistration::SOURCE_MANUAL;

        if ($isManual) {
            $rules += [
                'empcode' => ['required', 'string', 'max:50'],
                'lastname' => ['required', 'string', 'max:255'],
                'firstname' => ['required', 'string', 'max:255'],
                'mi' => ['nullable', 'string', 'max:10'],
                'position' => ['required', 'string', 'max:255'],
                'sg' => ['nullable', 'string', 'max:20'],
                'plantilla_status' => ['required', 'string', 'max:255'],
                'sex' => ['required', 'string', 'max:20'],
                'region' => ['required', 'string', 'max:255'],
                'office_division' => ['required', 'string', 'max:255'],
            ];
        }

        $data = $request->validate($rules, [], [
            'empcode' => 'employee code',
            'contact_number' => 'contact number',
            'office_division' => 'office/division',
            'plantilla_status' => 'plantilla status',
        ]);

        $data['email'] = Str::lower($data['email']);

        if ($isManual) {
            $data = array_merge($data, EmployeeFieldOptions::detailsForDivision($data['region'], $data['office_division']));
        }

        if ($isManual && Str::lower($data['empcode']) !== Str::lower($programRegistration->empcode)) {
            $this->ensureEmpcodeIsFree($programRegistration, $data['empcode']);
        }

        $programRegistration->update($data);

        return back()->with('success', FlashMessage::make('Registration updated', 'Your corrections are saved. You can approve it now.'));
    }

    // POST /program-registrations/{programRegistration}/resend-login
    public function resendLogin(Request $request, ProgramRegistration $programRegistration): RedirectResponse
    {
        $this->ensureCanReview($request, $programRegistration->form);

        try {
            $password = $this->approver->resetTemporaryPassword($programRegistration);
        } catch (ValidationException $e) {
            return back()->with('error', FlashMessage::make('Login details not sent', Arr::first(Arr::flatten($e->errors()))));
        }

        $programRegistration->refresh();

        if (! $this->approver->sendApprovalEmail($programRegistration, $password, resent: true)) {
            return back()->with('error', FlashMessage::make(
                "The email didn't go out",
                'A new temporary password was set, but we could not email it.',
                'Check the email address and try again.',
            ));
        }

        return back()->with('success', FlashMessage::make(
            'New login details sent',
            "{$programRegistration->fullName()} got a new temporary password at {$programRegistration->user->email}. The old one no longer works.",
        ));
    }

    private function ensureCanReview(Request $request, ?RegistrationForm $form): void
    {
        abort_unless(
            $form?->canBeEditedBy($request->user()) === true,
            403,
            'Only the owner of this registration form, its editors or a superadmin can review its registrations.',
        );
    }

    /**
     * A corrected employee code must not belong to someone already in the
     * employee list, or to another participant or registration of this program.
     */
    private function ensureEmpcodeIsFree(ProgramRegistration $registration, string $empcode): void
    {
        $lower = Str::lower($empcode);

        if (Employee::whereRaw('LOWER(EMPCODE) = ?', [$lower])->exists()) {
            throw ValidationException::withMessages([
                'empcode' => 'This employee code is already in the employee list. Reject this registration and ask them to register by searching their name.',
            ]);
        }

        $programBatchIds = $registration->batch->program?->batches()->pluck('id') ?? collect([$registration->batch_id]);

        $taken = Participant::whereIn('batch_id', $programBatchIds)->whereRaw('LOWER(empcode) = ?', [$lower])->exists()
            || ProgramRegistration::whereIn('batch_id', $programBatchIds)
                ->whereKeyNot($registration->id)
                ->holdingSlot()
                ->whereRaw('LOWER(empcode) = ?', [$lower])
                ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'empcode' => 'Someone with this employee code is already a participant or registrant of this program.',
            ]);
        }
    }
}
