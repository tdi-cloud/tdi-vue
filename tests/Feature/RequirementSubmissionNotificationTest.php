<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\PendingNotification;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\RequirementSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function reqSubNotifEmployee(string $empcode, string $lastname): Employee
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

/**
 * @return array{0: Program, 1: Batch, 2: Requirement}
 */
function reqSubNotifSetup(string $addedByEmpcode): array
{
    $program = Program::create([
        'title' => 'Notification Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => $addedByEmpcode,
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

test('submitting a requirement notifies the program creator who already has a user account', function () {
    Notification::fake();
    Storage::fake('public');

    $creator = User::factory()->create(['empcode' => 'EMP-RSN-CREATOR-01', 'access' => 'admin']);
    [$program, $batch, $requirement] = reqSubNotifSetup($creator->empcode);

    $employee = reqSubNotifEmployee('EMP-RSN-EMP-01', 'Santos');
    $employeeUser = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $this->actingAs($employeeUser)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'file' => UploadedFile::fake()->create('treap.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionDoesntHaveErrors();

    Notification::assertSentTo($creator, RequirementSubmitted::class);
});

test('resubmitting a new file after rejection notifies the program creator again', function () {
    Notification::fake();
    Storage::fake('public');

    $creator = User::factory()->create(['empcode' => 'EMP-RSN-CREATOR-02', 'access' => 'admin']);
    [$program, $batch, $requirement] = reqSubNotifSetup($creator->empcode);

    $employee = reqSubNotifEmployee('EMP-RSN-EMP-02', 'Reyes');
    $employeeUser = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Rejected',
        'remarks' => 'Wrong file.',
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($employeeUser)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'file' => UploadedFile::fake()->create('treap-v2.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionDoesntHaveErrors();

    Notification::assertSentToTimes($creator, RequirementSubmitted::class, 1);
});

test('a notes-only update with no new file does not notify the program creator', function () {
    Notification::fake();
    Storage::fake('public');

    $creator = User::factory()->create(['empcode' => 'EMP-RSN-CREATOR-03', 'access' => 'admin']);
    [$program, $batch, $requirement] = reqSubNotifSetup($creator->empcode);

    $employee = reqSubNotifEmployee('EMP-RSN-EMP-03', 'Cruz');
    $employeeUser = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'file_path' => 'submissions/existing.pdf',
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($employeeUser)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'notes' => 'Just adding a note.',
        ])
        ->assertSessionDoesntHaveErrors();

    Notification::assertNotSentTo($creator, RequirementSubmitted::class);
});

test('the program creator is not notified when submitting their own requirement', function () {
    Notification::fake();
    Storage::fake('public');

    $creatorEmployee = reqSubNotifEmployee('EMP-RSN-SELF-01', 'Bautista');
    $creator = User::factory()->create(['empcode' => $creatorEmployee->EMPCODE, 'access' => 'admin']);
    [$program, $batch, $requirement] = reqSubNotifSetup($creator->empcode);

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $creator->empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $this->actingAs($creator)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'file' => UploadedFile::fake()->create('treap.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionDoesntHaveErrors();

    Notification::assertNothingSent();
});

test('submitting a requirement queues a pending notification when the program creator has no user account yet', function () {
    Storage::fake('public');

    [$program, $batch, $requirement] = reqSubNotifSetup('EMP-RSN-CREATOR-NOACCT');

    $employee = reqSubNotifEmployee('EMP-RSN-EMP-04', 'Garcia');
    $employeeUser = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $this->actingAs($employeeUser)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'file' => UploadedFile::fake()->create('treap.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionDoesntHaveErrors();

    $pending = PendingNotification::where('empcode', 'EMP-RSN-CREATOR-NOACCT')->first();
    expect($pending)->not->toBeNull();
    expect($pending->type)->toBe(RequirementSubmitted::class);
    expect($pending->data['title'])->toBe('New Requirement Submission');
});
