<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeRequirement;
use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function adminAddTestAdmin(string $empcode): User
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

function adminAddTestProgram(): ForeignProgram
{
    return ForeignProgram::create([
        'program_title' => 'Admin Add Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);
}

function adminAddTestBaseFields(): array
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

test('admin can add a participant without any requirement files', function () {
    $admin = adminAddTestAdmin('EMP-AADD-01');
    $program = adminAddTestProgram();

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.store', $program),
        adminAddTestBaseFields()
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(ForeignNominee::where('foreign_program_id', $program->id)->count())->toBe(1);

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    expect($nominee->foreign_sponsor_config_id)->toBeNull();
    expect($nominee->status)->toBe('for_interview');
});

test('admin can add a participant without age, email, or contact number', function () {
    $admin = adminAddTestAdmin('EMP-AADD-05');
    $program = adminAddTestProgram();

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.store', $program),
        [
            'firstname' => 'Juan',
            'surname' => 'Dela Cruz',
            'sex' => 'male',
            'position' => 'Test Position',
            'agency' => 'Test Agency',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    expect($nominee->age)->toBeNull();
    expect($nominee->email)->toBeNull();
    expect($nominee->contact_number)->toBeNull();
});

test('admin can add a participant and attach optional requirement files', function () {
    Storage::fake('public');

    $admin = adminAddTestAdmin('EMP-AADD-02');
    $program = adminAddTestProgram();

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica-admin-add',
        'form_title' => 'JICA Nomination Form',
        'is_active' => true,
    ]);

    $requirement = ForeignNomineeRequirement::create([
        'foreign_sponsor_config_id' => $config->id,
        'sort_order' => 1,
        'question' => 'Upload your CV',
        'file_required' => true,
    ]);

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.store', $program),
        [
            ...adminAddTestBaseFields(),
            'foreign_sponsor_config_id' => $config->id,
            "requirement_{$requirement->id}" => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    expect($nominee->foreign_sponsor_config_id)->toBe($config->id);
    expect($nominee->submissions)->toHaveCount(1);
    Storage::disk('public')->assertExists($nominee->submissions->first()->file_path);
});

test('admin can add a participant to a config with required requirements while skipping the files', function () {
    $admin = adminAddTestAdmin('EMP-AADD-03');
    $program = adminAddTestProgram();

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica-admin-add-skip',
        'form_title' => 'JICA Nomination Form',
        'is_active' => true,
    ]);

    ForeignNomineeRequirement::create([
        'foreign_sponsor_config_id' => $config->id,
        'sort_order' => 1,
        'question' => 'Upload your CV',
        'file_required' => true,
    ]);

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.store', $program),
        [
            ...adminAddTestBaseFields(),
            'foreign_sponsor_config_id' => $config->id,
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $nominee = ForeignNominee::where('foreign_program_id', $program->id)->first();
    expect($nominee)->not->toBeNull();
    expect($nominee->submissions)->toHaveCount(0);
});

test('non-admin users cannot add a participant', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-AADD-04',
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

    $program = adminAddTestProgram();

    $response = $this->actingAs($user)->post(
        route('foreign-nominees.store', $program),
        adminAddTestBaseFields()
    );

    $response->assertForbidden();
});
