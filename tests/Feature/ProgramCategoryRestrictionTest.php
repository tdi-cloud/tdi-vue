<?php

use App\Models\Employee;
use App\Models\Program;
use App\Models\User;

function programTestAdmin(string $empcode, string $region): User
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function programPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ], $overrides);
}

test('a CO admin can create a program with any category and office initiated', function () {
    $admin = programTestAdmin('EMP-501', 'CO');

    $response = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'category' => 'Foreign-FSTP',
        'initiated' => 'TDI',
    ]));

    $response->assertSessionDoesntHaveErrors();
    expect(Program::where('title', 'Test Program')->exists())->toBeTrue();
});

test('a non-CO admin can only create a program with the Regional category', function () {
    $admin = programTestAdmin('EMP-502', 'Region IV-A');

    $rejected = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'category' => 'Foreign-FSTP',
    ]));
    $rejected->assertSessionHasErrors('category');

    $allowed = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'category' => 'Regional',
    ]));
    $allowed->assertSessionDoesntHaveErrors();
});

test('a non-CO admin can only create a program with NTTA or Other Training Provider as office initiated', function () {
    $admin = programTestAdmin('EMP-503', 'Region IV-A');

    $rejected = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'initiated' => 'TDI',
    ]));
    $rejected->assertSessionHasErrors('initiated');

    $allowed = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'initiated' => 'Other Training Provider',
    ]));
    $allowed->assertSessionDoesntHaveErrors();
});

test('an admin with no matching employee record is treated as unrestricted', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-DOES-NOT-EXIST', 'access' => 'admin']);

    $response = $this->actingAs($admin)->post(route('programs.store'), programPayload([
        'category' => 'Foreign-FSTP',
        'initiated' => 'TDI',
    ]));

    $response->assertSessionDoesntHaveErrors();
});

test('the same category and office-initiated restriction applies when updating a program', function () {
    $admin = programTestAdmin('EMP-504', 'Region IV-A');
    $program = Program::create(programPayload());

    $response = $this->actingAs($admin)->put(route('programs.update', $program), programPayload([
        'category' => 'Foreign-FSTP',
    ]));

    $response->assertSessionHasErrors('category');
});
