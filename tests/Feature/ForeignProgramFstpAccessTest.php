<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function fstpAccessUser(string $empcode, string $access, bool $isFstpMember = false): User
{
    return User::factory()->create([
        'empcode' => $empcode,
        'access' => $access,
        'is_fstp_member' => $isFstpMember,
    ]);
}

test('an admin who is an FSTP unit member can view foreign programs', function () {
    $this->actingAs(fstpAccessUser('EMP-FSTP-01', 'admin', true))
        ->get(route('foreign-programs.index'))
        ->assertSuccessful();
});

test('a superadmin can view foreign programs without being flagged as an FSTP member', function () {
    $this->actingAs(fstpAccessUser('EMP-FSTP-02', 'superadmin'))
        ->get(route('foreign-programs.index'))
        ->assertSuccessful();
});

test('an admin outside the FSTP unit cannot view or manage foreign programs', function () {
    $admin = fstpAccessUser('EMP-FSTP-03', 'admin');

    $this->actingAs($admin)->get(route('foreign-programs.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('foreign-programs.dashboard-data'))->assertForbidden();
    $this->actingAs($admin)->post(route('foreign-programs.store'), [])->assertForbidden();
    $this->actingAs($admin)->get(route('nhrdc-members.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('organizing-sponsors.index'))->assertForbidden();
});

test('an FSTP member without admin access cannot view foreign programs', function () {
    $this->actingAs(fstpAccessUser('EMP-FSTP-04', 'user', true))
        ->get(route('foreign-programs.index'))
        ->assertForbidden();
});

test('non-foreign admin pages stay available to admins outside the FSTP unit', function () {
    $this->actingAs(fstpAccessUser('EMP-FSTP-05', 'admin'))
        ->get(route('calendar.index'))
        ->assertSuccessful();
});

test('the shared auth props tell the frontend whether to show Foreign Programs', function (string $access, bool $isFstpMember, bool $expected) {
    $this->actingAs(fstpAccessUser('EMP-FSTP-06', $access, $isFstpMember))
        ->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessForeignPrograms', $expected));
})->with([
    'fstp admin' => ['admin', true, true],
    'non-fstp admin' => ['admin', false, false],
    'superadmin' => ['superadmin', false, true],
]);

test('a superadmin can add and remove a user from the FSTP unit', function () {
    $superadmin = fstpAccessUser('EMP-FSTP-07', 'superadmin');
    $admin = fstpAccessUser('EMP-FSTP-08', 'admin');

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $admin), ['is_fstp_member' => true])
        ->assertRedirect();

    expect($admin->fresh()->is_fstp_member)->toBeTrue()
        ->and($admin->fresh()->canAccessForeignPrograms())->toBeTrue();

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $admin), ['is_fstp_member' => false])
        ->assertRedirect();

    expect($admin->fresh()->canAccessForeignPrograms())->toBeFalse();
});

test('an admin cannot change FSTP unit membership', function () {
    $admin = fstpAccessUser('EMP-FSTP-09', 'admin', true);
    $other = fstpAccessUser('EMP-FSTP-10', 'admin');

    $this->actingAs($admin)
        ->put(route('user-management.update', $other), ['is_fstp_member' => true])
        ->assertForbidden();

    expect($other->fresh()->is_fstp_member)->toBeFalse();
});

test('the user management page can be filtered to FSTP unit members', function () {
    $superadmin = fstpAccessUser('EMP-FSTP-11', 'superadmin');
    fstpAccessUser('EMP-FSTP-12', 'admin', true);
    fstpAccessUser('EMP-FSTP-13', 'admin');

    $this->actingAs($superadmin)
        ->get(route('user-management.index', ['fstp' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.empcode', 'EMP-FSTP-12')
            ->where('users.data.0.is_fstp_member', true)
        );
});
