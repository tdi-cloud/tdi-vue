<?php

use App\Models\Employee;
use App\Models\NhrdcMember;
use App\Models\User;

function nhrdcMemberTestAdmin(string $empcode): User
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

function nhrdcMemberTestEmployee(string $empcode, string $lastname): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'NHRDC Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Test',
        'MI' => 'R',
        'POSITION' => 'NHRDC Member',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'CO',
        'OFFICE' => 'NHRDC Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function addNhrdcMember(string $empcode): NhrdcMember
{
    return NhrdcMember::create(['empcode' => $empcode, 'sort_order' => NhrdcMember::nextSortOrder()]);
}

test('admin can add an employee to the NHRDC roster', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-01');
    $employee = nhrdcMemberTestEmployee('EMP-NM-EMP-01', 'Villanueva');

    $response = $this->actingAs($admin)->post(route('nhrdc-members.store'), ['empcode' => $employee->EMPCODE]);

    $response->assertCreated();
    // The first member ever added to an empty roster becomes Chairperson.
    $response->assertJsonFragment(['empcode' => $employee->EMPCODE, 'name' => $employee->name, 'role' => 'Chairperson, HRDC']);
    $this->assertDatabaseHas('nhrdc_members', ['empcode' => $employee->EMPCODE]);
});

test('NHRDC committee roles follow roster order: chairperson, vice chairperson, then members', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-07');
    $first = nhrdcMemberTestEmployee('EMP-NM-EMP-06', 'Alonzo');
    $second = nhrdcMemberTestEmployee('EMP-NM-EMP-07', 'Bautista');
    $third = nhrdcMemberTestEmployee('EMP-NM-EMP-08', 'Castro');

    addNhrdcMember($first->EMPCODE);
    addNhrdcMember($second->EMPCODE);
    addNhrdcMember($third->EMPCODE);

    $response = $this->actingAs($admin)->getJson(route('nhrdc-members.index'));

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => $first->EMPCODE, 'role' => 'Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $second->EMPCODE, 'role' => 'Vice Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $third->EMPCODE, 'role' => 'Member']);
});

test('removing the chairperson promotes the next member automatically', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-08');
    $first = nhrdcMemberTestEmployee('EMP-NM-EMP-09', 'Domingo');
    $second = nhrdcMemberTestEmployee('EMP-NM-EMP-10', 'Espino');

    $chair = addNhrdcMember($first->EMPCODE);
    addNhrdcMember($second->EMPCODE);

    $this->actingAs($admin)->delete(route('nhrdc-members.destroy', $chair));

    $response = $this->actingAs($admin)->getJson(route('nhrdc-members.index'));

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => $second->EMPCODE, 'role' => 'Chairperson, HRDC']);
});

test('admin can move a member up to swap them into the chairperson role', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-09');
    $first = nhrdcMemberTestEmployee('EMP-NM-EMP-11', 'Flores');
    $second = nhrdcMemberTestEmployee('EMP-NM-EMP-12', 'Gomez');

    addNhrdcMember($first->EMPCODE);
    $viceChair = addNhrdcMember($second->EMPCODE);

    $response = $this->actingAs($admin)->post(route('nhrdc-members.move-up', $viceChair));

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => $second->EMPCODE, 'role' => 'Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $first->EMPCODE, 'role' => 'Vice Chairperson, HRDC']);
});

test('admin can move a member down', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-10');
    $first = nhrdcMemberTestEmployee('EMP-NM-EMP-13', 'Herrera');
    $second = nhrdcMemberTestEmployee('EMP-NM-EMP-14', 'Ilagan');

    $chair = addNhrdcMember($first->EMPCODE);
    addNhrdcMember($second->EMPCODE);

    $response = $this->actingAs($admin)->post(route('nhrdc-members.move-down', $chair));

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => $second->EMPCODE, 'role' => 'Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $first->EMPCODE, 'role' => 'Vice Chairperson, HRDC']);
});

test('moving a member up still works even if sort_order values were left tied', function () {
    // Regression test: a Vice Chairperson whose sort_order happened to tie
    // with the Chairperson's (e.g. from stale/corrupted data) could not be
    // promoted, because the old moveUp() compared raw sort_order values and
    // found no row with a strictly smaller value.
    $admin = nhrdcMemberTestAdmin('EMP-NM-13');
    $chairEmployee = nhrdcMemberTestEmployee('EMP-NM-EMP-18', 'Mendoza');
    $viceChairEmployee = nhrdcMemberTestEmployee('EMP-NM-EMP-19', 'Navarro');

    $chair = NhrdcMember::create(['empcode' => $chairEmployee->EMPCODE, 'sort_order' => 0]);
    $viceChair = NhrdcMember::create(['empcode' => $viceChairEmployee->EMPCODE, 'sort_order' => 0]);

    $response = $this->actingAs($admin)->post(route('nhrdc-members.move-up', $viceChair));

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => $viceChairEmployee->EMPCODE, 'role' => 'Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $chairEmployee->EMPCODE, 'role' => 'Vice Chairperson, HRDC']);

    // The roster should also come out of this fully renumbered (no more ties).
    expect(NhrdcMember::find($chair->id)->sort_order)->not->toBe(NhrdcMember::find($viceChair->id)->sort_order);
});

test('moving the topmost member up or the bottommost member down does nothing', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-11');
    $first = nhrdcMemberTestEmployee('EMP-NM-EMP-15', 'Jimenez');
    $second = nhrdcMemberTestEmployee('EMP-NM-EMP-16', 'Katigbak');

    $chair = addNhrdcMember($first->EMPCODE);
    $member = addNhrdcMember($second->EMPCODE);

    $this->actingAs($admin)->post(route('nhrdc-members.move-up', $chair))->assertOk();
    $this->actingAs($admin)->post(route('nhrdc-members.move-down', $member))->assertOk();

    $response = $this->actingAs($admin)->getJson(route('nhrdc-members.index'));
    $response->assertJsonFragment(['empcode' => $first->EMPCODE, 'role' => 'Chairperson, HRDC']);
    $response->assertJsonFragment(['empcode' => $second->EMPCODE, 'role' => 'Vice Chairperson, HRDC']);
});

test('non-admin users cannot reorder the NHRDC roster', function () {
    $nonAdminEmployee = Employee::forceCreate([
        'EMPCODE' => 'EMP-NM-12',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Manalo',
        'FIRSTNAME' => 'Paolo',
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
    $user = User::factory()->create(['empcode' => $nonAdminEmployee->EMPCODE, 'access' => 'user']);
    $employee = nhrdcMemberTestEmployee('EMP-NM-EMP-17', 'Lopez');
    $member = addNhrdcMember($employee->EMPCODE);

    $this->actingAs($user)
        ->post(route('nhrdc-members.move-up', $member))
        ->assertForbidden();
});

test('an employee cannot be added to the NHRDC roster twice', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-02');
    $employee = nhrdcMemberTestEmployee('EMP-NM-EMP-02', 'Dizon');
    NhrdcMember::create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($admin)->post(route('nhrdc-members.store'), ['empcode' => $employee->EMPCODE]);

    $response->assertSessionHasErrors('empcode');
});

test('adding an unknown empcode to the roster is rejected', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-03');

    $response = $this->actingAs($admin)->post(route('nhrdc-members.store'), ['empcode' => 'DOES-NOT-EXIST']);

    $response->assertSessionHasErrors('empcode');
});

test('admin can list the NHRDC roster with employee details', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-04');
    $employee = nhrdcMemberTestEmployee('EMP-NM-EMP-03', 'Aquino');
    NhrdcMember::create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($admin)->getJson(route('nhrdc-members.index'));

    $response->assertOk();
    $response->assertJsonFragment([
        'empcode' => $employee->EMPCODE,
        'name' => $employee->name,
        'position' => 'NHRDC Member',
        'role' => 'Chairperson, HRDC',
    ]);
});

test('admin can remove an employee from the NHRDC roster', function () {
    $admin = nhrdcMemberTestAdmin('EMP-NM-05');
    $employee = nhrdcMemberTestEmployee('EMP-NM-EMP-04', 'Torres');
    $member = NhrdcMember::create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($admin)->delete(route('nhrdc-members.destroy', $member));

    $response->assertOk();
    $this->assertDatabaseMissing('nhrdc_members', ['id' => $member->id]);
});

test('non-admin users cannot manage the NHRDC roster', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-NM-06',
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
    $nhrdcEmployee = nhrdcMemberTestEmployee('EMP-NM-EMP-05', 'Santos');

    $this->actingAs($user)
        ->post(route('nhrdc-members.store'), ['empcode' => $nhrdcEmployee->EMPCODE])
        ->assertForbidden();
});
