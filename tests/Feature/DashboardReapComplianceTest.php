<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;

function reapTestAdmin(string $empcode): User
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

function reapTestEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
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

/**
 * @return array{0: Program, 1: Batch, 2: Requirement}
 */
function reapTestSetup(?string $dueDate = null, ?string $dateStart = null): array
{
    $program = Program::create([
        'title' => 'REAP Compliance Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $dateStart ??= now()->subMonth()->toDateString();

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => $dateStart,
        'date_end' => Carbon::parse($dateStart)->addDays(2)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'REAP',
        'name' => Requirement::nameFor('REAP'),
        'due_date' => $dueDate ?? now()->subWeek()->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

test('reap compliance endpoint counts submitted, not-submitted, and excludes absentees', function () {
    $admin = reapTestAdmin('EMP-REAP-ADM-01');
    [$program, $batch, $requirement] = reapTestSetup();

    $submittedEmployee = reapTestEmployee('EMP-REAP-SUB-01', 'Santos');
    $missingEmployee = reapTestEmployee('EMP-REAP-MISS-01', 'Reyes');
    $absentEmployee = reapTestEmployee('EMP-REAP-ABS-01', 'Cruz');

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

    $response = $this->actingAs($admin)->getJson(route('dashboard.reap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('reap compliance counts employees whose REAP is not yet due (not overdue-only anymore)', function () {
    $admin = reapTestAdmin('EMP-REAP-ADM-ND');
    [$program, $batch, $requirement] = reapTestSetup(now()->addMonth()->toDateString());

    $notYetDueEmployee = reapTestEmployee('EMP-REAP-ND-01', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $notYetDueEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.reap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('reap compliance counts each batch obligation separately, not once per employee', function () {
    $admin = reapTestAdmin('EMP-REAP-ADM-MULTI');
    [$programA, $batchA, $requirementA] = reapTestSetup(now()->subWeek()->toDateString(), now()->subMonths(2)->toDateString());
    [$programB, $batchB, $requirementB] = reapTestSetup(now()->subWeek()->toDateString(), now()->subMonths(2)->toDateString());

    $employee = reapTestEmployee('EMP-REAP-MULTI-01', 'Bautista');

    $participantA = Participant::create([
        'sort_order' => 1, 'batch_id' => $batchA->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchB->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $participantA->id,
        'program_code' => $programA->program_code,
        'batch_id' => $batchA->id,
        'requirement_id' => $requirementA->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.reap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('reap compliance list endpoint returns the correct employees per type', function () {
    $admin = reapTestAdmin('EMP-REAP-ADM-02');
    [$program, $batch, $requirement] = reapTestSetup();

    $submittedEmployee = reapTestEmployee('EMP-REAP-SUB-02', 'Santos');
    $missingEmployee = reapTestEmployee('EMP-REAP-MISS-02', 'Reyes');

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

    $notSubmittedResponse = $this->actingAs($admin)->getJson(route('dashboard.reap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'not_submitted', 'reg' => 'ALL',
    ]));

    $notSubmittedResponse->assertOk();
    expect($notSubmittedResponse->json('count'))->toBe(1);
    expect($notSubmittedResponse->json('employees.0.empcode'))->toBe($missingEmployee->EMPCODE);

    $submittedResponse = $this->actingAs($admin)->getJson(route('dashboard.reap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'submitted', 'reg' => 'ALL',
    ]));

    $submittedResponse->assertOk();
    expect($submittedResponse->json('count'))->toBe(1);
    expect($submittedResponse->json('employees.0.empcode'))->toBe($submittedEmployee->EMPCODE);
});

test('non-admin users cannot access the reap compliance endpoints', function () {
    $employee = reapTestEmployee('EMP-REAP-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.reap-compliance'))->assertForbidden();
    $this->actingAs($user)->getJson(route('dashboard.reap-compliance.list'))->assertForbidden();
});
