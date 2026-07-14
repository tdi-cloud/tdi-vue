<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\OverdueRequirementReminder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function overdueNotifTestEmployee(string $empcode, string $lastname): Employee
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

function overdueNotifTestSetup(): array
{
    $program = Program::create([
        'title' => 'Overdue Notif Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => now()->subDays(30)->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $overdueRequirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->subDays(5)->toDateString(),
        'is_required' => true,
    ]);

    $upcomingRequirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TDOR',
        'name' => Requirement::nameFor('TDOR'),
        'due_date' => now()->addDays(30)->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $overdueRequirement, $upcomingRequirement];
}

test('notifies an employee for an overdue, unsubmitted requirement', function () {
    Notification::fake();

    [$program, $batch, $overdueRequirement, $upcomingRequirement] = overdueNotifTestSetup();

    $employee = overdueNotifTestEmployee('EMP-OVR-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $this->artisan('requirements:notify-overdue')->assertExitCode(0);

    Notification::assertSentTo(
        $user,
        OverdueRequirementReminder::class,
        fn ($notification, $channels) => $notification->toArray($user)['requirement_id'] === $overdueRequirement->id
    );

    Notification::assertNotSentTo(
        $user,
        OverdueRequirementReminder::class,
        fn ($notification, $channels) => $notification->toArray($user)['requirement_id'] === $upcomingRequirement->id
    );
});

test('does not notify absent participants or employees without a user account', function () {
    Notification::fake();

    [$program, $batch, $overdueRequirement] = overdueNotifTestSetup();

    $absentEmployee = overdueNotifTestEmployee('EMP-OVR-02', 'Cruz');
    $absentUser = User::factory()->create(['empcode' => $absentEmployee->EMPCODE]);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $absentEmployee->EMPCODE,
        'attendance' => 'Absent', 'hours' => 0, 'added_by' => 'system',
    ]);

    $noAccountEmployee = overdueNotifTestEmployee('EMP-OVR-03', 'Santos');
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $noAccountEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $this->artisan('requirements:notify-overdue')->assertExitCode(0);

    Notification::assertNotSentTo($absentUser, OverdueRequirementReminder::class);
});

test('does not re-notify for a requirement already notified in a previous run', function () {
    Notification::fake();

    [$program, $batch, $overdueRequirement] = overdueNotifTestSetup();

    $employee = overdueNotifTestEmployee('EMP-OVR-04', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    // Simulate a notification already sent in a previous run by writing the
    // database notification row directly (Notification::fake() intercepts real sends).
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OverdueRequirementReminder::class,
        'data' => ['requirement_id' => $overdueRequirement->id],
    ]);

    $this->artisan('requirements:notify-overdue')->assertExitCode(0);

    Notification::assertNotSentTo($user, OverdueRequirementReminder::class);
});

test('does not notify when the requirement has already been submitted', function () {
    Notification::fake();

    [$program, $batch, $overdueRequirement] = overdueNotifTestSetup();

    $employee = overdueNotifTestEmployee('EMP-OVR-05', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $overdueRequirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $this->artisan('requirements:notify-overdue')->assertExitCode(0);

    Notification::assertNotSentTo($user, OverdueRequirementReminder::class);
});
