<?php

use App\Models\Employee;
use App\Models\User;

function employeeMapTestAdmin(string $empcode): User
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

function employeeMapTestEmployee(string $empcode, string $region, string $lastname = 'Reyes', string $office = 'Test Office'): Employee
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
        'OFFICE' => $office,
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

test('employees map index exposes region counts and total employees', function () {
    $admin = employeeMapTestAdmin('EMP-MAP-ADM1');

    employeeMapTestEmployee('EMP-MAP-01', 'NCR');
    employeeMapTestEmployee('EMP-MAP-02', 'NCR');
    employeeMapTestEmployee('EMP-MAP-03', 'R5');

    $response = $this->actingAs($admin)->get(route('employees-map.index'));
    $response->assertOk();

    $regionCounts = collect($response->inertiaProps('regionCounts'))->keyBy('region');

    // +1 dahil ang admin mismo ay isang CO employee.
    expect($regionCounts['NCR']['total'])->toBe(2);
    expect($regionCounts['R5']['total'])->toBe(1);
    expect($response->inertiaProps('totalEmployees'))->toBe(4);
});

test('employees map region endpoint returns only employees from the requested region', function () {
    $admin = employeeMapTestAdmin('EMP-MAP-ADM2');

    employeeMapTestEmployee('EMP-MAP-04', 'R5', 'Santos');
    employeeMapTestEmployee('EMP-MAP-05', 'NCR', 'Cruz');

    $response = $this->actingAs($admin)->getJson(route('employees-map.region', ['region' => 'R5']));
    $response->assertOk();

    $empcodes = collect($response->json('employees.data'))->pluck('EMPCODE');
    expect($empcodes)->toContain('EMP-MAP-04');
    expect($empcodes)->not->toContain('EMP-MAP-05');
});

test('employees map region endpoint supports searching within the region', function () {
    $admin = employeeMapTestAdmin('EMP-MAP-ADM3');

    employeeMapTestEmployee('EMP-MAP-06', 'R5', 'Bautista');
    employeeMapTestEmployee('EMP-MAP-07', 'R5', 'Villanueva');

    $response = $this->actingAs($admin)->getJson(route('employees-map.region', [
        'region' => 'R5',
        'search' => 'Bautista',
    ]));
    $response->assertOk();

    $lastnames = collect($response->json('employees.data'))->pluck('LASTNAME');
    expect($lastnames)->toContain('Bautista');
    expect($lastnames)->not->toContain('Villanueva');
});

test('employees map region endpoint returns an office breakdown for the requested region', function () {
    $admin = employeeMapTestAdmin('EMP-MAP-ADM5');

    employeeMapTestEmployee('EMP-MAP-09', 'R5', 'Aquino', 'Regional Office V');
    employeeMapTestEmployee('EMP-MAP-10', 'R5', 'Garcia', 'Regional Office V');
    employeeMapTestEmployee('EMP-MAP-11', 'R5', 'Torres', 'Provincial Office - Albay');
    employeeMapTestEmployee('EMP-MAP-12', 'NCR', 'Lim', 'Regional Office V');

    $response = $this->actingAs($admin)->getJson(route('employees-map.region', ['region' => 'R5']));
    $response->assertOk();

    $breakdown = collect($response->json('officeBreakdown'))->keyBy('office');
    // Dapat 2 lang (hindi 3) — dahil hindi dapat mabilang ang EMP-MAP-12 na NCR employee kahit magkatulad ang OFFICE nila.
    expect($breakdown['Regional Office V']['total'])->toBe(2);
    expect($breakdown['Provincial Office - Albay']['total'])->toBe(1);
});

test('employees map region endpoint requires a region parameter', function () {
    $admin = employeeMapTestAdmin('EMP-MAP-ADM4');

    $response = $this->actingAs($admin)->getJson(route('employees-map.region'));
    $response->assertStatus(422);
});

test('non-admin users cannot access the employees map', function () {
    $employee = employeeMapTestEmployee('EMP-MAP-08', 'NCR');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('employees-map.index'))->assertForbidden();
});
