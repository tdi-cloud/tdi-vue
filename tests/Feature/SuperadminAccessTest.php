<?php

use App\Models\User;

test('superadmin can access admin-only routes', function () {
    $superadmin = User::factory()->create(['empcode' => 'EMP-SA-001', 'access' => 'superadmin']);

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertSuccessful();
});

test('regular user is forbidden from admin-only routes', function () {
    $user = User::factory()->create(['empcode' => 'EMP-USR-001', 'access' => 'user']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('User::isAdmin returns true for admin and superadmin, false otherwise', function (string $access, bool $expected) {
    $user = User::factory()->create(['empcode' => 'EMP-'.$access, 'access' => $access]);

    expect($user->isAdmin())->toBe($expected);
})->with([
    'admin' => ['admin', true],
    'superadmin' => ['superadmin', true],
    'user' => ['user', false],
    'guest' => ['guest', false],
]);
