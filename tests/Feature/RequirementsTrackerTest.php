<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;

function trackerTestAdmin(string $empcode): User
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

function trackerTestEmployee(string $empcode, string $lastname): Employee
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

function trackerTestSetup(): array
{
    $program = Program::create([
        'title' => 'Tracker Test Program',
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

test('lists employees with missing requirement submissions, excluding absentees and those who already submitted', function () {
    $admin = trackerTestAdmin('EMP-ADM-01');
    [$program, $batch, $overdueRequirement, $upcomingRequirement] = trackerTestSetup();

    $missingEmployee = trackerTestEmployee('EMP-MISS-01', 'Reyes');
    $submittedEmployee = trackerTestEmployee('EMP-SUB-01', 'Santos');
    $absentEmployee = trackerTestEmployee('EMP-ABS-01', 'Cruz');

    $missingParticipant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $missingEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $submittedParticipant = Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $submittedEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Participant::create([
        'sort_order' => 3, 'batch_id' => $batch->id, 'empcode' => $absentEmployee->EMPCODE,
        'attendance' => 'Absent', 'hours' => 0, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $submittedParticipant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $overdueRequirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('requirements-tracker.index'));
    $response->assertOk();

    $items = collect($response->inertiaProps('items')['data']);

    // Missing employee still owes both requirements (TREAP overdue, TDOR upcoming).
    expect($items->where('empcode', $missingEmployee->EMPCODE)->pluck('requirement_title')->sort()->values()->all())
        ->toBe(['TDOR', 'TREAP']);

    // Submitted employee already has TREAP covered, should not appear for it.
    expect($items->where('empcode', $submittedEmployee->EMPCODE)->where('requirement_title', 'TREAP'))->toHaveCount(0);

    // Absent participant is excluded entirely.
    expect($items->where('empcode', $absentEmployee->EMPCODE))->toHaveCount(0);

    $overdueRow = $items->firstWhere('requirement_title', 'TREAP');
    expect($overdueRow['is_overdue'])->toBeTrue();
    expect($overdueRow['program_id'])->toBe($program->id);

    $upcomingRow = $items->firstWhere('requirement_title', 'TDOR');
    expect($upcomingRow['is_overdue'])->toBeFalse();
});

test('overdue_only filter only returns requirements past their due date', function () {
    $admin = trackerTestAdmin('EMP-ADM-02');
    [$program, $batch, $overdueRequirement, $upcomingRequirement] = trackerTestSetup();

    $employee = trackerTestEmployee('EMP-MISS-02', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('requirements-tracker.index', ['overdue_only' => '1']));
    $response->assertOk();

    $items = collect($response->inertiaProps('items')['data']);
    expect($items)->toHaveCount(1);
    expect($items->first()['requirement_title'])->toBe('TREAP');
});

test('requirement_title filter narrows results to the selected requirement', function () {
    $admin = trackerTestAdmin('EMP-ADM-03');
    [$program, $batch, $overdueRequirement, $upcomingRequirement] = trackerTestSetup();

    $employee = trackerTestEmployee('EMP-MISS-03', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('requirements-tracker.index', ['requirement_title' => 'TDOR']));
    $response->assertOk();

    $items = collect($response->inertiaProps('items')['data']);
    expect($items)->toHaveCount(1);
    expect($items->first()['requirement_title'])->toBe('TDOR');
});

test('csv export streams a csv file with the outstanding requirements', function () {
    $admin = trackerTestAdmin('EMP-ADM-04');
    [$program, $batch, $overdueRequirement, $upcomingRequirement] = trackerTestSetup();

    $employee = trackerTestEmployee('EMP-MISS-04', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('requirements-tracker.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('EMP-MISS-04');
    expect($content)->toContain('TREAP');
    expect($content)->toContain('TDOR');
});

test('non-admin users cannot access the requirements tracker', function () {
    $employee = trackerTestEmployee('EMP-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('requirements-tracker.index'))->assertForbidden();
});
