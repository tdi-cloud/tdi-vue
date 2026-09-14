<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeRequirement;
use App\Models\ForeignNomineeSubmission;
use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;
use App\Models\User;

function nominationHistoryTestAdmin(string $empcode): User
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

function nominationHistoryTestNominee(array $overrides = []): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => $overrides['program_title'] ?? 'History Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => $overrides['organizing_sponsor'] ?? 'JICA',
        'status' => 'for_dissemination',
    ]);

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => $overrides['organizing_sponsor'] ?? 'JICA',
        'slug' => 'history-'.uniqid(),
        'form_title' => 'History Test Form',
        'is_active' => true,
    ]);

    $requirement = ForeignNomineeRequirement::create([
        'foreign_sponsor_config_id' => $config->id,
        'sort_order' => 1,
        'question' => 'Upload your CV',
        'file_required' => true,
    ]);

    $nominee = ForeignNominee::create([
        'foreign_program_id' => $program->id,
        'foreign_sponsor_config_id' => $config->id,
        'firstname' => $overrides['firstname'] ?? 'Juan',
        'surname' => $overrides['surname'] ?? 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
    ]);

    if ($overrides['created_at'] ?? null) {
        $nominee->forceFill(['created_at' => $overrides['created_at']])->save();
    }

    if ($overrides['with_submission'] ?? false) {
        ForeignNomineeSubmission::create([
            'foreign_nominee_id' => $nominee->id,
            'foreign_nominee_requirement_id' => $requirement->id,
            'file_path' => "nominees/requirements/{$nominee->id}/cv.pdf",
        ]);
    }

    return $nominee;
}

test('nomination history endpoint returns nominees ordered from latest to oldest', function () {
    $admin = nominationHistoryTestAdmin('EMP-NH-01');

    nominationHistoryTestNominee(['surname' => 'Oldest', 'created_at' => now()->subDays(5)]);
    nominationHistoryTestNominee(['surname' => 'Newest', 'created_at' => now()]);
    nominationHistoryTestNominee(['surname' => 'Middle', 'created_at' => now()->subDays(2)]);

    $response = $this->actingAs($admin)->getJson(route('foreign-programs.nomination-history'));

    $response->assertOk();
    $names = collect($response->json('nominees.data'))->pluck('name');

    expect($names->values()->all())->toBe([
        'Juan  Newest',
        'Juan  Middle',
        'Juan  Oldest',
    ]);

    $first = collect($response->json('nominees.data'))->first();
    expect($first['program_id'])->not->toBeNull();
});

test('nomination history endpoint reports requirement submission counts', function () {
    $admin = nominationHistoryTestAdmin('EMP-NH-02');

    nominationHistoryTestNominee(['surname' => 'Complete', 'with_submission' => true]);
    nominationHistoryTestNominee(['surname' => 'Incomplete', 'with_submission' => false]);

    $response = $this->actingAs($admin)->getJson(route('foreign-programs.nomination-history'));

    $response->assertOk();
    $rows = collect($response->json('nominees.data'));

    $complete = $rows->firstWhere('name', 'Juan  Complete');
    $incomplete = $rows->firstWhere('name', 'Juan  Incomplete');

    expect($complete['requirements_total'])->toBe(1)
        ->and($complete['requirements_submitted'])->toBe(1)
        ->and($complete['submissions'])->toHaveCount(1)
        ->and($incomplete['requirements_total'])->toBe(1)
        ->and($incomplete['requirements_submitted'])->toBe(0)
        ->and($incomplete['submissions'])->toHaveCount(0);
});

test('nomination history endpoint filters by search and organizing sponsor', function () {
    $admin = nominationHistoryTestAdmin('EMP-NH-03');

    nominationHistoryTestNominee(['surname' => 'Jica Nominee', 'organizing_sponsor' => 'JICA']);
    nominationHistoryTestNominee(['surname' => 'Kfw Nominee', 'organizing_sponsor' => 'KFW']);

    $response = $this->actingAs($admin)->getJson(route('foreign-programs.nomination-history', [
        'organizing_sponsor' => 'KFW',
    ]));

    $response->assertOk();
    $names = collect($response->json('nominees.data'))->pluck('name');
    expect($names)->toContain('Juan  Kfw Nominee')->and($names)->not->toContain('Juan  Jica Nominee');

    $searchResponse = $this->actingAs($admin)->getJson(route('foreign-programs.nomination-history', [
        'search' => 'Jica Nominee',
    ]));

    $searchResponse->assertOk();
    $searchNames = collect($searchResponse->json('nominees.data'))->pluck('name');
    expect($searchNames)->toContain('Juan  Jica Nominee')->and($searchNames)->not->toContain('Juan  Kfw Nominee');
});

test('non-admin users cannot access the nomination history endpoint', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-NH-USER',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Reyes',
        'FIRSTNAME' => 'Maria',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->getJson(route('foreign-programs.nomination-history'))->assertForbidden();
});
