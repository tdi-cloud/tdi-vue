<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\PendingNotification;
use App\Models\Program;
use App\Models\User;
use App\Notifications\ParticipantAdded;
use App\Notifications\ParticipantRemoved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function pendingNotifTestAdmin(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Admin',
        'FIRSTNAME' => 'Ana',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function pendingNotifTestEmployee(string $empcode, string $lastname): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function pendingNotifTestBatch(): Batch
{
    $program = Program::create([
        'title' => 'Pending Notification Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->toDateString(),
        'date_end' => now()->addDays(2)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

test('adding a participant without a user account queues a pending notification instead of sending one', function () {
    $admin = pendingNotifTestAdmin('EMP-PN-ADM-01');
    $batch = pendingNotifTestBatch();
    $employee = pendingNotifTestEmployee('EMP-PN-NOACC-01', 'Reyes');

    $response = $this->actingAs($admin)->post(route('participants.store'), [
        'batch_id' => $batch->id,
        'empcodes' => [$employee->EMPCODE],
    ]);

    $response->assertRedirect();

    $pending = PendingNotification::where('empcode', $employee->EMPCODE)->get();
    expect($pending)->toHaveCount(1);
    expect($pending->first()->type)->toBe(ParticipantAdded::class);
    expect($pending->first()->data['title'])->toBe('Added to a Program');

    expect(DB::table('notifications')->count())->toBe(0);
});

test('adding a participant with an existing user account notifies them directly, no pending row', function () {
    $admin = pendingNotifTestAdmin('EMP-PN-ADM-02');
    $batch = pendingNotifTestBatch();
    $employee = pendingNotifTestEmployee('EMP-PN-ACC-01', 'Santos');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $response = $this->actingAs($admin)->post(route('participants.store'), [
        'batch_id' => $batch->id,
        'empcodes' => [$employee->EMPCODE],
    ]);

    $response->assertRedirect();

    expect(PendingNotification::where('empcode', $employee->EMPCODE)->count())->toBe(0);
    expect($user->fresh()->notifications)->toHaveCount(1);
    expect($user->fresh()->notifications->first()->data['title'])->toBe('Added to a Program');
});

test('removing a participant without a user account queues a pending notification', function () {
    $admin = pendingNotifTestAdmin('EMP-PN-ADM-03');
    $batch = pendingNotifTestBatch();
    $employee = pendingNotifTestEmployee('EMP-PN-NOACC-02', 'Cruz');

    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Pending', 'hours' => 0, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->delete(route('participants.destroy', $participant));
    $response->assertRedirect();

    $pending = PendingNotification::where('empcode', $employee->EMPCODE)->get();
    expect($pending)->toHaveCount(1);
    expect($pending->first()->type)->toBe(ParticipantRemoved::class);
    expect($pending->first()->data['title'])->toBe('Removed from a Program');
});

test('registering delivers queued pending notifications and clears the pending table', function () {
    $batch = pendingNotifTestBatch();
    $employee = pendingNotifTestEmployee('EMP-PN-REG-01', 'Dela Cruz');

    // I-queue ang notification bago pa magkaroon ng account (via the real
    // add-participant path, hindi manual insert, para totoong end-to-end).
    $admin = pendingNotifTestAdmin('EMP-PN-ADM-04');
    $this->actingAs($admin)->post(route('participants.store'), [
        'batch_id' => $batch->id,
        'empcodes' => [$employee->EMPCODE],
    ]);
    expect(PendingNotification::where('empcode', $employee->EMPCODE)->count())->toBe(1);

    // I-logout ang admin — naka-guest middleware ang registration routes,
    // kaya kung naka-login pa rin ang admin, ire-redirect lang palayo ang
    // request na ito nang hindi pa man na-a-abot ang store() logic.
    $this->post(route('logout'));

    // Simulate na naka-request na siya ng OTP (bypass ang actual email send).
    DB::table('registration_otps')->updateOrInsert(
        ['email' => 'juan.delacruz@example.com'],
        [
            'otp' => Hash::make('123456'),
            'form_data' => json_encode([
                'name' => 'Juan Dela Cruz',
                'empcode' => $employee->EMPCODE,
                'email' => 'juan.delacruz@example.com',
                'password' => 'password123',
            ]),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $response = $this->post(route('register.store'), [
        'email' => 'juan.delacruz@example.com',
        'otp' => '123456',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticated();

    $newUser = User::where('empcode', $employee->EMPCODE)->first();
    expect($newUser)->not->toBeNull();

    expect(PendingNotification::where('empcode', $employee->EMPCODE)->count())->toBe(0);

    $notifications = $newUser->notifications;
    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->data['title'])->toBe('Added to a Program');
    expect($notifications->first()->read_at)->toBeNull();
});

test('non-admin users cannot add participants', function () {
    $batch = pendingNotifTestBatch();
    $employee = pendingNotifTestEmployee('EMP-PN-REG-02', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $response = $this->actingAs($user)->post(route('participants.store'), [
        'batch_id' => $batch->id,
        'empcodes' => [$employee->EMPCODE],
    ]);

    $response->assertForbidden();
});
