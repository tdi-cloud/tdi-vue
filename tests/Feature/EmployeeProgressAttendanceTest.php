<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The Employee Training Profile modal sets a program's attendance through the
 * same participants.attendance route as the program page, then reloads the
 * employee's progress to show the new status, hours and justification memo.
 *
 * @return array{admin: User, participant: Participant}
 */
function profileAttendanceSetup(): array
{
    Employee::forceCreate([
        'EMPCODE' => 'EMP-PAT-01',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Andres',
        'FIRSTNAME' => 'Irvin',
        'MI' => 'A',
        'POSITION' => 'Instructor I',
        'SG' => '13',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'R2',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    $program = Program::create([
        'title' => 'Profile Attendance Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => 'EMP-PAT-CREATOR',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Done',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $participant = Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => 'EMP-PAT-01',
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);

    $admin = User::factory()->create(['empcode' => 'EMP-PAT-ADMIN', 'access' => 'admin', 'name' => 'Ana Admin']);

    return compact('admin', 'participant');
}

/**
 * @return array<string, mixed>
 */
function profileAttendanceProgram(User $admin): array
{
    return test()->actingAs($admin)
        ->getJson(route('employees.progress', ['empcode' => 'EMP-PAT-01']))
        ->assertSuccessful()
        ->json('enrolled_programs.0');
}

test('the training profile exposes the batch hours and no memo for each program', function () {
    ['admin' => $admin] = profileAttendanceSetup();

    $program = profileAttendanceProgram($admin);

    expect($program['batch_hours'])->toEqual(16)
        ->and($program['attendance'])->toBe('Pending')
        ->and($program['justification'])->toBeNull();
});

test('an admin can mark an employee complete from the training profile', function () {
    ['admin' => $admin, 'participant' => $participant] = profileAttendanceSetup();

    $this->actingAs($admin)
        ->post(route('participants.attendance', $participant), ['attendance' => 'Complete', 'hours' => 12.5])
        ->assertSessionHasNoErrors();

    $program = profileAttendanceProgram($admin);

    expect($program['attendance'])->toBe('Complete')
        ->and((float) $program['hours'])->toBe(12.5);
});

test('marking absent from the training profile shows the memo and who uploaded it', function () {
    Storage::fake('public');
    ['admin' => $admin, 'participant' => $participant] = profileAttendanceSetup();

    $this->actingAs($admin)
        ->post(route('participants.attendance', $participant), [
            'attendance' => 'Absent',
            'justification' => UploadedFile::fake()->create('memo.pdf', 80, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $program = profileAttendanceProgram($admin);

    expect($program['attendance'])->toBe('Absent')
        ->and($program['justification']['original_name'])->toBe('memo.pdf')
        ->and($program['justification']['uploaded_by_name'])->toBe('Ana Admin');
    Storage::disk('public')->assertExists($program['justification']['file_path']);
});

test('marking absent without a memo is rejected so the profile can show the error', function () {
    ['admin' => $admin, 'participant' => $participant] = profileAttendanceSetup();

    $this->actingAs($admin)
        ->postJson(route('participants.attendance', $participant), ['attendance' => 'Absent'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('justification');

    expect($participant->fresh()->attendance)->toBe('Pending');
});

test('changing an absent employee to complete removes the memo from the training profile', function () {
    Storage::fake('public');
    ['admin' => $admin, 'participant' => $participant] = profileAttendanceSetup();

    $this->actingAs($admin)->post(route('participants.attendance', $participant), [
        'attendance' => 'Absent',
        'justification' => UploadedFile::fake()->create('memo.pdf', 80, 'application/pdf'),
    ]);
    $memoPath = $participant->fresh()->justification->file_path;

    $this->actingAs($admin)
        ->post(route('participants.attendance', $participant), ['attendance' => 'Complete', 'hours' => 16])
        ->assertSessionHasNoErrors();

    $program = profileAttendanceProgram($admin);

    expect($program['attendance'])->toBe('Complete')
        ->and($program['justification'])->toBeNull();
    Storage::disk('public')->assertMissing($memoPath);
});

test('non-admin users cannot set attendance for other employees', function () {
    ['participant' => $participant] = profileAttendanceSetup();
    $user = User::factory()->create(['empcode' => 'EMP-PAT-USER', 'access' => 'user']);

    $this->actingAs($user)
        ->post(route('participants.attendance', $participant), ['attendance' => 'Complete', 'hours' => 16])
        ->assertForbidden();

    expect($participant->fresh()->attendance)->toBe('Pending');
});
