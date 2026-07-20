<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function justificationTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function justificationTestSetup(): array
{
    $program = Program::create([
        'title' => 'Justification Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->toDateString(),
        'date_end' => now()->addDay()->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '1',
        'hours' => '8',
    ]);

    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => 'EMP-ABSENT-01',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Reyes',
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
    ])->save();

    $participant = Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $employee->EMPCODE,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);

    return [$participant];
}

test('uploading a justification memo attaches it to the participant when marked absent', function () {
    Storage::fake('public');
    $admin = justificationTestAdmin('EMP-JADM-01');
    [$participant] = justificationTestSetup();

    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
        'justification' => UploadedFile::fake()->create('memo.pdf', 100),
    ])->assertSessionDoesntHaveErrors();

    $participant->refresh();
    expect($participant->attendance)->toBe('Absent');
    expect($participant->justification)->not->toBeNull();
    Storage::disk('public')->assertExists($participant->justification->file_path);
});

test('re-submitting absent attendance without a new file keeps the previously uploaded memo', function () {
    Storage::fake('public');
    $admin = justificationTestAdmin('EMP-JADM-02');
    [$participant] = justificationTestSetup();

    // First submission: upload the memo.
    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
        'justification' => UploadedFile::fake()->create('memo.pdf', 100),
    ])->assertSessionDoesntHaveErrors();

    $originalPath = $participant->refresh()->justification->file_path;

    // Second submission: re-confirm "Absent" without picking a new file.
    // This must NOT fail validation and must NOT remove the existing memo.
    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
    ])->assertSessionDoesntHaveErrors();

    $participant->refresh();
    expect($participant->attendance)->toBe('Absent');
    expect($participant->justification)->not->toBeNull();
    expect($participant->justification->file_path)->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
});

test('re-submitting absent attendance with a new file replaces the old memo', function () {
    Storage::fake('public');
    $admin = justificationTestAdmin('EMP-JADM-03');
    [$participant] = justificationTestSetup();

    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
        'justification' => UploadedFile::fake()->create('memo-old.pdf', 100),
    ]);

    $oldPath = $participant->refresh()->justification->file_path;

    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
        'justification' => UploadedFile::fake()->create('memo-new.pdf', 100),
    ])->assertSessionDoesntHaveErrors();

    $participant->refresh();
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($participant->justification->file_path);
    expect($participant->justification->file_path)->not->toBe($oldPath);
});

test('marking absent without any file and no prior memo fails validation', function () {
    $admin = justificationTestAdmin('EMP-JADM-04');
    [$participant] = justificationTestSetup();

    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
    ])->assertSessionHasErrors('justification');
});
