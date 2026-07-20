<?php

use App\Models\SiteImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function siteImageSuperadmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'superadmin']);
}

test('homepage falls back to the default image when no override exists', function () {
    $urls = SiteImage::resolvedUrls();

    expect($urls['fstp_photo_1'])->toBe(config('site-images.fstp_photo_1.default'));
});

test('superadmin can view the homepage images page', function () {
    $superadmin = siteImageSuperadmin('EMP-SA-200');

    $this->actingAs($superadmin)
        ->get(route('site-images.index'))
        ->assertSuccessful();
});

test('regular admin cannot view the homepage images page', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-ADM-200', 'access' => 'admin']);

    $this->actingAs($admin)
        ->get(route('site-images.index'))
        ->assertForbidden();
});

test('superadmin can replace an fstp nominee photo and it resolves afterwards', function () {
    Storage::fake('public');
    $superadmin = siteImageSuperadmin('EMP-SA-201');

    $this->actingAs($superadmin)
        ->post(route('site-images.update', 'fstp_photo_1'), [
            'image' => UploadedFile::fake()->image('nominees.jpg'),
        ])
        ->assertRedirect();

    $siteImage = SiteImage::where('key', 'fstp_photo_1')->first();
    expect($siteImage)->not->toBeNull();
    Storage::disk('public')->assertExists($siteImage->path);

    $urls = SiteImage::resolvedUrls();
    expect($urls['fstp_photo_1'])->not->toBe(config('site-images.fstp_photo_1.default'));
});

test('uploading to an unknown image key is rejected', function () {
    $superadmin = siteImageSuperadmin('EMP-SA-202');

    $this->actingAs($superadmin)
        ->post(route('site-images.update', 'not-a-real-slot'), [
            'image' => UploadedFile::fake()->image('x.jpg'),
        ])
        ->assertNotFound();
});

test('superadmin can reset a customized image back to its default', function () {
    Storage::fake('public');
    $superadmin = siteImageSuperadmin('EMP-SA-203');
    $path = UploadedFile::fake()->image('logo.png')->store('site-images', 'public');
    SiteImage::create(['key' => 'sponsor_logo_jica', 'path' => $path]);

    $this->actingAs($superadmin)
        ->delete(route('site-images.destroy', 'sponsor_logo_jica'))
        ->assertRedirect();

    expect(SiteImage::where('key', 'sponsor_logo_jica')->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

test('non-image uploads are rejected', function () {
    Storage::fake('public');
    $superadmin = siteImageSuperadmin('EMP-SA-204');

    $this->actingAs($superadmin)
        ->post(route('site-images.update', 'about_photo'), [
            'image' => UploadedFile::fake()->create('document.pdf', 100),
        ])
        ->assertSessionHasErrors('image');
});
