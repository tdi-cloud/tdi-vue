<?php

use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fakeBasePath = storage_path('framework/testing/app-version-'.uniqid());
    File::ensureDirectoryExists($this->fakeBasePath.'/.git/refs/heads');

    Process::fake(['*' => Process::result(output: '', errorOutput: 'fatal: No names found', exitCode: 128)]);
});

afterEach(function () {
    File::deleteDirectory($this->fakeBasePath);
});

function writeGitBranch(string $basePath, string $hash): void
{
    File::put($basePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($basePath.'/.git/refs/heads/main', $hash."\n");
}

test('the application version label is shared with every page', function () {
    $this->actingAs(User::factory()->create(['empcode' => 'EMP-VERSION-01']));

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('appVersion.short', AppVersion::shortLabel())
        ->where('appVersion.full', 'TDI '.AppVersion::label())
    );
});

test('the short label shows only the version and build number', function () {
    writeGitBranch($this->fakeBasePath, '51ee70fa9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e');
    Process::fake(['*' => Process::result('v2.0.0-3-g51ee70f')]);

    expect(AppVersion::shortLabel($this->fakeBasePath))->toBe('v'.config('app.version').' b3');
});

test('the short label is just the version when there is no build number', function () {
    File::deleteDirectory($this->fakeBasePath.'/.git');

    expect(AppVersion::shortLabel($this->fakeBasePath))->toBe('v'.config('app.version'));
});

test('the label shows the build count since the last release tag and the commit hash', function () {
    writeGitBranch($this->fakeBasePath, '1e05267a9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e');
    Process::fake(['*' => Process::result('v1.0.0-3-g1e05267')]);

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version').' build 3 (1e05267)');
});

test('the build count is cached per commit so git runs once', function () {
    writeGitBranch($this->fakeBasePath, '1e05267a9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e');
    Process::fake(['*' => Process::result('v1.0.0-3-g1e05267')]);

    AppVersion::buildNumber($this->fakeBasePath);
    AppVersion::buildNumber($this->fakeBasePath);

    Process::assertRanTimes(fn ($process) => $process->command === ['git', 'describe', '--tags', '--long', '--match', 'v*'], 1);
});

test('the label omits the build count when there is no release tag', function () {
    writeGitBranch($this->fakeBasePath, '1e05267a9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e');

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version').' (1e05267)');
});

test('the commit hash is read from packed refs when the branch file is missing', function () {
    File::put($this->fakeBasePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($this->fakeBasePath.'/.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted\nabcdef1234567890abcdef1234567890abcdef12 refs/heads/main\n");

    expect(AppVersion::commit($this->fakeBasePath))->toBe('abcdef1');
});

test('a REVISION file from git describe gives the build count and hash without running git', function () {
    File::put($this->fakeBasePath.'/REVISION', "v1.0.0-5-g9f8e7d6\n");
    writeGitBranch($this->fakeBasePath, '1111111111111111111111111111111111111111');

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version').' build 5 (9f8e7d6)');
    Process::assertNothingRan();
});

test('a REVISION file with only a commit hash still shows the hash', function () {
    File::put($this->fakeBasePath.'/REVISION', "9f8e7d6c5b4a\n");

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version').' (9f8e7d6)');
});

test('the label falls back to the release number when no commit can be found', function () {
    File::deleteDirectory($this->fakeBasePath.'/.git');

    expect(AppVersion::label($this->fakeBasePath))->toBe('v'.config('app.version'));
});
