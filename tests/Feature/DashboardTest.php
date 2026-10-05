<?php

use App\Models\Employee;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('the dashboard greets the admin by their full first name from the employee record', function () {
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => 'EMP-DASH-01',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Balucas',
        'FIRSTNAME' => 'MA. THERESE ANGELICA',
        'MI' => 'B',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    $admin = User::factory()->create(['empcode' => 'EMP-DASH-01', 'access' => 'admin', 'name' => 'MA. THERESE ANGELICA B. BALUCAS']);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('firstName', 'MA. THERESE ANGELICA'));
});

test('the dashboard first name is null when the admin has no employee record', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-DASH-02', 'access' => 'admin']);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('firstName', null));
});
