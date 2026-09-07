<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;

function supCompTestAdmin(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Admin',
        'FIRSTNAME' => 'Ana',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '24',
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

function supCompTestEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '24',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function supCompTestBatch(int $hours): Batch
{
    $program = Program::create([
        'title' => 'Supervisory Compliance Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'SUPERVISORY/MANAGERIAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => now()->subMonths(2)->toDateString(),
        'date_end' => now()->subMonths(1)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '5',
        'hours' => (string) $hours,
    ]);
}

test('supervisory compliance endpoint reports correct global and per-region numbers (grouped-query rewrite)', function () {
    $admin = supCompTestAdmin('EMP-SC-ADM-01');
    $completedBatch = supCompTestBatch(40); // >= 40 hrs = "completed"
    $inProgressBatch = supCompTestBatch(20); // < 40, > 0 = "in progress"

    $ncrCompleted = supCompTestEmployee('EMP-SC-NCR-DONE', 'Santos', 'NCR');
    $ncrInProgress = supCompTestEmployee('EMP-SC-NCR-PROG', 'Reyes', 'NCR');
    $r5Completed = supCompTestEmployee('EMP-SC-R5-DONE', 'Cruz', 'R5');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $completedBatch->id, 'empcode' => $ncrCompleted->EMPCODE,
        'attendance' => 'Complete', 'hours' => 40, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $inProgressBatch->id, 'empcode' => $ncrInProgress->EMPCODE,
        'attendance' => 'Complete', 'hours' => 20, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 3, 'batch_id' => $completedBatch->id, 'empcode' => $r5Completed->EMPCODE,
        'attendance' => 'Complete', 'hours' => 40, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.supervisory-compliance', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'ALL', 'sg_min' => 19,
    ]));

    $response->assertOk();
    expect($response->json('completed'))->toBe(2);
    expect($response->json('in_progress'))->toBe(1);

    $regions = $response->json('regions');
    $completed = $response->json('regions_completed');
    $inProgress = $response->json('regions_in_progress');

    $ncrIndex = array_search('NCR', $regions);
    $r5Index = array_search('R5', $regions);
    $caragaIndex = array_search('CARAGA', $regions);

    expect($completed[$ncrIndex])->toBe(1);
    expect($inProgress[$ncrIndex])->toBe(1);
    expect($completed[$r5Index])->toBe(1);
    expect($inProgress[$r5Index])->toBe(0);
    // Rehiyon na walang data — dapat 0/0, hindi crash/undefined.
    expect($completed[$caragaIndex])->toBe(0);
    expect($inProgress[$caragaIndex])->toBe(0);
});

test('non-admin users cannot access the supervisory compliance endpoint', function () {
    $employee = supCompTestEmployee('EMP-SC-USER', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('dashboard.supervisory-compliance'))->assertForbidden();
});
