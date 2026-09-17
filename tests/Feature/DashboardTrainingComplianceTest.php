<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Carbon\Carbon;

function trainingCompTestAdmin(string $empcode): User
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

function trainingCompTestEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
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

function trainingCompTestBatch(int $hours = 16, string $category = 'Regional', ?string $dateStart = null): Batch
{
    $program = Program::create([
        'title' => 'Training Compliance Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => $category,
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $dateStart ??= now()->subMonths(2)->toDateString();

    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => $dateStart,
        'date_end' => Carbon::parse($dateStart)->addMonth()->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => (string) $hours,
    ]);
}

test('training compliance endpoint reports correct global and per-region numbers (grouped-query rewrite)', function () {
    $admin = trainingCompTestAdmin('EMP-TC-ADM-01');
    $batch = trainingCompTestBatch(16);

    $ncrTrained = trainingCompTestEmployee('EMP-TC-NCR-TRAINED', 'Santos', 'NCR');
    $ncrNotTrained = trainingCompTestEmployee('EMP-TC-NCR-NOT', 'Reyes', 'NCR');
    $r5Trained = trainingCompTestEmployee('EMP-TC-R5-TRAINED', 'Cruz', 'R5');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $ncrTrained->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $r5Trained->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    // $ncrNotTrained employee: walang participant row -> hindi trained.

    $response = $this->actingAs($admin)->getJson(route('dashboard.training-compliance', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 1,
    ]));

    $response->assertOk();
    // +1 admin (CO) na hindi trained.
    expect($response->json('total'))->toBe(4);
    expect($response->json('trained'))->toBe(2);

    $regions = $response->json('regions');
    $trained = $response->json('regions_trained');
    $notTrained = $response->json('regions_not_trained');

    $ncrIndex = array_search('NCR', $regions);
    $r5Index = array_search('R5', $regions);
    $caragaIndex = array_search('CARAGA', $regions);

    expect($trained[$ncrIndex])->toBe(1);
    expect($notTrained[$ncrIndex])->toBe(1);
    expect($trained[$r5Index])->toBe(1);
    expect($notTrained[$r5Index])->toBe(0);
    // Rehiyon na walang data — dapat 0/0, hindi crash/undefined.
    expect($trained[$caragaIndex])->toBe(0);
    expect($notTrained[$caragaIndex])->toBe(0);
});

test('training compliance endpoint excludes General Assembly programs from the trained count', function () {
    $admin = trainingCompTestAdmin('EMP-TC-ADM-GA');

    $regularBatch = trainingCompTestBatch(16, 'Regional');
    $gaBatch = trainingCompTestBatch(16, 'General Assembly');

    $trainedViaRegular = trainingCompTestEmployee('EMP-TC-GA-01', 'Santos');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $regularBatch->id, 'empcode' => $trainedViaRegular->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $onlyAttendedGeneralAssembly = trainingCompTestEmployee('EMP-TC-GA-02', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $gaBatch->id, 'empcode' => $onlyAttendedGeneralAssembly->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.training-compliance', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 1,
    ]));

    $response->assertOk();
    // +1 admin (CO) na hindi trained. Sa 2 test employees, 1 lang ang
    // "trained" — ang isa ay General Assembly lang ang na-attend-an, hindi
    // ito binibilang na learning intervention.
    expect($response->json('total'))->toBe(3);
    expect($response->json('trained'))->toBe(1);
});

test('training compliance endpoint zeroes out other regions when a single region filter is applied', function () {
    $admin = trainingCompTestAdmin('EMP-TC-ADM-02');
    $batch = trainingCompTestBatch(16);

    $ncrEmployee = trainingCompTestEmployee('EMP-TC-NCR-ONLY', 'Santos', 'NCR');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $ncrEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $r5Employee = trainingCompTestEmployee('EMP-TC-R5-ONLY', 'Cruz', 'R5');
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $r5Employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.training-compliance', [
        'region' => 'NCR', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 1,
    ]));

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('trained'))->toBe(1);

    $regions = $response->json('regions');
    $trained = $response->json('regions_trained');
    $r5Index = array_search('R5', $regions);

    // May data ang R5, pero dapat 0 dahil naka-filter tayo sa NCR lang.
    expect($trained[$r5Index])->toBe(0);
});

test('training compliance endpoint scopes the trained count to the selected batch year', function () {
    $admin = trainingCompTestAdmin('EMP-TC-ADM-YR');

    $batch2025 = trainingCompTestBatch(16, 'Regional', '2025-03-10');
    $batch2026 = trainingCompTestBatch(16, 'Regional', '2026-03-10');

    $trainedIn2025 = trainingCompTestEmployee('EMP-TC-YR-2025', 'Santos');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch2025->id, 'empcode' => $trainedIn2025->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $trainedIn2026 = trainingCompTestEmployee('EMP-TC-YR-2026', 'Reyes');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch2026->id, 'empcode' => $trainedIn2026->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.training-compliance', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 1, 'year' => '2026',
    ]));

    $response->assertOk();
    // +1 admin (CO) + 2 test employees = 3 total (year filter hindi
    // apektado ang denominator, employee-level lang ang total). Trained =
    // 1 lang — yung employee na trained sa 2026 batch.
    expect($response->json('total'))->toBe(3);
    expect($response->json('trained'))->toBe(1);

    $allResponse = $this->actingAs($admin)->getJson(route('dashboard.training-compliance', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 1, 'year' => 'ALL',
    ]));
    $allResponse->assertOk();
    expect($allResponse->json('trained'))->toBe(2);
});

test('non-admin users cannot access the training compliance endpoint', function () {
    $employee = trainingCompTestEmployee('EMP-TC-USER', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.training-compliance'))->assertForbidden();
});
