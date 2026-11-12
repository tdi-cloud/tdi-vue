<?php

use App\Models\Employee;
use App\Models\EmployeePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function photoUser(string $access): User
{
    return User::factory()->create(['empcode' => 'EMP-PIC-'.$access, 'access' => $access]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function photoEmployee(string $empcode, array $overrides = []): Employee
{
    return Employee::forceCreate(array_merge([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Office A',
        'LASTNAME' => 'Lastname '.$empcode,
        'FIRSTNAME' => 'Firstname',
        'MI' => 'D',
        'POSITION' => 'Director IV',
        'SG' => '28',
        'PLANTILLA STATUS' => 'PERMANENT',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'RO',
        'LOCATION' => '',
        'SECTION' => '',
        'UNIT' => '',
    ], $overrides));
}

/**
 * The photo_url of one employee row on the Employees page.
 */
function employeesPagePhotoUrl(mixed $testCase, User $user, string $empcode): ?string
{
    $rows = $testCase->actingAs($user)->get(route('employees.index', ['search' => $empcode]))->inertiaProps('employees.data');

    return collect($rows)->firstWhere('EMPCODE', $empcode)['photo_url'];
}

test('the position directory page no longer exists', function () {
    $this->actingAs(photoUser('superadmin'))
        ->get('/employee-directory')
        ->assertNotFound();
});

test('the employees page tells who may manage pictures and the upload rules', function () {
    photoEmployee('EMP-1');

    $this->actingAs(photoUser('admin'))
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManagePhotos', false)
            ->where('photoRules.maxKilobytes', 5120)
            ->where('photoRules.types', ['jpg', 'jpeg', 'png', 'webp']));

    $this->actingAs(photoUser('superadmin'))
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canManagePhotos', true));
});

test('a superadmin can upload a picture, which is resized to webp and shown on the employees page', function () {
    Storage::fake('public');
    photoEmployee('EMP-1');
    $superadmin = photoUser('superadmin');

    $response = $this->actingAs($superadmin)
        ->post(route('employees.photo.update', 'EMP-1'), [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 1600, 2000),
        ])
        ->assertSuccessful()
        ->assertJsonPath('title', 'Profile picture added');

    $photo = EmployeePhoto::where('empcode', 'EMP-1')->sole();

    expect($photo->path)->toStartWith('employee-profiles/')->toEndWith('.webp');
    expect($photo->uploaded_by)->toBe($superadmin->id);
    Storage::disk('public')->assertExists($photo->path);
    expect($response->json('photo_url'))->toBe($photo->url);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($photo->path));
    expect(max($width, $height))->toBe(720);
    expect($height)->toBeGreaterThan($width);

    expect(employeesPagePhotoUrl($this, $superadmin, 'EMP-1'))->toBe($photo->url);
});

test('picture urls are host-relative so they load on any address the app is opened with', function () {
    // e.g. APP_URL is the LAN address while someone browses on localhost.
    config(['filesystems.disks.public.url' => 'http://192.168.20.112:8000/storage']);
    Storage::forgetDisk('public');

    expect(EmployeePhoto::publicUrl('employee-profiles/abc.webp'))->toBe('/storage/employee-profiles/abc.webp');
    expect(EmployeePhoto::factory()->make(['path' => 'employee-profiles/xyz.webp'])->url)->toBe('/storage/employee-profiles/xyz.webp');
});

test('replacing a picture stores the new one and deletes only the old file', function () {
    Storage::fake('public');
    photoEmployee('EMP-1');
    photoEmployee('EMP-2');
    $superadmin = photoUser('superadmin');

    $this->actingAs($superadmin)->post(route('employees.photo.update', 'EMP-1'), ['photo' => UploadedFile::fake()->image('a.png', 300, 300)]);
    $this->actingAs($superadmin)->post(route('employees.photo.update', 'EMP-2'), ['photo' => UploadedFile::fake()->image('b.png', 300, 300)]);

    $oldPath = EmployeePhoto::where('empcode', 'EMP-1')->value('path');
    $otherPath = EmployeePhoto::where('empcode', 'EMP-2')->value('path');

    $this->actingAs($superadmin)
        ->post(route('employees.photo.update', 'EMP-1'), ['photo' => UploadedFile::fake()->image('c.webp', 300, 300)])
        ->assertSuccessful()
        ->assertJsonPath('title', 'Profile picture changed');

    $newPath = EmployeePhoto::where('empcode', 'EMP-1')->value('path');

    expect($newPath)->not->toBe($oldPath);
    expect(EmployeePhoto::count())->toBe(2);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertExists($otherPath);
});

test('removing a picture deletes the file and restores the placeholder without touching the employee', function () {
    Storage::fake('public');
    photoEmployee('EMP-1');
    $superadmin = photoUser('superadmin');

    $this->actingAs($superadmin)->post(route('employees.photo.update', 'EMP-1'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
    $path = EmployeePhoto::where('empcode', 'EMP-1')->value('path');

    $this->actingAs($superadmin)
        ->delete(route('employees.photo.destroy', 'EMP-1'))
        ->assertSuccessful()
        ->assertJsonPath('photo_url', null);

    Storage::disk('public')->assertMissing($path);
    expect(EmployeePhoto::count())->toBe(0);
    expect(Employee::where('EMPCODE', 'EMP-1')->exists())->toBeTrue();
    expect(employeesPagePhotoUrl($this, $superadmin, 'EMP-1'))->toBeNull();
});

test('regular admins cannot upload, replace or remove pictures', function () {
    Storage::fake('public');
    photoEmployee('EMP-1');
    EmployeePhoto::factory()->create(['empcode' => 'EMP-1']);
    $admin = photoUser('admin');

    $this->actingAs($admin)
        ->post(route('employees.photo.update', 'EMP-1'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('employees.photo.destroy', 'EMP-1'))
        ->assertForbidden();

    expect(EmployeePhoto::count())->toBe(1);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('pictures for an unknown employee code are refused', function () {
    Storage::fake('public');

    $this->actingAs(photoUser('superadmin'))
        ->post(route('employees.photo.update', 'NOT-AN-EMPLOYEE'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)])
        ->assertNotFound();

    expect(EmployeePhoto::count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('unsafe or unsupported uploads are rejected', function (Closure $makeFile) {
    Storage::fake('public');
    photoEmployee('EMP-1');

    $this->actingAs(photoUser('superadmin'))
        ->postJson(route('employees.photo.update', 'EMP-1'), ['photo' => $makeFile()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');

    expect(EmployeePhoto::count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'pdf document' => fn () => fn () => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    'script renamed to jpg' => fn () => fn () => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "hacked"; ?>'),
    'svg image' => fn () => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    'gif image' => fn () => fn () => UploadedFile::fake()->image('animated.gif', 300, 300),
    'larger than 5 MB' => fn () => fn () => UploadedFile::fake()->image('huge.jpg', 300, 300)->size(6000),
    'too small to use' => fn () => fn () => UploadedFile::fake()->image('tiny.jpg', 20, 20),
]);

test('a picture survives the monthly re-import because it is linked by empcode', function () {
    Storage::fake('public');
    photoEmployee('EMP-1');
    $superadmin = photoUser('superadmin');

    $this->actingAs($superadmin)->post(route('employees.photo.update', 'EMP-1'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
    $url = EmployeePhoto::sole()->url;

    // HR empties the table and imports it again: same EMPCODE, new id.
    Employee::query()->where('EMPCODE', 'EMP-1')->delete();
    photoEmployee('EMP-1', ['id' => 9001]);

    expect(employeesPagePhotoUrl($this, $superadmin, 'EMP-1'))->toBe($url);
});

test('the dashboard no longer loads the position directory shortcut', function () {
    $this->actingAs(photoUser('admin'))
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('positionDirectory')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summary')
                ->missing('positionDirectory')));
});
