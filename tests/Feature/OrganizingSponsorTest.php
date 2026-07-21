<?php

use App\Models\Employee;
use App\Models\OrganizingSponsor;
use App\Models\User;

function organizingSponsorTestAdmin(string $empcode): User
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

test('admin can create a sponsor with an abbreviation and a full name', function () {
    $admin = organizingSponsorTestAdmin('EMP-ORGSPON-01');

    $response = $this->actingAs($admin)->post(route('organizing-sponsors.store'), [
        'name' => 'JICA',
        'full_name' => 'Japan International Cooperation Agency',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'JICA',
        'full_name' => 'Japan International Cooperation Agency',
    ]);

    $this->assertDatabaseHas('organizing_sponsors', [
        'name' => 'JICA',
        'full_name' => 'Japan International Cooperation Agency',
    ]);
});

test('the full name is optional when creating a sponsor', function () {
    $admin = organizingSponsorTestAdmin('EMP-ORGSPON-02');

    $response = $this->actingAs($admin)->post(route('organizing-sponsors.store'), [
        'name' => 'CPSC',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('organizing_sponsors', ['name' => 'CPSC', 'full_name' => null]);
});

test('admin can set the full name on an existing sponsor without changing its abbreviation', function () {
    $admin = organizingSponsorTestAdmin('EMP-ORGSPON-03');
    $sponsor = OrganizingSponsor::create(['name' => 'KOICA']);

    $response = $this->actingAs($admin)->put(route('organizing-sponsors.update', $sponsor), [
        'full_name' => 'Korea International Cooperation Agency',
    ]);

    $response->assertOk();

    $sponsor->refresh();
    expect($sponsor->name)->toBe('KOICA');
    expect($sponsor->full_name)->toBe('Korea International Cooperation Agency');
});

test('non-admin users cannot manage organizing sponsors', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-ORGSPON-04',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Reyes',
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
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $sponsor = OrganizingSponsor::create(['name' => 'MTCP']);

    $this->actingAs($user)
        ->post(route('organizing-sponsors.store'), ['name' => 'SCP'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('organizing-sponsors.update', $sponsor), ['full_name' => 'Something'])
        ->assertForbidden();
});
