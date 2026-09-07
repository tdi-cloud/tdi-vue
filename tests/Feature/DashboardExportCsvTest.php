<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;

function exportCsvTestAdmin(string $empcode): User
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
        'OFFICE' => 'Central Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function exportCsvTestEmployee(string $empcode, string $region, string $lastname = 'Reyes'): Employee
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

function exportCsvTestBatch(): array
{
    $program = Program::create([
        'title' => 'Export CSV Test Program',
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
        'date_start' => '2026-01-10',
        'date_end' => '2026-01-12',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    return [$program, $batch];
}

test('dashboard export csv streams programs, batches, participants and attendance', function () {
    $admin = exportCsvTestAdmin('EMP-EXP-ADM1');
    [$program, $batch] = exportCsvTestBatch();

    $employee = exportCsvTestEmployee('EMP-EXP-01', 'NCR', 'Santos');
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard.export-csv', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'Nationwide',
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain($program->program_code)
        ->and($csv)->toContain('Export CSV Test Program')
        ->and($csv)->toContain('Batch 1')
        ->and($csv)->toContain('EMP-EXP-01')
        ->and($csv)->toContain('Santos')
        ->and($csv)->toContain('Complete');

    // Header row includes an "SG" column, and the employee's SG value is present.
    $headerLine = strtok($csv, "\n");
    expect($headerLine)->toContain('SG');
    expect($csv)->toContain(',10,'); // SG value ng exportCsvTestEmployee
});

test('dashboard export csv respects the region filter', function () {
    $admin = exportCsvTestAdmin('EMP-EXP-ADM2');
    [$program, $batch] = exportCsvTestBatch();

    $ncrEmployee = exportCsvTestEmployee('EMP-EXP-02', 'NCR', 'Cruz');
    $r5Employee = exportCsvTestEmployee('EMP-EXP-03', 'R5', 'Bautista');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $ncrEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $r5Employee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard.export-csv', [
        'region' => 'NCR', 'office' => 'ALL', 'office_filter' => 'Nationwide',
    ]));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('Cruz')
        ->and($csv)->not->toContain('Bautista');
});

test('dashboard export csv respects the date range filter', function () {
    $admin = exportCsvTestAdmin('EMP-EXP-ADM3');
    [$program, $batchJan] = exportCsvTestBatch();

    $batchAug = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 2 (August)',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => '2026-08-10',
        'date_end' => '2026-08-12',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $janEmployee = exportCsvTestEmployee('EMP-EXP-05', 'NCR', 'January');
    $augEmployee = exportCsvTestEmployee('EMP-EXP-06', 'NCR', 'August');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchJan->id, 'empcode' => $janEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batchAug->id, 'empcode' => $augEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard.export-csv', [
        'region' => 'ALL', 'office' => 'ALL', 'office_filter' => 'Nationwide',
        'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
    ]));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('August')
        ->and($csv)->not->toContain('January');
});

test('non-admin users cannot access the dashboard csv export', function () {
    $employee = exportCsvTestEmployee('EMP-EXP-04', 'NCR');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('dashboard.export-csv'))->assertForbidden();
});
