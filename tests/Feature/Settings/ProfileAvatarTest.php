<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can upload a profile picture', function () {
    Storage::fake('public');

    $user = User::factory()->create(['empcode' => 'EMP-AVT-'.uniqid()]);

    $response = $this->actingAs($user)->post('/settings/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $user->refresh();
    expect($user->getRawOriginal('avatar'))->not->toBeNull();
    Storage::disk('public')->assertExists($user->getRawOriginal('avatar'));
    expect($user->avatar)->toContain('/storage/');
});

test('uploading a new profile picture replaces the old one', function () {
    Storage::fake('public');

    $user = User::factory()->create(['empcode' => 'EMP-AVT-'.uniqid()]);

    $this->actingAs($user)->post('/settings/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $user->refresh();
    $firstPath = $user->getRawOriginal('avatar');

    $this->actingAs($user)->post('/settings/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);
    $user->refresh();
    $secondPath = $user->getRawOriginal('avatar');

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('non-image files are rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create(['empcode' => 'EMP-AVT-'.uniqid()]);

    $response = $this->actingAs($user)->post('/settings/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('document.pdf', 100),
    ]);

    $response->assertSessionHasErrors('avatar');
});

test('user can remove their profile picture', function () {
    Storage::fake('public');

    $user = User::factory()->create(['empcode' => 'EMP-AVT-'.uniqid()]);

    $this->actingAs($user)->post('/settings/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('photo.jpg'),
    ]);
    $user->refresh();
    $path = $user->getRawOriginal('avatar');

    $response = $this->actingAs($user)->delete('/settings/profile/avatar');

    $response->assertSessionHasNoErrors()->assertRedirect();

    $user->refresh();
    expect($user->getRawOriginal('avatar'))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
