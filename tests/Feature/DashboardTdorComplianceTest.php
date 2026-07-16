<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;

function tdorTestAdmin(string $empcode): User
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

function tdorTestEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
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
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function tdorTestSetup(): array
{
    $program = Program::create([
        'title' => 'TDOR Compliance Test Program',
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
        'date_start' => now()->subMonths(8)->toDateString(),
        'date_end' => now()->subMonths(7)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    // Due date sa nakaraan na (date_end + 6 months) para masama sa "required" count.
    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TDOR',
        'name' => Requirement::nameFor('TDOR'),
        'due_date' => now()->subMonth()->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

test('tdor compliance endpoint counts submitted, not-submitted, and excludes absentees', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-01');
    [$program, $batch, $requirement] = tdorTestSetup();

    $submittedEmployee = tdorTestEmployee('EMP-TDOR-SUB-01', 'Santos');
    $missingEmployee = tdorTestEmployee('EMP-TDOR-MISS-01', 'Reyes');
    $absentEmployee = tdorTestEmployee('EMP-TDOR-ABS-01', 'Cruz');

    $submittedParticipant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $submittedEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $missingEmployee->EMPCODE,
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
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('tdor compliance list endpoint returns the correct employees per type', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-02');
    [$program, $batch, $requirement] = tdorTestSetup();

    $submittedEmployee = tdorTestEmployee('EMP-TDOR-SUB-02', 'Santos');
    $missingEmployee = tdorTestEmployee('EMP-TDOR-MISS-02', 'Reyes');

    $submittedParticipant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $submittedEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $missingEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $submittedParticipant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $notSubmittedResponse = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'not_submitted', 'reg' => 'ALL',
    ]));

    $notSubmittedResponse->assertOk();
    expect($notSubmittedResponse->json('count'))->toBe(1);
    expect($notSubmittedResponse->json('employees.0.empcode'))->toBe($missingEmployee->EMPCODE);

    $submittedResponse = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'submitted', 'reg' => 'ALL',
    ]));

    $submittedResponse->assertOk();
    expect($submittedResponse->json('count'))->toBe(1);
    expect($submittedResponse->json('employees.0.empcode'))->toBe($submittedEmployee->EMPCODE);
});

test('non-admin users cannot access the tdor compliance endpoints', function () {
    $employee = tdorTestEmployee('EMP-TDOR-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.tdor-compliance'))->assertForbidden();
    $this->actingAs($user)->getJson(route('dashboard.tdor-compliance.list'))->assertForbidden();
});
