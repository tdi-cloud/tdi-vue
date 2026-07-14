<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function divisionTestEmployee(string $empcode, string $position, string $section, string $unit): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => $section,
        'UNIT' => $unit,
    ])->save();

    return $employee;
}

test('self-rating page defaults division to SECTION/UNIT when both are present', function () {
    $position = 'Test Officer Division 1';
    $employee = divisionTestEmployee('EMP-601', $position, 'Test Section', 'Test Unit');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('tna.self-rating'));

    $response->assertOk();
    expect($response->inertiaProps('employee')['division'])->toBe('Test Section/Test Unit');
});

test('self-rating page defaults division to SECTION only when UNIT is blank', function () {
    $position = 'Test Officer Division 2';
    $employee = divisionTestEmployee('EMP-602', $position, 'Test Section', '');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('tna.self-rating'));

    $response->assertOk();
    expect($response->inertiaProps('employee')['division'])->toBe('Test Section');
});

test('supervisory rating page defaults the supervisor division to SECTION/UNIT', function () {
    $position = 'Test Officer Division 3';
    $employee = divisionTestEmployee('EMP-603', $position, 'Sub Section', 'Sub Unit');
    $supervisorEmployee = divisionTestEmployee('EMP-604', 'Supervisor', 'Sup Section', 'Sup Unit');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($supervisorUser)->get(route('tna.supervisory.show', $assessment));

    $response->assertOk();
    expect($response->inertiaProps('supervisor')['division'])->toBe('Sup Section/Sup Unit');
});
