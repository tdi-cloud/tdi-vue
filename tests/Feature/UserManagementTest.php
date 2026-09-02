<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function userManagementSeedSession(User $user, int $secondsAgo): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'payload' => base64_encode('test-payload'),
        'last_activity' => now()->subSeconds($secondsAgo)->timestamp,
    ]);
}

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

test('superadmin can edit another user\'s name', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-104');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-104', 'access' => 'user', 'name' => 'Old Name']);

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $target), ['name' => 'New Name'])
        ->assertRedirect();

    expect($target->fresh()->name)->toBe('New Name');
});

test('name is required when editing a user\'s name', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-105');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-105', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->put(route('user-management.update', $target), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('superadmin can upload a profile picture for another user', function () {
    Storage::fake('public');

    $superadmin = userManagementSuperadmin('EMP-SA-106');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-106', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->post(route('user-management.avatar.update', $target), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->getRawOriginal('avatar'))->not->toBeNull();
    Storage::disk('public')->assertExists($target->getRawOriginal('avatar'));
});

test('uploading a new avatar replaces the previous one', function () {
    Storage::fake('public');

    $superadmin = userManagementSuperadmin('EMP-SA-107');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-107', 'access' => 'user']);

    $this->actingAs($superadmin)->post(route('user-management.avatar.update', $target), [
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $firstPath = $target->fresh()->getRawOriginal('avatar');

    $this->actingAs($superadmin)->post(route('user-management.avatar.update', $target), [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($target->fresh()->getRawOriginal('avatar'));
});

test('avatar upload rejects non-image files', function () {
    Storage::fake('public');

    $superadmin = userManagementSuperadmin('EMP-SA-108');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-108', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->post(route('user-management.avatar.update', $target), [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

test('superadmin can remove another user\'s profile picture', function () {
    Storage::fake('public');

    $superadmin = userManagementSuperadmin('EMP-SA-109');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-109', 'access' => 'user']);

    $this->actingAs($superadmin)->post(route('user-management.avatar.update', $target), [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);
    $path = $target->fresh()->getRawOriginal('avatar');

    $this->actingAs($superadmin)
        ->delete(route('user-management.avatar.destroy', $target))
        ->assertRedirect();

    expect($target->fresh()->getRawOriginal('avatar'))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('regular admin cannot edit a user\'s name or avatar', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['empcode' => 'EMP-ADM-101', 'access' => 'admin']);
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-110', 'access' => 'user']);

    $this->actingAs($admin)
        ->put(route('user-management.update', $target), ['name' => 'Hacked Name'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('user-management.avatar.update', $target), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertForbidden();
});

test('a user with a recent session is marked as online', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-111');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-111', 'access' => 'user']);
    userManagementSeedSession($target, secondsAgo: 30);

    $this->actingAs($superadmin)
        ->get(route('user-management.index', ['search' => 'EMP-TARGET-111']))
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.is_online', true)
            ->where('users.data.0.last_active_at', fn ($value) => $value !== null)
        );
});

test('a user with a stale session is marked offline with a last-seen time', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-112');
    $target = User::factory()->create(['empcode' => 'EMP-TARGET-112', 'access' => 'user']);
    userManagementSeedSession($target, secondsAgo: 30 * 60);

    $this->actingAs($superadmin)
        ->get(route('user-management.index', ['search' => 'EMP-TARGET-112']))
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.is_online', false)
            ->where('users.data.0.last_active_at', fn ($value) => $value !== null)
        );
});

test('online users are listed before offline users', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-114');

    $offline = User::factory()->create(['empcode' => 'EMP-TARGET-114A', 'access' => 'user', 'name' => 'Aaa Offline']);
    userManagementSeedSession($offline, secondsAgo: 30 * 60);

    $online = User::factory()->create(['empcode' => 'EMP-TARGET-114B', 'access' => 'user', 'name' => 'Zzz Online']);
    userManagementSeedSession($online, secondsAgo: 10);

    $response = $this->actingAs($superadmin)
        ->get(route('user-management.index', ['search' => 'EMP-TARGET-114']));

    $response->assertInertia(fn ($page) => $page
        ->where('users.data.0.empcode', 'EMP-TARGET-114B')
        ->where('users.data.0.is_online', true)
        ->where('users.data.1.empcode', 'EMP-TARGET-114A')
        ->where('users.data.1.is_online', false)
    );
});

test('a user who never logged in has no last-active time', function () {
    $superadmin = userManagementSuperadmin('EMP-SA-113');
    User::factory()->create(['empcode' => 'EMP-TARGET-113', 'access' => 'user']);

    $this->actingAs($superadmin)
        ->get(route('user-management.index', ['search' => 'EMP-TARGET-113']))
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.is_online', false)
            ->where('users.data.0.last_active_at', null)
        );
});
