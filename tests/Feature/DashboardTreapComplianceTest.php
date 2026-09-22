<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;

function treapTestAdmin(string $empcode): User
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

function treapTestEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
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
function treapTestSetup(?string $dueDate = null, ?string $dateStart = null): array
{
    $program = Program::create([
        'title' => 'TREAP Compliance Test Program',
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
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => $dueDate ?? now()->subWeek()->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

test('treap compliance endpoint counts submitted, not-submitted, and excludes absentees', function () {
    $admin = treapTestAdmin('EMP-TREAP-ADM-01');
    [$program, $batch, $requirement] = treapTestSetup();

    $submittedEmployee = treapTestEmployee('EMP-TREAP-SUB-01', 'Santos');
    $missingEmployee = treapTestEmployee('EMP-TREAP-MISS-01', 'Reyes');
    $absentEmployee = treapTestEmployee('EMP-TREAP-ABS-01', 'Cruz');

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

    $response = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('treap compliance counts employees whose TREAP is not yet due (not overdue-only anymore)', function () {
    $admin = treapTestAdmin('EMP-TREAP-ADM-ND');
    // Due date sa hinaharap — dating hindi ito nabibilang, dapat kasama na ngayon.
    [$program, $batch, $requirement] = treapTestSetup(now()->addMonth()->toDateString());

    $notYetDueEmployee = treapTestEmployee('EMP-TREAP-ND-01', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $notYetDueEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);

    $listResponse = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'not_submitted', 'reg' => 'ALL',
    ]));

    $listResponse->assertOk();
    expect($listResponse->json('count'))->toBe(1);
    expect($listResponse->json('employees.0.empcode'))->toBe($notYetDueEmployee->EMPCODE);
});

test('treap compliance counts each batch obligation separately, not once per employee', function () {
    $admin = treapTestAdmin('EMP-TREAP-ADM-MULTI');
    // Dalawang HIWALAY na batch (ibang program) — pareho may TREAP requirement.
    [$programA, $batchA, $requirementA] = treapTestSetup(now()->subWeek()->toDateString(), now()->subMonths(2)->toDateString());
    [$programB, $batchB, $requirementB] = treapTestSetup(now()->subWeek()->toDateString(), now()->subMonths(2)->toDateString());

    $employee = treapTestEmployee('EMP-TREAP-MULTI-01', 'Bautista');

    // Enrolled sa DALAWANG batch — dalawang hiwalay na TREAP obligation.
    $participantA = Participant::create([
        'sort_order' => 1, 'batch_id' => $batchA->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchB->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    // Isa lang sa dalawa ang na-submit (Batch A).
    Submission::create([
        'participant_id' => $participantA->id,
        'program_code' => $programA->program_code,
        'batch_id' => $batchA->id,
        'requirement_id' => $requirementA->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    // Dalawang obligation, isa lang na-submit — dapat 2 total, 1 submitted,
    // 1 not submitted (hindi 1/1 na parang isa lang siyang tao).
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);

    $notSubmittedList = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'not_submitted', 'reg' => 'ALL',
    ]));
    $notSubmittedList->assertOk();
    expect($notSubmittedList->json('count'))->toBe(1);

    $submittedList = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'submitted', 'reg' => 'ALL',
    ]));
    $submittedList->assertOk();
    expect($submittedList->json('count'))->toBe(1);
});

test('treap compliance list endpoint returns the correct employees per type', function () {
    $admin = treapTestAdmin('EMP-TREAP-ADM-02');
    [$program, $batch, $requirement] = treapTestSetup();

    $submittedEmployee = treapTestEmployee('EMP-TREAP-SUB-02', 'Santos');
    $missingEmployee = treapTestEmployee('EMP-TREAP-MISS-02', 'Reyes');

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

    $notSubmittedResponse = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'not_submitted', 'reg' => 'ALL',
    ]));

    $notSubmittedResponse->assertOk();
    expect($notSubmittedResponse->json('count'))->toBe(1);
    expect($notSubmittedResponse->json('employees.0.empcode'))->toBe($missingEmployee->EMPCODE);

    $submittedResponse = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance.list', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
        'type' => 'submitted', 'reg' => 'ALL',
    ]));

    $submittedResponse->assertOk();
    expect($submittedResponse->json('count'))->toBe(1);
    expect($submittedResponse->json('employees.0.empcode'))->toBe($submittedEmployee->EMPCODE);
});

test('treap compliance endpoint scopes the total/submitted counts to the selected batch year', function () {
    $admin = treapTestAdmin('EMP-TREAP-ADM-YR');
    [$program2025, $batch2025, $requirement2025] = treapTestSetup(null, '2025-03-10');
    [$program2026, $batch2026, $requirement2026] = treapTestSetup(null, '2026-03-10');

    $employee2025 = treapTestEmployee('EMP-TREAP-YR-2025', 'Santos');
    $participant2025 = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch2025->id, 'empcode' => $employee2025->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Submission::create([
        'participant_id' => $participant2025->id,
        'program_code' => $program2025->program_code,
        'batch_id' => $batch2025->id,
        'requirement_id' => $requirement2025->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $employee2026 = treapTestEmployee('EMP-TREAP-YR-2026', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch2026->id, 'empcode' => $employee2026->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance', [
        'region' => 'ALL', 'year' => '2026', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('submitted'))->toBe(0);
    expect($response->json('not_submitted'))->toBe(1);

    $allResponse = $this->actingAs($admin)->getJson(route('dashboard.treap-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));
    $allResponse->assertOk();
    expect($allResponse->json('total'))->toBe(2);
    expect($allResponse->json('submitted'))->toBe(1);
});

test('non-admin users cannot access the treap compliance endpoints', function () {
    $employee = treapTestEmployee('EMP-TREAP-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.treap-compliance'))->assertForbidden();
    $this->actingAs($user)->getJson(route('dashboard.treap-compliance.list'))->assertForbidden();
});
