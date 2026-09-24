<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;

function globalSubIndexAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function globalSubIndexEmployee(string $empcode, string $lastname): Employee
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
function globalSubIndexSetup(string $programTitle): array
{
    $program = Program::create([
        'title' => $programTitle,
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

function globalSubIndexSubmission(Program $program, Batch $batch, Requirement $requirement, string $empcode, string $status): Submission
{
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    return Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => $status,
        'file_path' => 'submissions/test.pdf',
        'submitted_at' => now(),
    ]);
}

test('admin can view submissions across multiple programs on the global submissions page', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-01');

    [$programA, $batchA, $reqA] = globalSubIndexSetup('Global Submissions Test Program A');
    [$programB, $batchB, $reqB] = globalSubIndexSetup('Global Submissions Test Program B');

    $empA = globalSubIndexEmployee('EMP-GSI-01', 'Santos');
    $empB = globalSubIndexEmployee('EMP-GSI-02', 'Reyes');

    globalSubIndexSubmission($programA, $batchA, $reqA, $empA->EMPCODE, 'Pending');
    globalSubIndexSubmission($programB, $batchB, $reqB, $empB->EMPCODE, 'Approved');

    $response = $this->actingAs($admin)->get(route('submissions.index'));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(2);
    expect($data->pluck('program.program_code')->sort()->values()->all())
        ->toBe(collect([$programA->program_code, $programB->program_code])->sort()->values()->all());

    expect($response->inertiaProps('stats.total'))->toBe(2);
    expect($response->inertiaProps('stats.pending'))->toBe(1);
    expect($response->inertiaProps('stats.approved'))->toBe(1);
});

test('status filter narrows the global submissions list', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-02');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Status Filter Program');

    $empPending = globalSubIndexEmployee('EMP-GSI-03', 'Cruz');
    $empApproved = globalSubIndexEmployee('EMP-GSI-04', 'Bautista');

    globalSubIndexSubmission($program, $batch, $requirement, $empPending->EMPCODE, 'Pending');
    globalSubIndexSubmission($program, $batch, $requirement, $empApproved->EMPCODE, 'Approved');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['status' => 'Pending']));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['status'])->toBe('Pending');
});

test('program filter narrows the global submissions list to one program', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-03');
    [$programA, $batchA, $reqA] = globalSubIndexSetup('Global Submissions Program Filter A');
    [$programB, $batchB, $reqB] = globalSubIndexSetup('Global Submissions Program Filter B');

    $empA = globalSubIndexEmployee('EMP-GSI-05', 'Garcia');
    $empB = globalSubIndexEmployee('EMP-GSI-06', 'Villanueva');

    globalSubIndexSubmission($programA, $batchA, $reqA, $empA->EMPCODE, 'Pending');
    globalSubIndexSubmission($programB, $batchB, $reqB, $empB->EMPCODE, 'Pending');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['program_code' => $programA->program_code]));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['program_code'])->toBe($programA->program_code);
});

test('search filter matches by employee name, empcode, or requirement title', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-04');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Search Program');

    $target = globalSubIndexEmployee('EMP-GSI-UNIQUE-07', 'Fernandez');
    $other = globalSubIndexEmployee('EMP-GSI-08', 'Torres');

    globalSubIndexSubmission($program, $batch, $requirement, $target->EMPCODE, 'Pending');
    globalSubIndexSubmission($program, $batch, $requirement, $other->EMPCODE, 'Pending');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['search' => 'Fernandez']));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['participant']['empcode'])->toBe($target->EMPCODE);
});

test('each submission exposes the participant attendance and justification for inline editing', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-05');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Attendance Program');

    $employee = globalSubIndexEmployee('EMP-GSI-09', 'Aquino');
    $submission = globalSubIndexSubmission($program, $batch, $requirement, $employee->EMPCODE, 'Pending');
    $submission->participant->update(['attendance' => 'Complete', 'hours' => 16]);

    $response = $this->actingAs($admin)->get(route('submissions.index'));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    $row = $data->first();
    expect($row['participant']['attendance'])->toBe('Complete');
    expect($row['participant']['hours'])->toEqual(16);
    expect($row['participant'])->toHaveKey('justification');
});

test('non-admin users cannot access the global submissions page', function () {
    $employee = globalSubIndexEmployee('EMP-GSI-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('submissions.index'))->assertForbidden();
});
