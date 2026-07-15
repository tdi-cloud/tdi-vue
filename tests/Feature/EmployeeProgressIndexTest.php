<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function progressIndexTestAdmin(string $empcode): User
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

test('employees index exposes program and hours progress stats per employee', function () {
    $admin = progressIndexTestAdmin('EMP-PIDX-ADM');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-PIDX-01',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Reyes',
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

    $program = Program::create([
        'title' => 'Progress Index Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batchCompleted = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $batchOngoing = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 2',
        'status' => 'Open',
        'modality' => 'Onsite',
        'date_start' => '2026-02-01',
        'date_end' => '2026-02-03',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '3',
        'hours' => '24',
    ]);

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchCompleted->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchOngoing->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Pending', 'hours' => 0, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PIDX-01']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('employees')['data']);
    $row = $rows->firstWhere('EMPCODE', 'EMP-PIDX-01');

    expect($row)->not->toBeNull();
    expect($row['progress_stats'])->toBe([
        'total_programs' => 2,
        'completed_programs' => 1,
        'total_hours' => 40,
        'hours_completed' => 16,
    ]);
});

test('employees index reports zeroed progress stats for employees with no participation', function () {
    $admin = progressIndexTestAdmin('EMP-PIDX-ADM2');

    Employee::forceCreate([
        'EMPCODE' => 'EMP-PIDX-02',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Santos',
        'FIRSTNAME' => 'Maria',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PIDX-02']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('employees')['data']);
    $row = $rows->firstWhere('EMPCODE', 'EMP-PIDX-02');

    expect($row['progress_stats'])->toBe([
        'total_programs' => 0,
        'completed_programs' => 0,
        'total_hours' => 0,
        'hours_completed' => 0,
    ]);
});

test('employees index exposes the linked user account avatar url', function () {
    Storage::fake('public');

    $admin = progressIndexTestAdmin('EMP-PIDX-ADM3');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-PIDX-03',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Cruz',
        'FIRSTNAME' => 'Pedro',
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

    User::factory()->create(['empcode' => $employee->EMPCODE, 'avatar' => 'avatars/pedro.jpg']);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PIDX-03']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('employees')['data']);
    $row = $rows->firstWhere('EMPCODE', 'EMP-PIDX-03');

    expect($row['avatar'])->toContain('/storage/avatars/pedro.jpg');
});

test('employees index reports a null avatar when the employee has no user account', function () {
    $admin = progressIndexTestAdmin('EMP-PIDX-ADM4');

    Employee::forceCreate([
        'EMPCODE' => 'EMP-PIDX-04',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Bautista',
        'FIRSTNAME' => 'Liza',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PIDX-04']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('employees')['data']);
    $row = $rows->firstWhere('EMPCODE', 'EMP-PIDX-04');

    expect($row['avatar'])->toBeNull();
});

test('employees index exposes submission progress stats per employee', function () {
    $admin = progressIndexTestAdmin('EMP-PIDX-ADM5');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-PIDX-05',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Garcia',
        'FIRSTNAME' => 'Nora',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    $program = Program::create([
        'title' => 'Submission Progress Test Program',
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
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $reqApproved = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addDays(5)->toDateString(),
        'is_required' => true,
    ]);

    $reqMissing = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TDOR',
        'name' => Requirement::nameFor('TDOR'),
        'due_date' => now()->addDays(30)->toDateString(),
        'is_required' => true,
    ]);

    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $reqApproved->id,
        'status' => 'Approved',
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PIDX-05']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('employees')['data']);
    $row = $rows->firstWhere('EMPCODE', 'EMP-PIDX-05');

    expect($row['submission_stats'])->toBe([
        'total_requirements' => 2,
        'approved_submissions' => 1,
    ]);
});
