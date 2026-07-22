<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignProgram;
use App\Models\User;

function statusTestAdmin(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Admin',
        'FIRSTNAME' => 'Ana',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function statusTestProgram(string $status): ForeignProgram
{
    return ForeignProgram::create([
        'program_title' => 'Status Auto-Update Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(5)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => $status,
    ]);
}

function statusTestNomineePayload(): array
{
    return [
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
    ];
}

test('adding the first nominee moves a "waiting for nominees" program to "waiting for result"', function () {
    $admin = statusTestAdmin('EMP-STAT-01');
    $program = statusTestProgram('waiting_for_nominees');

    $this->actingAs($admin)->post(route('foreign-nominees.store', $program), statusTestNomineePayload());

    expect($program->fresh()->status)->toBe('waiting_for_result');
});

test('adding a nominee does not touch the status when it is not "waiting for nominees"', function () {
    $admin = statusTestAdmin('EMP-STAT-02');
    $program = statusTestProgram('for_dissemination');

    $this->actingAs($admin)->post(route('foreign-nominees.store', $program), statusTestNomineePayload());

    expect($program->fresh()->status)->toBe('for_dissemination');
});

test('deleting the last nominee reverts a "waiting for result" program back to "waiting for nominees"', function () {
    $admin = statusTestAdmin('EMP-STAT-03');
    $program = statusTestProgram('waiting_for_nominees');

    $this->actingAs($admin)->post(route('foreign-nominees.store', $program), statusTestNomineePayload());
    expect($program->fresh()->status)->toBe('waiting_for_result');

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    $this->actingAs($admin)->delete(route('foreign-nominees.destroy', $nominee));

    expect($program->fresh()->status)->toBe('waiting_for_nominees');
});

test('deleting one of several nominees does not revert the status', function () {
    $admin = statusTestAdmin('EMP-STAT-04');
    $program = statusTestProgram('waiting_for_nominees');

    $this->actingAs($admin)->post(route('foreign-nominees.store', $program), statusTestNomineePayload());
    $this->actingAs($admin)->post(route('foreign-nominees.store', $program), array_merge(statusTestNomineePayload(), ['firstname' => 'Maria']));
    expect($program->fresh()->status)->toBe('waiting_for_result');

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    $this->actingAs($admin)->delete(route('foreign-nominees.destroy', $nominee));

    expect($program->fresh()->status)->toBe('waiting_for_result');
    expect(ForeignNominee::where('foreign_program_id', $program->id)->count())->toBe(1);
});

test('deleting the last nominee does not revert a status other than "waiting for result"', function () {
    $admin = statusTestAdmin('EMP-STAT-05');
    $program = statusTestProgram('ongoing');

    $nominee = $program->nominees()->create(statusTestNomineePayload());
    expect($program->fresh()->status)->toBe('ongoing');

    $this->actingAs($admin)->delete(route('foreign-nominees.destroy', $nominee));

    expect($program->fresh()->status)->toBe('ongoing');
});
