<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;

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
