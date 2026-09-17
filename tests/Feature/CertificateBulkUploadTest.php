<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function certBulkTestAdmin(string $empcode): User
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

function certBulkTestEmployee(string $empcode, string $lastname, string $firstname): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => $firstname,
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
}

function certBulkTestBatch(): Batch
{
    $program = Program::create([
        'title' => 'Certificate Bulk Upload Test Program',
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

test('admin can bulk upload certificates for multiple participants at once', function () {
    Storage::fake('public');

    $admin = certBulkTestAdmin('EMP-CB-ADM-01');
    $batch = certBulkTestBatch();

    $juan = certBulkTestEmployee('EMP-CB-01', 'Dela Cruz', 'Juan');
    $maria = certBulkTestEmployee('EMP-CB-02', 'Santos', 'Maria');

    $juanParticipant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $juan->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    $mariaParticipant = Participant::create([
        'sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => $maria->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->post(route('certificates.bulk-store'), [
        'batch_id' => $batch->id,
        'program_code' => $batch->program_code,
        'type' => 'Completion',
        'status' => 'Issued',
        'issued_date' => now()->toDateString(),
        'files' => [
            ['participant_id' => $juanParticipant->id, 'file' => UploadedFile::fake()->create('Juan Dela Cruz.pdf', 100, 'application/pdf')],
            ['participant_id' => $mariaParticipant->id, 'file' => UploadedFile::fake()->create('Maria Santos.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(Certificate::count())->toBe(2);

    $juanCert = Certificate::where('participant_id', $juanParticipant->id)->first();
    expect($juanCert)->not->toBeNull();
    expect($juanCert->type)->toBe('Completion');
    expect($juanCert->status)->toBe('Issued');
    expect($juanCert->empcode)->toBe($juan->EMPCODE);
    expect($juanCert->file_path)->not->toBeNull();
    Storage::disk('public')->assertExists($juanCert->file_path);

    $mariaCert = Certificate::where('participant_id', $mariaParticipant->id)->first();
    expect($mariaCert)->not->toBeNull();
    expect($mariaCert->empcode)->toBe($maria->EMPCODE);
});

test('bulk upload rejects duplicate participant assignments within the same request', function () {
    Storage::fake('public');

    $admin = certBulkTestAdmin('EMP-CB-ADM-02');
    $batch = certBulkTestBatch();
    $juan = certBulkTestEmployee('EMP-CB-03', 'Reyes', 'Pedro');
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $juan->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($admin)->post(route('certificates.bulk-store'), [
        'batch_id' => $batch->id,
        'program_code' => $batch->program_code,
        'type' => 'Completion',
        'files' => [
            ['participant_id' => $participant->id, 'file' => UploadedFile::fake()->create('Pedro Reyes 1.pdf', 100, 'application/pdf')],
            ['participant_id' => $participant->id, 'file' => UploadedFile::fake()->create('Pedro Reyes 2.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertSessionHasErrors();
    expect(Certificate::count())->toBe(0);
});

test('non-admin cannot bulk upload certificates', function () {
    Storage::fake('public');

    $empcode = 'EMP-CB-USER-01';
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'User',
        'FIRSTNAME' => 'Regular',
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
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $batch = certBulkTestBatch();
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    $response = $this->actingAs($user)->post(route('certificates.bulk-store'), [
        'batch_id' => $batch->id,
        'program_code' => $batch->program_code,
        'type' => 'Completion',
        'files' => [
            ['participant_id' => $participant->id, 'file' => UploadedFile::fake()->create('Regular User.pdf', 100, 'application/pdf')],
        ],
    ]);

    $response->assertForbidden();
    expect(Certificate::count())->toBe(0);
});
