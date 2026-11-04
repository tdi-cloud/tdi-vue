<?php

use App\Models\Employee;
use App\Models\Participant;
use App\Models\PendingNotification;
use App\Models\ProgramRegistration;
use App\Models\ProvisionalEmployee;
use App\Models\RegistrationForm;
use App\Models\User;
use App\Support\ProvisionalEmployeeSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * A holding record together with the employees row the app inserted for it.
 */
function holdingRecord(array $overrides = []): ProvisionalEmployee
{
    $record = ProvisionalEmployee::factory()->create($overrides);
    $record->insertEmployeeRow();

    return $record->refresh();
}

/**
 * A row as HR's monthly import would add it (no app timestamps).
 */
function officialEmployeeRow(string $empcode, string $lastname = 'Official'): void
{
    DB::table('employees')->insert([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'HR Division',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'From HR',
        'MI' => '',
        'POSITION' => 'Officer',
        'SG' => '11',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'TDI',
        'LOCATION' => '',
        'SECTION' => '',
        'UNIT' => '',
    ]);
}

/**
 * The monthly update: empty the employees table, then import HR's list.
 *
 * @param  list<string>  $officialEmpcodes
 */
function monthlyReimport(array $officialEmpcodes): void
{
    DB::table('employees')->truncate();

    foreach ($officialEmpcodes as $empcode) {
        officialEmployeeRow($empcode);
    }
}

test('someone HR has added next to the app\'s row is merged and their duplicate row removed', function () {
    $record = holdingRecord(['empcode' => 'NEW-SYNC-01']);
    officialEmployeeRow('NEW-SYNC-01');

    $result = app(ProvisionalEmployeeSync::class)->check($record);

    expect($result)->toBe(ProvisionalEmployeeSync::MERGED)
        ->and(Employee::where('EMPCODE', 'NEW-SYNC-01')->count())->toBe(1)
        ->and(Employee::where('EMPCODE', 'NEW-SYNC-01')->value('LASTNAME'))->toBe('Official')
        ->and(ProvisionalEmployee::count())->toBe(0);

    $merged = ProvisionalEmployee::onlyTrashed()->sole();
    expect($merged->status)->toBe(ProvisionalEmployee::STATUS_MERGED)
        ->and($merged->merged_at)->not->toBeNull();
});

test('after an empty-and-re-import that includes the person, they are merged', function () {
    $record = holdingRecord(['empcode' => 'NEW-SYNC-01']);

    // Ids restart after the truncate, so the official row reuses the app row's id.
    monthlyReimport(['NEW-SYNC-01']);
    expect(Employee::where('EMPCODE', 'NEW-SYNC-01')->value('id'))->toBe($record->employee_row_id);

    expect(app(ProvisionalEmployeeSync::class)->check($record))->toBe(ProvisionalEmployeeSync::MERGED)
        ->and(Employee::where('EMPCODE', 'NEW-SYNC-01')->value('LASTNAME'))->toBe('Official');
});

test('after an empty-and-re-import without the person, their row is restored from the holding list', function () {
    $record = holdingRecord(['empcode' => 'NEW-SYNC-01', 'lastname' => 'Santos']);
    monthlyReimport(['OTHER-0001']);

    // The re-imported row has the old id but belongs to someone else.
    expect(Employee::find($record->employee_row_id)?->EMPCODE)->toBe('OTHER-0001');

    expect(app(ProvisionalEmployeeSync::class)->check($record))->toBe(ProvisionalEmployeeSync::RESTORED);

    $restored = Employee::where('EMPCODE', 'NEW-SYNC-01')->sole();
    expect($restored->LASTNAME)->toBe('Santos')
        ->and($record->refresh())
        ->status->toBe(ProvisionalEmployee::STATUS_WAITING)
        ->employee_row_id->toBe($restored->id)
        ->and($record->insertedEmployeeRow()?->id)->toBe($restored->id)
        ->and(Employee::where('EMPCODE', 'OTHER-0001')->exists())->toBeTrue();
});

test('someone still only in the app\'s row keeps waiting, and only the last checked time changes', function () {
    $record = holdingRecord(['empcode' => 'NEW-SYNC-01']);
    $rowId = $record->employee_row_id;

    $this->travel(2)->hours();

    expect(app(ProvisionalEmployeeSync::class)->check($record))->toBe(ProvisionalEmployeeSync::WAITING)
        ->and($record->refresh())
        ->status->toBe(ProvisionalEmployee::STATUS_WAITING)
        ->employee_row_id->toBe($rowId)
        ->and($record->last_checked_at->isToday())->toBeTrue()
        ->and(Employee::count())->toBe(1);
});

test('running the check twice gives the same result', function () {
    holdingRecord(['empcode' => 'NEW-SYNC-01']);
    holdingRecord(['empcode' => 'NEW-SYNC-02']);
    holdingRecord(['empcode' => 'NEW-SYNC-03']);
    monthlyReimport(['NEW-SYNC-02']);

    $first = app(ProvisionalEmployeeSync::class)->run();
    $second = app(ProvisionalEmployeeSync::class)->run();

    expect($first)->toBe(['merged' => 1, 'restored' => 2, 'waiting' => 0])
        ->and($second)->toBe(['merged' => 0, 'restored' => 0, 'waiting' => 2])
        ->and(Employee::pluck('EMPCODE')->sort()->values()->all())->toBe(['NEW-SYNC-01', 'NEW-SYNC-02', 'NEW-SYNC-03']);
});

test('the daily command runs the check and is scheduled', function () {
    holdingRecord(['empcode' => 'NEW-SYNC-01']);
    officialEmployeeRow('NEW-SYNC-01');

    $this->artisan('employees:sync-provisional')
        ->expectsOutputToContain('Merged into the official list: 1')
        ->assertSuccessful();

    Artisan::call('schedule:list');
    expect(Artisan::output())->toContain('employees:sync-provisional');
});

test('only super admins can open the Employees from Registration page', function () {
    holdingRecord(['empcode' => 'NEW-SYNC-01']);
    ProvisionalEmployee::factory()->merged()->create();

    $this->actingAs(User::factory()->create(['access' => 'admin']))
        ->get(route('employees-from-registration.index'))
        ->assertForbidden();

    $superAdmin = User::factory()->create(['access' => 'superadmin']);

    $this->actingAs($superAdmin)
        ->get(route('employees-from-registration.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('EmployeesFromRegistration/index')
            ->where('counts', ['waiting' => 1, 'merged' => 1])
            ->has('records.data', 1)
            ->where('records.data.0.empcode', 'NEW-SYNC-01')
            ->where('records.data.0.in_employees_table', true)
        );

    $this->actingAs($superAdmin)
        ->get(route('employees-from-registration.index', ['status' => 'merged']))
        ->assertInertia(fn ($page) => $page->has('records.data', 1)->where('records.data.0.status', 'merged'));
});

test('"Check now" runs the same check from the page', function () {
    holdingRecord(['empcode' => 'NEW-SYNC-01']);
    officialEmployeeRow('NEW-SYNC-01');

    $this->actingAs(User::factory()->create(['access' => 'superadmin']))
        ->post(route('employees-from-registration.sync'))
        ->assertSessionHas('success');

    expect(ProvisionalEmployee::count())->toBe(0);
});

test('fixing a mistyped employee code updates it everywhere the person appears', function () {
    $form = RegistrationForm::factory()->create();
    $user = User::factory()->create(['empcode' => 'TYPO-0001', 'access' => 'guest']);
    $registration = ProgramRegistration::factory()->forForm($form)->approved()->create(['empcode' => 'TYPO-0001', 'user_id' => $user->id]);
    $record = holdingRecord(['empcode' => 'TYPO-0001', 'user_id' => $user->id, 'program_registration_id' => $registration->id]);
    Participant::create(['batch_id' => $form->batch_id, 'empcode' => 'TYPO-0001', 'attendance' => 'Pending', 'added_by' => 'test']);
    PendingNotification::create(['empcode' => 'TYPO-0001', 'type' => 'test', 'data' => []]);

    $this->actingAs(User::factory()->create(['access' => 'superadmin']))
        ->put(route('employees-from-registration.update', $record), array_merge($record->only([
            'lastname', 'firstname', 'mi', 'position', 'sg', 'plantilla_status', 'sex', 'region', 'office_division',
        ]), ['empcode' => 'FIXED-0001']))
        ->assertSessionHasNoErrors();

    expect($record->refresh()->empcode)->toBe('FIXED-0001')
        ->and($record->insertedEmployeeRow()?->EMPCODE)->toBe('FIXED-0001')
        ->and($user->refresh()->empcode)->toBe('FIXED-0001')
        ->and($registration->refresh()->empcode)->toBe('FIXED-0001')
        ->and(Participant::sole()->empcode)->toBe('FIXED-0001')
        ->and(PendingNotification::sole()->empcode)->toBe('FIXED-0001')
        ->and(Employee::where('EMPCODE', 'TYPO-0001')->exists())->toBeFalse();
});

test('fixing the code to one already in HR\'s list merges the person right away', function () {
    $record = holdingRecord(['empcode' => 'TYPO-0001']);
    officialEmployeeRow('REAL-0001');

    $this->actingAs(User::factory()->create(['access' => 'superadmin']))
        ->put(route('employees-from-registration.update', $record), array_merge($record->only([
            'lastname', 'firstname', 'position', 'plantilla_status', 'sex', 'region', 'office_division',
        ]), ['empcode' => 'REAL-0001']))
        ->assertSessionHasNoErrors();

    expect(ProvisionalEmployee::count())->toBe(0)
        ->and(Employee::where('EMPCODE', 'REAL-0001')->count())->toBe(1)
        ->and(Employee::where('EMPCODE', 'TYPO-0001')->exists())->toBeFalse();
});

test('a code that already belongs to someone else\'s account is refused', function () {
    $record = holdingRecord(['empcode' => 'TYPO-0001']);
    User::factory()->create(['empcode' => 'TAKEN-0001']);

    $this->actingAs(User::factory()->create(['access' => 'superadmin']))
        ->put(route('employees-from-registration.update', $record), array_merge($record->only([
            'lastname', 'firstname', 'position', 'plantilla_status', 'sex', 'region', 'office_division',
        ]), ['empcode' => 'TAKEN-0001']))
        ->assertSessionHasErrors('empcode');

    expect($record->refresh()->empcode)->toBe('TYPO-0001');
});
