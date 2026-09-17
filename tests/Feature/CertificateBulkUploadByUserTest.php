<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function selfCertTestEmployee(string $empcode): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => '',
        'POSITION' => 'Staff',
        'SG' => '8',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function selfCertTestBatch(): Batch
{
    $program = Program::create([
        'title' => 'Self Certificate Bulk Upload Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

test('employee can bulk upload multiple certificate types for themselves in one request', function () {
    Storage::fake('public');

    $empcode = 'EMP-SELF-CB-01';
    selfCertTestEmployee($empcode);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $batch = selfCertTestBatch();
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($user)->post(route('certificates.bulk-upload-by-user', ['batch' => $batch->id]), [
        'files' => [
            ['type' => 'Completion', 'file' => UploadedFile::fake()->create('completion.pdf', 100, 'application/pdf')],
            ['type' => 'Appearance', 'file' => UploadedFile::fake()->create('appearance.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(Certificate::where('empcode', $empcode)->count())->toBe(2);

    $completion = Certificate::where('empcode', $empcode)->where('type', 'Completion')->first();
    expect($completion)->not->toBeNull();
    expect($completion->status)->toBe('Pending');
    expect($completion->file_path)->not->toBeNull();
    Storage::disk('public')->assertExists($completion->file_path);

    $appearance = Certificate::where('empcode', $empcode)->where('type', 'Appearance')->first();
    expect($appearance)->not->toBeNull();
});

test('bulk upload rejects duplicate certificate types within the same request', function () {
    Storage::fake('public');

    $empcode = 'EMP-SELF-CB-02';
    selfCertTestEmployee($empcode);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $batch = selfCertTestBatch();
    Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($user)->post(route('certificates.bulk-upload-by-user', ['batch' => $batch->id]), [
        'files' => [
            ['type' => 'Completion', 'file' => UploadedFile::fake()->create('completion-1.pdf', 100, 'application/pdf')],
            ['type' => 'Completion', 'file' => UploadedFile::fake()->create('completion-2.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertSessionHasErrors();
    expect(Certificate::where('empcode', $empcode)->count())->toBe(0);
});

test('bulk upload silently skips a type that the employee already has a certificate for', function () {
    Storage::fake('public');

    $empcode = 'EMP-SELF-CB-03';
    selfCertTestEmployee($empcode);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $batch = selfCertTestBatch();
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    Certificate::create([
        'participant_id' => $participant->id,
        'batch_id' => $batch->id,
        'program_code' => $batch->program_code,
        'empcode' => $empcode,
        'type' => 'Completion',
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)->post(route('certificates.bulk-upload-by-user', ['batch' => $batch->id]), [
        'files' => [
            ['type' => 'Completion', 'file' => UploadedFile::fake()->create('completion-new.pdf', 100, 'application/pdf')],
            ['type' => 'Appearance', 'file' => UploadedFile::fake()->create('appearance.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertRedirect();
    expect(Certificate::where('empcode', $empcode)->count())->toBe(2);
    expect(Certificate::where('empcode', $empcode)->where('type', 'Appearance')->exists())->toBeTrue();
});

test('an employee cannot bulk upload certificates for a batch they are not enrolled in', function () {
    Storage::fake('public');

    $empcode = 'EMP-SELF-CB-04';
    selfCertTestEmployee($empcode);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $batch = selfCertTestBatch();
    // Walang Participant row para dito — hindi enrolled ang user sa batch na ito.

    $response = $this->actingAs($user)->post(route('certificates.bulk-upload-by-user', ['batch' => $batch->id]), [
        'files' => [
            ['type' => 'Completion', 'file' => UploadedFile::fake()->create('completion.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertNotFound();
    expect(Certificate::count())->toBe(0);
});
