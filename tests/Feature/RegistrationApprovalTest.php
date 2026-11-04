<?php

use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\ProgramRegistration;
use App\Models\ProvisionalEmployee;
use App\Models\RegistrationForm;
use App\Models\User;
use App\Notifications\RegistrationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function regApprovalAdmin(string $empcode, string $access = 'admin'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

/**
 * A form whose program was created by the given admin.
 */
function regApprovalForm(?User $creator = null): RegistrationForm
{
    $form = RegistrationForm::factory()->create();
    $form->batch->program->update(['added_by' => $creator?->empcode]);

    return $form->refresh();
}

function regApprovalEmployee(string $empcode = 'EMP-APR-0001'): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Training Division',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'P',
        'POSITION' => 'Training Specialist II',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'TDI',
        'LOCATION' => 'Taguig',
        'SECTION' => 'Curriculum',
        'UNIT' => 'Unit A',
    ]);
}

function regApprovalOtherBatch(RegistrationForm $form): Batch
{
    return Batch::create([
        'program_code' => $form->batch->program_code,
        'batch' => 'Batch 2',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->addDays(30)->toDateString(),
        'date_end' => now()->addDays(31)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

/**
 * The temporary password from the one approval email that was sent.
 */
function regApprovalSentPassword(): ?string
{
    $password = null;

    Mail::assertSent(RegistrationApprovedMail::class, function (RegistrationApprovedMail $mail) use (&$password) {
        $password = $mail->temporaryPassword;

        return true;
    });

    return $password;
}

beforeEach(function () {
    Mail::fake();
});

test('approving a listed registration enrolls the person and creates a guest account with an emailed temporary password', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    regApprovalEmployee();
    $registration = ProgramRegistration::factory()->forForm($form)->listed()->create([
        'empcode' => 'EMP-APR-0001',
        'firstname' => 'Juan',
        'lastname' => 'Dela Cruz',
        'email' => 'juan@example.com',
    ]);

    $this->actingAs($creator)
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('success');

    $registration->refresh();
    $user = User::where('empcode', 'EMP-APR-0001')->sole();
    $participant = Participant::sole();
    $password = regApprovalSentPassword();

    expect($registration)
        ->status->toBe(ProgramRegistration::STATUS_APPROVED)
        ->reviewed_by->toBe('ADM-APR-CREATOR')
        ->participant_id->toBe($participant->id)
        ->user_id->toBe($user->id)
        ->account_created->toBeTrue()
        ->and($registration->reviewed_at)->not->toBeNull()
        ->and($participant)
        ->batch_id->toBe($form->batch_id)
        ->empcode->toBe('EMP-APR-0001')
        ->attendance->toBe('Pending')
        ->and($user)
        ->access->toBe('guest')
        ->email->toBe('juan@example.com')
        ->must_change_password->toBeTrue()
        ->and($user->temp_password_expires_at->isFuture())->toBeTrue()
        ->and($password)->toBeString()->not->toBe('temp123456')
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and(ProvisionalEmployee::count())->toBe(0);

    Mail::assertSent(RegistrationApprovedMail::class, fn ($mail) => $mail->hasTo('juan@example.com'));
});

test('every new account gets a different random password, and the plain password is stored nowhere', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $first = ProgramRegistration::factory()->forForm($form)->create();
    $second = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs($creator)->post(route('program-registrations.approve', $first));
    $this->actingAs($creator)->post(route('program-registrations.approve', $second));

    $passwords = [];
    Mail::assertSent(RegistrationApprovedMail::class, function (RegistrationApprovedMail $mail) use (&$passwords) {
        $passwords[] = $mail->temporaryPassword;

        return true;
    });

    expect($passwords)->toHaveCount(2)
        ->and($passwords[0])->not->toBe($passwords[1])
        ->and(strlen($passwords[0]))->toBe(12);

    foreach (['users', 'program_registrations', 'provisional_employees', 'notifications', 'pending_notifications', 'jobs'] as $table) {
        $dump = json_encode(DB::table($table)->get());

        foreach ($passwords as $password) {
            expect(str_contains($dump, $password))->toBeFalse("The plain password was found in {$table}.");
        }
    }
});

test('approving a manual registration also adds the person to the employees data and the holding list', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $registration = ProgramRegistration::factory()->forForm($form)->create([
        'empcode' => 'NEW-APR-0001',
        'lastname' => 'Santos',
        'firstname' => 'Maria',
        'office_division' => 'Admin Division',
        'location' => null,
    ]);

    $this->actingAs($creator)->post(route('program-registrations.approve', $registration));

    $employee = Employee::where('EMPCODE', 'NEW-APR-0001')->sole();
    $holding = ProvisionalEmployee::sole();

    expect($employee)
        ->LASTNAME->toBe('Santos')
        ->getAttribute('OFFICE/DIVISION')->toBe('Admin Division')
        ->LOCATION->toBe('')
        ->and($holding)
        ->empcode->toBe('NEW-APR-0001')
        ->status->toBe(ProvisionalEmployee::STATUS_WAITING)
        ->program_registration_id->toBe($registration->id)
        ->employee_row_id->toBe($employee->id)
        ->user_id->toBe(User::where('empcode', 'NEW-APR-0001')->value('id'))
        ->and($holding->insertedEmployeeRow()?->id)->toBe($employee->id);
});

test('a person who already has an account keeps it and their password is not changed', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    regApprovalEmployee();
    $existing = User::factory()->create(['empcode' => 'EMP-APR-0001', 'email' => 'juan.old@example.com', 'password' => Hash::make('my-own-password')]);
    $registration = ProgramRegistration::factory()->forForm($form)->listed()->create(['empcode' => 'EMP-APR-0001', 'email' => 'juan.new@example.com']);

    $this->actingAs($creator)->post(route('program-registrations.approve', $registration))->assertSessionHas('success');

    expect($registration->refresh())
        ->user_id->toBe($existing->id)
        ->account_created->toBeFalse()
        ->and(User::where('empcode', 'EMP-APR-0001')->count())->toBe(1)
        ->and(Hash::check('my-own-password', $existing->refresh()->password))->toBeTrue()
        ->and($existing->must_change_password)->toBeFalse()
        ->and(regApprovalSentPassword())->toBeNull();
});

test('approval is blocked when the email belongs to someone else\'s account', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    User::factory()->create(['empcode' => 'SOMEONE-ELSE', 'email' => 'shared@example.com']);
    $registration = ProgramRegistration::factory()->forForm($form)->create(['email' => 'Shared@Example.com']);

    $this->actingAs($creator)
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('error');

    expect($registration->refresh()->status)->toBe(ProgramRegistration::STATUS_PENDING)
        ->and(Participant::count())->toBe(0)
        ->and(Employee::count())->toBe(0)
        ->and(ProvisionalEmployee::count())->toBe(0);

    Mail::assertNothingSent();
});

test('approval is blocked when the person is already a participant in another batch of the program', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    regApprovalEmployee();
    Participant::create([
        'batch_id' => regApprovalOtherBatch($form)->id,
        'empcode' => 'EMP-APR-0001',
        'attendance' => 'Pending',
        'added_by' => 'test',
    ]);
    $registration = ProgramRegistration::factory()->forForm($form)->listed()->create(['empcode' => 'EMP-APR-0001']);

    $this->actingAs($creator)
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('error');

    expect($registration->refresh()->status)->toBe(ProgramRegistration::STATUS_PENDING);
});

test('a registration can only be approved once', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs($creator)->post(route('program-registrations.approve', $registration))->assertSessionHas('success');
    $this->actingAs($creator)->post(route('program-registrations.approve', $registration))->assertSessionHas('error');

    expect(Participant::count())->toBe(1);
});

test('a super admin can approve registrations for a program someone else created', function () {
    $form = regApprovalForm(regApprovalAdmin('ADM-APR-CREATOR'));
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs(regApprovalAdmin('SUP-APR-01', 'superadmin'))
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('success');

    expect($registration->refresh()->status)->toBe(ProgramRegistration::STATUS_APPROVED);
});

test('on a program with no recorded creator, only a super admin can approve', function () {
    $form = regApprovalForm(null);
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs(regApprovalAdmin('ADM-APR-ANY'))
        ->post(route('program-registrations.approve', $registration))
        ->assertForbidden();

    $this->actingAs(regApprovalAdmin('SUP-APR-01', 'superadmin'))
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('success');
});

test('an admin who did not create the program cannot review its registrations', function (string $action) {
    $form = regApprovalForm(regApprovalAdmin('ADM-APR-CREATOR'));
    $pending = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs(regApprovalAdmin('ADM-APR-OTHER'));

    $response = match ($action) {
        'approve' => $this->post(route('program-registrations.approve', $pending)),
        'approve all' => $this->post(route('registration-forms.approve', $form)),
        'reject' => $this->post(route('program-registrations.reject', $pending), ['remarks' => 'No.']),
        'edit' => $this->put(route('program-registrations.update', $pending), ['email' => 'x@example.com', 'contact_number' => '09170000000']),
        'resend login' => $this->post(route('program-registrations.resend-login', $pending)),
    };

    $response->assertForbidden();

    expect($pending->refresh()->status)->toBe(ProgramRegistration::STATUS_PENDING);
})->with(['approve', 'approve all', 'reject', 'edit', 'resend login']);

test('guests and non-admins cannot reach the review actions', function () {
    $form = regApprovalForm(regApprovalAdmin('ADM-APR-CREATOR'));
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->post(route('program-registrations.approve', $registration))->assertRedirect(route('login'));

    $this->actingAs(regApprovalAdmin('USR-APR-01', 'user'))
        ->post(route('program-registrations.approve', $registration))
        ->assertForbidden();
});

test('the registration page tells the creator whether approving will create an account or is blocked', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    User::factory()->create(['empcode' => 'HAS-ACCOUNT']);
    User::factory()->create(['empcode' => 'SOMEONE-ELSE', 'email' => 'taken@example.com']);
    ProgramRegistration::factory()->forForm($form)->create(['empcode' => 'NEW-ONE', 'created_at' => now()->subMinutes(3)]);
    ProgramRegistration::factory()->forForm($form)->create(['empcode' => 'HAS-ACCOUNT', 'created_at' => now()->subMinutes(2)]);
    ProgramRegistration::factory()->forForm($form)->create(['empcode' => 'NEW-TWO', 'email' => 'taken@example.com', 'created_at' => now()->subMinute()]);

    $this->actingAs($creator)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertInertia(fn ($page) => $page
            ->has('registrations', 3)
            ->where('registrations.0.account', 'will_create')
            ->where('registrations.1.account', 'existing')
            ->where('registrations.2.account', 'email_taken')
            ->where('registrations.0.in_employee_list', false)
        );
});

test('approving all pending registrations skips the ones that cannot be approved and reports them', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    User::factory()->create(['empcode' => 'SOMEONE-ELSE', 'email' => 'taken@example.com']);
    ProgramRegistration::factory()->forForm($form)->count(2)->create();
    $blocked = ProgramRegistration::factory()->forForm($form)->create(['email' => 'taken@example.com']);
    ProgramRegistration::factory()->forForm($form)->rejected()->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.approve', $form))
        ->assertSessionHas('error', fn ($flash) => str_contains($flash['message'], $blocked->fullName()));

    expect(ProgramRegistration::where('status', ProgramRegistration::STATUS_APPROVED)->count())->toBe(2)
        ->and($blocked->refresh()->status)->toBe(ProgramRegistration::STATUS_PENDING)
        ->and(Participant::count())->toBe(2);

    Mail::assertSent(RegistrationApprovedMail::class, 2);
});

test('rejecting requires a reason, frees the slot, and emails the reason', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $form->update(['slot_limit' => 1]);
    $registration = ProgramRegistration::factory()->forForm($form)->create(['email' => 'maria@example.com']);

    $this->actingAs($creator)
        ->post(route('program-registrations.reject', $registration), ['remarks' => ''])
        ->assertSessionHasErrors('remarks');

    expect($form->refresh()->isFull())->toBeTrue();

    $this->actingAs($creator)
        ->post(route('program-registrations.reject', $registration), ['remarks' => 'This batch is for Region IV-A only.'])
        ->assertSessionHas('success');

    expect($registration->refresh())
        ->status->toBe(ProgramRegistration::STATUS_REJECTED)
        ->remarks->toBe('This batch is for Region IV-A only.')
        ->reviewed_by->toBe('ADM-APR-CREATOR')
        ->and($form->refresh()->isFull())->toBeFalse()
        ->and(Participant::count())->toBe(0);

    Mail::assertSent(RegistrationRejectedMail::class, fn ($mail) => $mail->hasTo('maria@example.com'));
});

test('the creator can correct a manual registration before approving it', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $registration = ProgramRegistration::factory()->forForm($form)->create(['empcode' => 'TYPO-001']);
    regApprovalEmployee();

    $this->actingAs($creator)->put(route('program-registrations.update', $registration), [
        'empcode' => 'NEW-APR-0009',
        'lastname' => 'Reyes',
        'firstname' => 'Ana',
        'mi' => null,
        'position' => 'Administrative Officer',
        'sg' => '11',
        'plantilla_status' => 'Permanent',
        'sex' => 'F',
        'region' => 'NCR',
        'office_division' => 'Training Division',
        'email' => 'Ana.Reyes@Example.com',
        'contact_number' => '09171234567',
    ])->assertSessionHasNoErrors();

    // Office, location, section and unit follow from the office/division.
    expect($registration->refresh())
        ->empcode->toBe('NEW-APR-0009')
        ->lastname->toBe('Reyes')
        ->email->toBe('ana.reyes@example.com')
        ->office_division->toBe('Training Division')
        ->office->toBe('TDI')
        ->location->toBe('Taguig')
        ->section->toBe('Curriculum')
        ->unit->toBe('Unit A');
});

test('a listed registration can only have its email and contact number corrected', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    regApprovalEmployee();
    $registration = ProgramRegistration::factory()->forForm($form)->listed()->create(['empcode' => 'EMP-APR-0001', 'lastname' => 'Dela Cruz']);

    $this->actingAs($creator)->put(route('program-registrations.update', $registration), [
        'lastname' => 'Changed',
        'email' => 'fixed@example.com',
        'contact_number' => '09170000001',
    ])->assertSessionHasNoErrors();

    expect($registration->refresh())
        ->lastname->toBe('Dela Cruz')
        ->email->toBe('fixed@example.com');
});

test('resending login details replaces the temporary password, only while it has not been changed', function () {
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $form = regApprovalForm($creator);
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs($creator)->post(route('program-registrations.approve', $registration));
    $user = User::where('empcode', $registration->empcode)->sole();
    $firstPassword = regApprovalSentPassword();

    Mail::fake();

    $this->actingAs($creator)
        ->post(route('program-registrations.resend-login', $registration))
        ->assertSessionHas('success');

    $secondPassword = regApprovalSentPassword();

    expect($secondPassword)->not->toBe($firstPassword)
        ->and(Hash::check($secondPassword, $user->refresh()->password))->toBeTrue()
        ->and(Hash::check($firstPassword, $user->password))->toBeFalse();

    $user->update(['must_change_password' => false]);
    Mail::fake();

    $this->actingAs($creator)
        ->post(route('program-registrations.resend-login', $registration))
        ->assertSessionHas('error');

    Mail::assertNothingSent();
});

test('the program creator and super admins are notified of a new registration', function () {
    Notification::fake();
    $creator = regApprovalAdmin('ADM-APR-CREATOR');
    $superAdmin = regApprovalAdmin('SUP-APR-01', 'superadmin');
    $otherAdmin = regApprovalAdmin('ADM-APR-OTHER');
    $form = regApprovalForm($creator);
    regApprovalEmployee();

    $this->post(route('join.submit', $form->slug), [
        'source' => 'listed',
        'empcode' => 'EMP-APR-0001',
        'email' => 'juan@example.com',
        'contact_number' => '09171234567',
        'data_privacy_consent' => true,
    ])->assertRedirect(route('join.success', $form->slug));

    Notification::assertSentTo([$creator, $superAdmin], RegistrationSubmitted::class);
    Notification::assertNotSentTo($otherAdmin, RegistrationSubmitted::class);
});
