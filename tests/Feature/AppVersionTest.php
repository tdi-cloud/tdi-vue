<?php

use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fakeBasePath = storage_path('framework/testing/app-version-'.uniqid());
    File::ensureDirectoryExists($this->fakeBasePath.'/.git/refs/heads');
});

afterEach(function () {
    File::deleteDirectory($this->fakeBasePath);
});

test('the application version label is shared with every page', function () {
    $this->actingAs(User::factory()->create(['empcode' => 'EMP-VERSION-01']));

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('appVersion', AppVersion::label())
    );
});

test('the label includes the short commit hash from the current git branch', function () {
    File::put($this->fakeBasePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($this->fakeBasePath.'/.git/refs/heads/main', "1e05267a9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e\n");

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version').' (1e05267)');
});

test('the commit hash is read from packed refs when the branch file is missing', function () {
    File::put($this->fakeBasePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($this->fakeBasePath.'/.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted\nabcdef1234567890abcdef1234567890abcdef12 refs/heads/main\n");

    expect(AppVersion::commit($this->fakeBasePath))->toBe('abcdef1');
});

test('a REVISION file written at deploy time takes priority over git', function () {
    File::put($this->fakeBasePath.'/REVISION', "9f8e7d6c5b4a\n");
    File::put($this->fakeBasePath.'/.git/HEAD', "1111111111111111111111111111111111111111\n");

    expect(AppVersion::commit($this->fakeBasePath))->toBe('9f8e7d6');
});

test('the label falls back to the release number when no commit can be found', function () {
    File::deleteDirectory($this->fakeBasePath.'/.git');

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version'));
});
