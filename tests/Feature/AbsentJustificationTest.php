<?php

use App\Models\AbsentJustification;
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

// ── Participant self-service justification (My Programs) ───────────────────

function selfJustificationUser(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
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

    return User::factory()->create(['empcode' => $empcode]);
}

function selfJustificationParticipant(string $empcode, string $attendance = 'Pending'): Participant
{
    $program = Program::create([
        'title' => 'Self Justification Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Open',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    return Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $empcode,
        'attendance' => $attendance,
        'hours' => 0,
        'added_by' => 'system',
    ]);
}

test('a participant can upload a justification without any prior requirement submission and is marked absent', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-01');
    $participant = selfJustificationParticipant($user->empcode);

    expect($participant->attendance)->toBe('Pending');
    expect(AbsentJustification::where('participant_id', $participant->id)->exists())->toBeFalse();

    $response = $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('justification.pdf', 100, 'application/pdf')],
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $participant->refresh();
    expect($participant->attendance)->toBe('Absent');

    $justification = AbsentJustification::where('participant_id', $participant->id)->first();
    expect($justification)->not->toBeNull();
    Storage::disk('public')->assertExists($justification->file_path);
});

test('a non-PDF self-service justification file is rejected', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-02');
    $participant = selfJustificationParticipant($user->empcode);

    $response = $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('justification.docx', 100, 'application/msword')],
    );

    $response->assertSessionHasErrors('file');
    expect($participant->fresh()->attendance)->toBe('Pending');
});

test('uploading a self-service justification again replaces the previous file', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-03');
    $participant = selfJustificationParticipant($user->empcode);

    $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf')],
    );
    $firstPath = AbsentJustification::where('participant_id', $participant->id)->first()->file_path;

    $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf')],
    );

    expect(AbsentJustification::where('participant_id', $participant->id)->count())->toBe(1);
    Storage::disk('public')->assertMissing($firstPath);
});

test('a participant can remove their self-service justification and attendance reverts to pending', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-04');
    $participant = selfJustificationParticipant($user->empcode);

    $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('justification.pdf', 100, 'application/pdf')],
    );
    $path = AbsentJustification::where('participant_id', $participant->id)->first()->file_path;

    $response = $this->actingAs($user)->delete(
        route('programs.my-progress.justification.destroy', $participant->batch_id)
    );

    $response->assertRedirect();
    expect(AbsentJustification::where('participant_id', $participant->id)->exists())->toBeFalse();
    expect($participant->fresh()->attendance)->toBe('Pending');
    Storage::disk('public')->assertMissing($path);
});

test('a user cannot submit a self-service justification for a batch they are not enrolled in', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-05');
    $otherParticipant = selfJustificationParticipant('EMP-JUST-06');

    $response = $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $otherParticipant->batch_id),
        ['file' => UploadedFile::fake()->create('justification.pdf', 100, 'application/pdf')],
    );

    $response->assertNotFound();
});

test('the enrolled program detail page exposes the justification for the participant', function () {
    Storage::fake('public');

    $user = selfJustificationUser('EMP-JUST-07');
    $participant = selfJustificationParticipant($user->empcode);

    $this->actingAs($user)->post(
        route('programs.my-progress.justification.submit', $participant->batch_id),
        ['file' => UploadedFile::fake()->create('justification.pdf', 100, 'application/pdf')],
    );

    $response = $this->actingAs($user)->get(route('programs.my-progress', $participant->batch_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('MyPrograms/Show')
        ->where('program.attendance', 'Absent')
        ->where('program.justification.id', fn ($id) => $id !== null)
    );
});
