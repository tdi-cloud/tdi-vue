<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\User;

function workforceTestAdmin(string $empcode): User
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

function workforceTestEmployee(string $empcode, string $region, string $lastname = 'Reyes'): Employee
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

function workforceTestBatch(string $status = 'Closed', int $hours = 16): array
{
    $program = Program::create([
        'title' => 'Workforce Test Program',
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
        'status' => $status,
        'modality' => 'Onsite',
        'date_start' => now()->subMonths(8)->toDateString(),
        'date_end' => now()->subMonths(7)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => (string) $hours,
    ]);

    return [$program, $batch];
}

test('employee map index reports real workforce kpis, not fabricated ones', function () {
    $admin = workforceTestAdmin('EMP-WF-ADM1');

    [$program, $batch] = workforceTestBatch(status: 'Closed', hours: 16);

    $completedEmployee = workforceTestEmployee('EMP-WF-01', 'NCR', 'Santos');
    $absentEmployee = workforceTestEmployee('EMP-WF-02', 'NCR', 'Cruz');
    $untouchedEmployee = workforceTestEmployee('EMP-WF-03', 'R5', 'Reyes');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $completedEmployee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $absentEmployee->EMPCODE,
        'attendance' => 'Absent', 'hours' => 0, 'added_by' => 'system',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TDOR',
        'name' => Requirement::nameFor('TDOR'),
        'due_date' => now()->subMonth()->toDateString(),
        'is_required' => true,
    ]);

    // completedEmployee's participant never submits -> counted as missing/overdue.

    $response = $this->actingAs($admin)->get(route('employees-map.index'));
    $response->assertOk();

    // +1 admin (CO) + 3 test employees = 4 total.
    expect($response->inertiaProps('kpi.total_personnel'))->toBe(4);
    expect($response->inertiaProps('kpi.training_completed'))->toBe(1);
    expect($response->inertiaProps('kpi.requirements_pending'))->toBe(1);
    expect($response->inertiaProps('kpi.needs_attention'))->toBe(1);

    $regionMetrics = collect($response->inertiaProps('regionMetrics'))->keyBy('region');
    expect($regionMetrics['NCR']['total'])->toBe(2);
    expect($regionMetrics['NCR']['completed'])->toBe(1);
    expect((float) $regionMetrics['NCR']['completion_pct'])->toBe(50.0);
    expect($regionMetrics['R5']['total'])->toBe(1);
    expect($regionMetrics['R5']['completed'])->toBe(0);

    // Untouched employee/region never fabricate a non-zero metric.
    expect($regionMetrics['R5']['pending'])->toBe(0);
});

test('employee map region endpoint returns a real regional training overview', function () {
    $admin = workforceTestAdmin('EMP-WF-ADM2');
    [$program, $batch] = workforceTestBatch(status: 'Closed', hours: 16);

    $trained = workforceTestEmployee('EMP-WF-04', 'R5', 'Santos');
    $notTrained = workforceTestEmployee('EMP-WF-05', 'R5', 'Reyes');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $trained->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->getJson(route('employees-map.region', ['region' => 'R5']));
    $response->assertOk();

    expect($response->json('overview.total'))->toBe(2);
    expect($response->json('overview.completed'))->toBe(1);
    expect((float) $response->json('overview.completion_pct'))->toBe(50.0);
});

test('employee map does not fabricate training completion for employees with only short sub-8-hour attendance', function () {
    $admin = workforceTestAdmin('EMP-WF-ADM3');
    [$program, $batch] = workforceTestBatch(status: 'Closed', hours: 4);

    $shortAttendee = workforceTestEmployee('EMP-WF-06', 'R3', 'Delacruz');

    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $shortAttendee->EMPCODE,
        'attendance' => 'Complete', 'hours' => 4, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->get(route('employees-map.index'));
    $response->assertOk();

    $regionMetrics = collect($response->inertiaProps('regionMetrics'))->keyBy('region');
    // May participation (dumalo) pero hindi pa "completed" — <8 hours ang batch.
    expect($regionMetrics['R3']['participation'])->toBe(1);
    expect($regionMetrics['R3']['completed'])->toBe(0);
});

test('non-admin users cannot access the employee map workforce data', function () {
    $employee = workforceTestEmployee('EMP-WF-07', 'NCR');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('employees-map.index'))->assertForbidden();
});
