<?php

use App\Models\Employee;
use App\Models\EmployeePhoto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 */
function photoIndexEmployee(string $empcode, array $overrides = []): Employee
{
    return Employee::forceCreate(array_merge([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Lastname '.$empcode,
        'FIRSTNAME' => 'Firstname',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Photo Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ], $overrides));
}

function photoIndexAdmin(): User
{
    photoIndexEmployee('EMP-PHOTO-ADM', ['OFFICE' => 'Admin Office']);

    return User::factory()->create(['empcode' => 'EMP-PHOTO-ADM', 'access' => 'admin']);
}

/**
 * @return array<string, mixed>
 */
function photoIndexRow(mixed $response, string $empcode): array
{
    return collect($response->inertiaProps('employees')['data'])->firstWhere('EMPCODE', $empcode);
}

test('employees index exposes the directory picture as a host-relative photo url', function () {
    Storage::fake('public');
    $admin = photoIndexAdmin();

    photoIndexEmployee('EMP-PHOTO-01');
    EmployeePhoto::factory()->create(['empcode' => 'EMP-PHOTO-01', 'path' => 'employee-profiles/ana.webp']);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PHOTO-01']));
    $response->assertOk();

    $row = photoIndexRow($response, 'EMP-PHOTO-01');

    expect($row['photo_url'])->toBe('/storage/employee-profiles/ana.webp')
        ->and($row)->not->toHaveKey('photo');
});

test('employees index keeps both the directory picture and the account avatar so the picture can be preferred', function () {
    Storage::fake('public');
    $admin = photoIndexAdmin();

    photoIndexEmployee('EMP-PHOTO-02');
    EmployeePhoto::factory()->create(['empcode' => 'EMP-PHOTO-02', 'path' => 'employee-profiles/ben.webp']);
    User::factory()->create(['empcode' => 'EMP-PHOTO-02', 'avatar' => 'avatars/ben.jpg']);

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PHOTO-02']));
    $row = photoIndexRow($response, 'EMP-PHOTO-02');

    expect($row['photo_url'])->toBe('/storage/employee-profiles/ben.webp')
        ->and($row['avatar'])->toContain('/storage/avatars/ben.jpg');
});

test('employees index reports a null photo url when the employee has no directory picture', function () {
    $admin = photoIndexAdmin();

    photoIndexEmployee('EMP-PHOTO-03');

    $response = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-PHOTO-03']));
    $row = photoIndexRow($response, 'EMP-PHOTO-03');

    expect($row)->toHaveKey('photo_url')
        ->and($row['photo_url'])->toBeNull();
});

test('employees index loads pictures without a query per employee', function () {
    Storage::fake('public');
    $admin = photoIndexAdmin();

    $countQueries = function (int $employees) use ($admin): int {
        foreach (range(1, $employees) as $number) {
            $empcode = sprintf('EMP-PHOTO-N%d-%02d', $employees, $number);
            photoIndexEmployee($empcode, ['OFFICE' => "N+1 Office {$employees}"]);
            EmployeePhoto::factory()->create(['empcode' => $empcode]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)
            ->get(route('employees.index', ['office' => "N+1 Office {$employees}", 'per_page' => 100]))
            ->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($countQueries(2))->toBe($countQueries(8));
});

test('filter changes reload only the props they need', function () {
    $admin = photoIndexAdmin();

    photoIndexEmployee('EMP-PHOTO-04', ['REGION' => 'R5', 'OFFICE' => 'R5 Office']);

    $this->actingAs($admin)
        ->get(route('employees.index', ['region' => 'R5']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/index')
            ->has('regions')
            ->has('plantillaStatuses')
            ->reloadOnly(['employees', 'offices', 'filters', 'suggestion'], fn (Assert $reload) => $reload
                ->has('employees.data', 1)
                ->where('employees.data.0.EMPCODE', 'EMP-PHOTO-04')
                ->where('offices', ['R5 Office'])
                ->where('filters.region', 'R5')
                ->missing('regions')
                ->missing('plantillaStatuses')
            )
        );
});
