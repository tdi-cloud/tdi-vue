<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeRequirement;
use App\Models\ForeignNomineeSubmission;
use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function submissionReplaceTestAdmin(string $empcode): User
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

function submissionReplaceTestSetup(): ForeignNomineeSubmission
{
    $program = ForeignProgram::create([
        'program_title' => 'Submission Replace Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica',
        'form_title' => 'JICA Nomination Form',
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
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
    ]);

    return ForeignNomineeSubmission::create([
        'foreign_nominee_id' => $nominee->id,
        'foreign_nominee_requirement_id' => $requirement->id,
        'file_path' => "nominees/requirements/{$nominee->id}/old-cv.pdf",
    ]);
}

function accomplishedFormTestNominee(): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => 'Accomplished Form Replace Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica-form',
        'form_title' => 'JICA Nomination Form',
        'is_active' => true,
    ]);

    return ForeignNominee::create([
        'foreign_program_id' => $program->id,
        'foreign_sponsor_config_id' => $config->id,
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
        'accomplished_form_path' => 'nominees/accomplished/old-form.pdf',
    ]);
}

test('admin can replace a nominee submitted document', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-01');
    $submission = submissionReplaceTestSetup();

    $oldPath = "nominees/requirements/{$submission->foreign_nominee_id}/old-cv.pdf";
    Storage::disk('public')->put($oldPath, 'old content');
    $submission->update(['file_path' => $oldPath]);

    $response = $this->actingAs($admin)->post(route('foreign-nominee-submissions.replace', $submission), [
        'file' => UploadedFile::fake()->create('new-cv.pdf', 100, 'application/pdf'),
    ]);

    $response->assertOk();

    $submission->refresh();
    expect($submission->file_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($submission->file_path);
});

test('replacing a submission requires a file', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-02');
    $submission = submissionReplaceTestSetup();

    $response = $this->actingAs($admin)->post(route('foreign-nominee-submissions.replace', $submission), []);

    $response->assertSessionHasErrors('file');
});

test('non-admin users cannot replace a nominee submitted document', function () {
    Storage::fake('public');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-SREP-03',
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

    $submission = submissionReplaceTestSetup();

    $response = $this->actingAs($user)->post(route('foreign-nominee-submissions.replace', $submission), [
        'file' => UploadedFile::fake()->create('new-cv.pdf', 100, 'application/pdf'),
    ]);

    $response->assertForbidden();
});

test('admin can replace a nominee accomplished application form', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-04');
    $nominee = accomplishedFormTestNominee();

    $oldPath = 'nominees/accomplished/old-form.pdf';
    Storage::disk('public')->put($oldPath, 'old content');

    $response = $this->actingAs($admin)->post(route('foreign-nominees.accomplished-form.replace', $nominee), [
        'file' => UploadedFile::fake()->create('new-form.pdf', 100, 'application/pdf'),
    ]);

    $response->assertOk();

    $nominee->refresh();
    expect($nominee->accomplished_form_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($nominee->accomplished_form_path);
});

test('replacing an accomplished form requires a file', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-05');
    $nominee = accomplishedFormTestNominee();

    $response = $this->actingAs($admin)->post(route('foreign-nominees.accomplished-form.replace', $nominee), []);

    $response->assertSessionHasErrors('file');
});

test('admin can upload a document for a requirement not yet submitted', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-06');
    $program = ForeignProgram::create([
        'program_title' => 'Missing Requirement Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);

    $config = ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica-missing-req',
        'form_title' => 'JICA Nomination Form',
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
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
    ]);

    expect($nominee->submissions()->count())->toBe(0);

    $response = $this->actingAs($admin)->post(
        route('foreign-nominee-submissions.store', [$nominee, $requirement]),
        ['file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')]
    );

    $response->assertOk();

    $nominee->refresh();
    expect($nominee->submissions)->toHaveCount(1);
    Storage::disk('public')->assertExists($nominee->submissions->first()->file_path);
});

test('uploading a document for an already-submitted requirement replaces the old file', function () {
    Storage::fake('public');

    $admin = submissionReplaceTestAdmin('EMP-SREP-07');
    $submission = submissionReplaceTestSetup();

    $oldPath = "nominees/requirements/{$submission->foreign_nominee_id}/old-cv.pdf";
    Storage::disk('public')->put($oldPath, 'old content');
    $submission->update(['file_path' => $oldPath]);

    $response = $this->actingAs($admin)->post(
        route('foreign-nominee-submissions.store', [$submission->foreign_nominee_id, $submission->foreign_nominee_requirement_id]),
        ['file' => UploadedFile::fake()->create('new-cv.pdf', 100, 'application/pdf')]
    );

    $response->assertOk();

    $submission->refresh();
    expect($submission->file_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($submission->file_path);
});

test('non-admin users cannot upload a missing requirement document', function () {
    Storage::fake('public');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-SREP-08',
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

    $submission = submissionReplaceTestSetup();

    $response = $this->actingAs($user)->post(
        route('foreign-nominee-submissions.store', [$submission->foreign_nominee_id, $submission->foreign_nominee_requirement_id]),
        ['file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')]
    );

    $response->assertForbidden();
});
