<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;

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

function tdorTestSetup(?string $dateStart = null): array
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

    $dateStart ??= now()->subMonths(8)->toDateString();

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => $dateStart,
        'date_end' => Carbon::parse($dateStart)->addMonth()->toDateString(),
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

test('tdor compliance endpoint reports correct per-region breakdown (grouped-query rewrite)', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-06');
    [$program, $batch, $requirement] = tdorTestSetup();

    $ncrSubmitted = tdorTestEmployee('EMP-TDOR-NCR-SUB', 'Santos', 'NCR');
    $ncrMissing = tdorTestEmployee('EMP-TDOR-NCR-MISS', 'Reyes', 'NCR');
    $r5Missing = tdorTestEmployee('EMP-TDOR-R5-MISS', 'Cruz', 'R5');

    $ncrSubmittedParticipant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $ncrSubmitted->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $ncrMissing->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 3, 'batch_id' => $batch->id, 'empcode' => $r5Missing->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Submission::create([
        'participant_id' => $ncrSubmittedParticipant->id,
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
    $regions = $response->json('regions');
    $submitted = $response->json('regions_submitted');
    $notSubmitted = $response->json('regions_not_submitted');

    $ncrIndex = array_search('NCR', $regions);
    $r5Index = array_search('R5', $regions);

    expect($submitted[$ncrIndex])->toBe(1);
    expect($notSubmitted[$ncrIndex])->toBe(1);
    expect($submitted[$r5Index])->toBe(0);
    expect($notSubmitted[$r5Index])->toBe(1);

    // Rehiyon na walang data — dapat 0/0, hindi crash/undefined.
    $caragaIndex = array_search('CARAGA', $regions);
    expect($submitted[$caragaIndex])->toBe(0);
    expect($notSubmitted[$caragaIndex])->toBe(0);
});

test('tdor compliance endpoint zeroes out other regions when a single region filter is applied', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-07');
    [$program, $batch, $requirement] = tdorTestSetup();

    tdorTestEmployee('EMP-TDOR-NCR-ONLY', 'Santos', 'NCR');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => 'EMP-TDOR-NCR-ONLY',
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $r5Employee = tdorTestEmployee('EMP-TDOR-R5-ONLY', 'Cruz', 'R5');
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $r5Employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'NCR', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1); // NCR lang dapat mabilang sa global total

    $regions = $response->json('regions');
    $notSubmitted = $response->json('regions_not_submitted');
    $r5Index = array_search('R5', $regions);

    // Kahit may data ang R5, dapat 0 ito dahil naka-filter tayo sa NCR lang.
    expect($notSubmitted[$r5Index])->toBe(0);
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

test('tdor compliance endpoint scopes the total/submitted counts to the selected batch year', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-YR');
    [$program2025, $batch2025, $requirement2025] = tdorTestSetup('2025-03-10');
    [$program2026, $batch2026, $requirement2026] = tdorTestSetup('2026-03-10');

    $employee2025 = tdorTestEmployee('EMP-TDOR-YR-2025', 'Santos');
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

    $employee2026 = tdorTestEmployee('EMP-TDOR-YR-2026', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch2026->id, 'empcode' => $employee2026->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    // Walang Submission ang 2026 employee — dapat mabilang bilang "not submitted".

    $response = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'ALL', 'year' => '2026', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    // 1 lang ang total sa 2026 filter — ang 2025 batch/employee ay
    // hindi dapat kasama (buong cohort ng TREAP/REAP/TDOR ay batay sa
    // batch, kaya ang year filter ay umaapekto sa denominator dito).
    expect($response->json('total'))->toBe(1);
    expect($response->json('submitted'))->toBe(0);
    expect($response->json('not_submitted'))->toBe(1);

    $allResponse = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));
    $allResponse->assertOk();
    expect($allResponse->json('total'))->toBe(2);
    expect($allResponse->json('submitted'))->toBe(1);
});

test('tdor compliance still counts a submission when the participant empcode differs only in case or whitespace', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-WS');
    [$program, $batch, $requirement] = tdorTestSetup();

    $employee = tdorTestEmployee('EMP-TDOR-WS-01', 'Santos');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    // Submission's own participant row was encoded with a messier empcode —
    // lowercase and a stray trailing space — for the same real employee.
    $messyParticipant = Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => ' '.strtolower($employee->EMPCODE).' ',
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Submission::create([
        'participant_id' => $messyParticipant->id,
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
    expect($response->json('total'))->toBe(1);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(0);
});

test('tdor compliance counts employees whose TDOR is not yet due (not overdue-only anymore)', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-ND');
    [$program, $batch, $requirement] = tdorTestSetup();
    $requirement->update(['due_date' => now()->addMonth()->toDateString()]);

    $notYetDueEmployee = tdorTestEmployee('EMP-TDOR-ND-01', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $notYetDueEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('tdor compliance counts each batch obligation separately, not once per employee', function () {
    $admin = tdorTestAdmin('EMP-TDOR-ADM-MULTI');
    [$programA, $batchA, $requirementA] = tdorTestSetup(now()->subMonths(9)->toDateString());
    [$programB, $batchB, $requirementB] = tdorTestSetup(now()->subMonths(9)->toDateString());

    $employee = tdorTestEmployee('EMP-TDOR-MULTI-01', 'Bautista');

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

    $response = $this->actingAs($admin)->getJson(route('dashboard.tdor-compliance', [
        'region' => 'ALL', 'year' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL',
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('submitted'))->toBe(1);
    expect($response->json('not_submitted'))->toBe(1);
});

test('non-admin users cannot access the tdor compliance endpoints', function () {
    $employee = tdorTestEmployee('EMP-TDOR-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.tdor-compliance'))->assertForbidden();
    $this->actingAs($user)->getJson(route('dashboard.tdor-compliance.list'))->assertForbidden();
});
