<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignProgram;
use App\Models\User;

function dashboardNomineesTestAdmin(string $empcode): User
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

function dashboardNomineesTestProgram(string $sponsor = 'JICA'): ForeignProgram
{
    return ForeignProgram::create([
        'program_title' => 'Dashboard Nominees Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => $sponsor,
        'status' => 'for_dissemination',
    ]);
}

function dashboardNomineesTestNominee(ForeignProgram $program, array $overrides = []): ForeignNominee
{
    return ForeignNominee::create(array_merge([
        'foreign_program_id' => $program->id,
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'TESDA',
        'status' => 'accepted',
    ], $overrides));
}

test('dashboard nominees endpoint filters by status', function () {
    $admin = dashboardNomineesTestAdmin('EMP-DNOM-01');
    $program = dashboardNomineesTestProgram();

    dashboardNomineesTestNominee($program, ['status' => 'accepted', 'firstname' => 'Accepted1']);
    dashboardNomineesTestNominee($program, ['status' => 'accepted', 'firstname' => 'Accepted2']);
    dashboardNomineesTestNominee($program, ['status' => 'regret', 'firstname' => 'Regretted1']);

    $response = $this->actingAs($admin)->getJson(
        route('foreign-programs.dashboard-nominees', ['status' => 'accepted'])
    );

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect(collect($response->json('data'))->pluck('status')->unique()->all())->toBe(['accepted']);
});

test('dashboard nominees endpoint filters by organizing sponsor', function () {
    $admin = dashboardNomineesTestAdmin('EMP-DNOM-02');
    $jica = dashboardNomineesTestProgram('JICA');
    $koica = dashboardNomineesTestProgram('KOICA');

    dashboardNomineesTestNominee($jica, ['firstname' => 'FromJica']);
    dashboardNomineesTestNominee($koica, ['firstname' => 'FromKoica']);

    $response = $this->actingAs($admin)->getJson(
        route('foreign-programs.dashboard-nominees', ['organizing_sponsor' => 'JICA'])
    );

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.name'))->toContain('FromJica');
});

test('dashboard nominees endpoint filters by search term', function () {
    $admin = dashboardNomineesTestAdmin('EMP-DNOM-03');
    $program = dashboardNomineesTestProgram();

    dashboardNomineesTestNominee($program, ['firstname' => 'Maria', 'surname' => 'Santos']);
    dashboardNomineesTestNominee($program, ['firstname' => 'Pedro', 'surname' => 'Reyes']);

    $response = $this->actingAs($admin)->getJson(
        route('foreign-programs.dashboard-nominees', ['search' => 'Santos'])
    );

    $response->assertOk();
    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.name'))->toContain('Santos');
});

test('non-admin users cannot access the dashboard nominees endpoint', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-DNOM-04',
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

    $response = $this->actingAs($user)->getJson(route('foreign-programs.dashboard-nominees'));

    $response->assertForbidden();
});
