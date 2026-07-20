<?php

use App\Models\User;

function userManagementSuperadmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'superadmin']);
}

test('superadmin can view the user management page', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-100');
    User::factory()->create(['empcode' => 'EMP-OTHER-100', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->get(route('user-management.index'))
        ->assertSuccessful();
});

test('regular admin cannot view the user management page', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-ADM-100', 'access' => 'admin']);

    $this->actingAs($admin)
        ->get(route('user-management.index'))
        ->assertForbidden();
});

test('guest is redirected away from the user management page', function () {
    $this->get(route('user-management.index'))->assertRedirect(route('login'));
});

test('superadmin can change another user\'s access level', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-101');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-101', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $target), ['access' => 'admin'])
        ->assertRedirect();

    expect($target->fresh()->access)->toBe('admin');
});

test('superadmin cannot change their own access level', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-102');

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $superadmin), ['access' => 'user'])
        ->assertRedirect();

    expect($superadmin->fresh()->access)->toBe('superadmin');
});

test('access level must be a valid option', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-103');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-103', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $target), ['access' => 'owner'])
        ->assertSessionHasErrors('access');
});
