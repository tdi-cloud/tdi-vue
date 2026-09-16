<?php

use App\Models\Employee;
use App\Models\User;

function userMgmtOfficeDivisionTestSuperAdmin(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'CO-Test Division',
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

    return User::factory()->create(['empcode' => $empcode, 'access' => 'superadmin']);
}

test('user management list includes each user\'s office/division from their linked employee record', function () {
    $superAdmin = userMgmtOfficeDivisionTestSuperAdmin('EMP-UM-SA-01');

    Employee::forceCreate([
        'EMPCODE' => 'EMP-UM-01',
        'OFFICE/DIVISION' => 'R3-Regional Office',
        'LASTNAME' => 'Reyes',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'R3',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
    $linkedUser = User::factory()->create(['empcode' => 'EMP-UM-01', 'access' => 'user']);

    // Walang tumutugmang Employee record ang empcode na ito — dapat null ang
    // office_division, hindi mag-error.
    $unlinkedUser = User::factory()->create(['empcode' => 'EMP-UM-NONEXISTENT', 'access' => 'user']);

    $response = $this->actingAs($superAdmin)->get(route('user-management.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('UserManagement/index')
        ->where('users.data', function ($data) use ($linkedUser, $unlinkedUser) {
            $rows = collect($data);
            $linkedRow = $rows->firstWhere('id', $linkedUser->id);
            $unlinkedRow = $rows->firstWhere('id', $unlinkedUser->id);

            return $linkedRow['office_division'] === 'R3-Regional Office'
                && $unlinkedRow['office_division'] === null;
        })
    );
});

test('non-superadmin users cannot access the user management page', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-UM-USER',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Cruz',
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
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'admin']);

    $this->actingAs($user)->get(route('user-management.index'))->assertForbidden();
});
