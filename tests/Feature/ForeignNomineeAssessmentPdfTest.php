<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\ForeignProgramNhrdcSignature;
use App\Models\NhrdcMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pdfTestAdmin(string $empcode): User
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

function pdfTestNominee(): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => 'PDF Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(5)->toDateString(),
        'slots' => 1,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);

    return ForeignNominee::create([
        'foreign_program_id' => $program->id,
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
    ]);
}

function pdfTestNhrdcMember(string $empcode, string $lastname, string $firstname): NhrdcMember
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'NHRDC Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => $firstname,
        'MI' => 'R',
        'POSITION' => 'Deputy Director General',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'CO',
        'OFFICE' => 'NHRDC Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return NhrdcMember::create(['empcode' => $empcode, 'sort_order' => NhrdcMember::nextSortOrder()]);
}

test('admin can generate the assessment sheet PDF for an NHRDC member with no ratings yet', function () {
    $admin = pdfTestAdmin('EMP-PDF-01');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-01', 'Glino', 'Galo');

    $response = $this->actingAs($admin)->get(
        route('foreign-programs.nhrdc-assessment-pdf', [$nominee->foreign_program_id, $nhrdc->id])
    );

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin can generate the PDF once requirements and this NHRDC\'s interview rating exist', function () {
    $admin = pdfTestAdmin('EMP-PDF-02');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-02', 'Orozco', 'Juliet');

    ForeignNomineeAssessment::create([
        'foreign_nominee_id' => $nominee->id,
        'need_for_training' => 20,
        'relevance_to_duties' => 30,
        'meets_donor_requirements' => 10,
        'completion_of_documents' => 10,
    ]);
    ForeignNomineeInterviewRating::create([
        'foreign_nominee_id' => $nominee->id,
        'nhrdc_empcode' => $nhrdc->empcode,
        'nhrdc_name' => 'Juliet Orozco',
        'nhrdc_position' => 'Chairperson, HRDC',
        'communication_skills' => 5,
        'alertness' => 5,
        'judgement' => 5,
        'self_confidence' => 5,
        'emotional_stability' => 5,
        'appearance' => 5,
    ]);

    $response = $this->actingAs($admin)->get(
        route('foreign-programs.nhrdc-assessment-pdf', [$nominee->foreign_program_id, $nhrdc->id])
    );

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('non-admin users cannot generate the NHRDC assessment PDF', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-PDF-03',
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
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-03', 'Santos', 'Pedro');

    $this->actingAs($user)
        ->get(route('foreign-programs.nhrdc-assessment-pdf', [$nominee->foreign_program_id, $nhrdc->id]))
        ->assertForbidden();
});

test('admin can upload a signed copy for an NHRDC member', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-04');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-04', 'Cruz', 'Liza');

    $response = $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    );

    $response->assertOk();

    $signature = ForeignProgramNhrdcSignature::where('foreign_program_id', $nominee->foreign_program_id)
        ->where('nhrdc_empcode', $nhrdc->empcode)
        ->first();

    expect($signature)->not->toBeNull();
    expect($signature->uploaded_by)->toBe($admin->id);
    Storage::disk('local')->assertExists($signature->signed_copy_path);
});

test('uploading a signed copy requires a PDF file', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-05');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-05', 'Garcia', 'Nora');

    $response = $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('signed.txt', 10, 'text/plain')],
    );

    $response->assertSessionHasErrors('file');
});

test('re-uploading a signed copy replaces the previous file', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-06');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-06', 'Bautista', 'Carlos');

    $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf')],
    );
    $firstPath = ForeignProgramNhrdcSignature::where('nhrdc_empcode', $nhrdc->empcode)->first()->signed_copy_path;

    $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf')],
    );

    expect(ForeignProgramNhrdcSignature::where('nhrdc_empcode', $nhrdc->empcode)->count())->toBe(1);
    Storage::disk('local')->assertExists($firstPath);
});

test('admin can download an uploaded signed copy', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-07');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-07', 'Torres', 'Isabel');

    $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    );

    $response = $this->actingAs($admin)->get(
        route('foreign-programs.nhrdc-signed-copy.download', [$nominee->foreign_program_id, $nhrdc->id])
    );

    $response->assertOk();
});

test('downloading a signed copy that was never uploaded returns 404', function () {
    $admin = pdfTestAdmin('EMP-PDF-08');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-08', 'Villanueva', 'Marco');

    $this->actingAs($admin)
        ->get(route('foreign-programs.nhrdc-signed-copy.download', [$nominee->foreign_program_id, $nhrdc->id]))
        ->assertNotFound();
});

test('admin can delete an uploaded signed copy', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-09');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-09', 'Fernandez', 'Miguel');

    $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    );
    $path = ForeignProgramNhrdcSignature::where('nhrdc_empcode', $nhrdc->empcode)->first()->signed_copy_path;

    $response = $this->actingAs($admin)->delete(
        route('foreign-programs.nhrdc-signed-copy.destroy', [$nominee->foreign_program_id, $nhrdc->id])
    );

    $response->assertOk();
    Storage::disk('local')->assertMissing($path);
    expect(ForeignProgramNhrdcSignature::where('nhrdc_empcode', $nhrdc->empcode)->exists())->toBeFalse();
});

test('the assessment sheet index exposes which NHRDC members have an uploaded signed copy', function () {
    Storage::fake('local');

    $admin = pdfTestAdmin('EMP-PDF-10');
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-10', 'Ramos', 'Elena');

    $this->actingAs($admin)->post(
        route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
        ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    );

    $response = $this->actingAs($admin)->get(route('foreign-programs.assessment', $nominee->foreign_program_id));

    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/Assessment')
        ->has("nhrdcSignatures.{$nhrdc->empcode}")
    );
});

test('non-admin users cannot upload, download, or delete a signed copy', function () {
    Storage::fake('local');

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-PDF-11',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Lopez',
        'FIRSTNAME' => 'Paolo',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $nominee = pdfTestNominee();
    $nhrdc = pdfTestNhrdcMember('EMP-PDF-NHRDC-11', 'Manalo', 'Beatriz');

    $this->actingAs($user)
        ->post(
            route('foreign-programs.nhrdc-signed-copy.upload', [$nominee->foreign_program_id, $nhrdc->id]),
            ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
        )
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('foreign-programs.nhrdc-signed-copy.download', [$nominee->foreign_program_id, $nhrdc->id]))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('foreign-programs.nhrdc-signed-copy.destroy', [$nominee->foreign_program_id, $nhrdc->id]))
        ->assertForbidden();
});
