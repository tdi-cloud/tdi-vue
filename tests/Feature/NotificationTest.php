<?php

use App\Models\Batch;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\Program;
use App\Models\TnaAssessment;
use App\Models\User;
use App\Notifications\FasdAssigned;
use App\Notifications\NewSubordinateToRate;
use App\Notifications\ParticipantAdded;
use App\Notifications\SelfRatingReviewed;
use App\Notifications\SelfRatingSubmitted;
use Illuminate\Support\Facades\Notification;

function notifTestEmployee(string $empcode, string $position, string $lastname = 'Dela Cruz'): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

test('self-rating submission notifies the employee and the selected supervisor', function () {
    Notification::fake();

    $position = 'Test Officer Notif 1';
    $employee = notifTestEmployee('EMP-401', $position);
    $supervisorEmployee = notifTestEmployee('EMP-402', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)->post(route('tna.self-rating.store'), [
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    Notification::assertSentTo($user, SelfRatingSubmitted::class);
    Notification::assertSentTo($supervisorUser, NewSubordinateToRate::class);
});

test('supervisory review notifies the employee that the result is ready and fasd is assigned', function () {
    Notification::fake();

    $position = 'Test Officer Notif 2';
    $employee = notifTestEmployee('EMP-403', $position);
    $supervisorEmployee = notifTestEmployee('EMP-404', 'Supervisor');
    $fasdEmployee = notifTestEmployee('EMP-405', 'FASD', 'Santos');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);
    $assessment->ratings()->create([
        'competency_id' => $competency->id,
        'criticality' => 2,
        'competence' => 3,
        'frequency' => 2,
    ]);

    $this->actingAs($supervisorUser)->post(route('tna.supervisory.store', $assessment), [
        'name' => 'Maria Supervisor',
        'subordinate_name' => 'Juan D. Dela Cruz',
        'subordinate_position' => $position,
        'signature' => null,
        'fasd_empcode' => $fasdEmployee->EMPCODE,
        'fasd_name' => 'Juan D. Santos',
        'fasd_position' => 'FASD',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    Notification::assertSentTo($user, SelfRatingReviewed::class);
    Notification::assertSentTo($user, FasdAssigned::class);
    Notification::assertSentTo(
        $user,
        SelfRatingReviewed::class,
        fn ($notification, $channels) => in_array('mail', $channels)
    );
});

test('self-rating-reviewed mail links to the tna result page', function () {
    $employee = notifTestEmployee('EMP-414', 'Test Position');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => 'Test Position',
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => 'EMP-999',
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $mail = (new SelfRatingReviewed($assessment))->toMail($user);

    expect($mail->subject)->toBe('Your TNA Result is Ready');
    expect($mail->actionUrl)->toBe(route('tna.result.show', $assessment->id));
});

test('changing the fasd signatory notifies the employee again', function () {
    Notification::fake();

    $position = 'Test Officer Notif 3';
    $employee = notifTestEmployee('EMP-406', $position);
    $supervisorEmployee = notifTestEmployee('EMP-407', 'Supervisor');
    $newFasd = notifTestEmployee('EMP-408', 'FASD', 'Cruz');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
        'supervisor_form' => ['name' => 'Maria Supervisor'],
    ]);

    $this->actingAs($supervisorUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $newFasd->EMPCODE,
        'fasd_name' => 'Juan D. Cruz',
    ]);

    Notification::assertSentTo($user, FasdAssigned::class);
});

test('adding a participant notifies them if they have a user account', function () {
    Notification::fake();

    $employee = notifTestEmployee('EMP-409', 'Test Position');
    $adminEmployee = notifTestEmployee('EMP-999', 'Admin', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $admin = User::factory()->create(['empcode' => $adminEmployee->EMPCODE, 'access' => 'admin']);

    $program = Program::create([
        'title' => 'Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Test',
        'type' => 'Test',
        'initiated' => 'Test',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Test',
    ]);
    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Open',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $this->actingAs($admin)->post(route('participants.store'), [
        'batch_id' => $batch->id,
        'empcodes' => [$employee->EMPCODE],
    ]);

    Notification::assertSentTo($user, ParticipantAdded::class);
});

test('notification index only returns notifications belonging to the authenticated user', function () {
    $employeeA = notifTestEmployee('EMP-410', 'Test Position A');
    $employeeB = notifTestEmployee('EMP-411', 'Test Position B');
    $userA = User::factory()->create(['empcode' => $employeeA->EMPCODE]);
    $userB = User::factory()->create(['empcode' => $employeeB->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $userA->id,
        'position' => 'Test Position A',
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $employeeB->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $userA->notify(new SelfRatingSubmitted($assessment));
    $userB->notify(new SelfRatingSubmitted($assessment));

    $response = $this->actingAs($userA)->getJson(route('notifications.index'));

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('marking a notification as read sets read_at', function () {
    $employee = notifTestEmployee('EMP-412', 'Test Position');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => 'Test Position',
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => 'EMP-999',
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $user->notify(new SelfRatingSubmitted($assessment));
    $notification = $user->notifications()->first();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)->post(route('notifications.read', $notification->id));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('marking all notifications as read clears all unread', function () {
    $employee = notifTestEmployee('EMP-413', 'Test Position');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => 'Test Position',
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => 'EMP-999',
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $user->notify(new SelfRatingSubmitted($assessment));
    $user->notify(new SelfRatingSubmitted($assessment));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)->post(route('notifications.read-all'));

    expect($user->unreadNotifications()->count())->toBe(0);
});
