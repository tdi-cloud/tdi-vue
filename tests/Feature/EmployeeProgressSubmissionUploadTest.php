<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The Employee Training Profile modal uploads requirement submissions through
 * the same submissions.store route as the program page, then reloads the
 * employee's progress so the requirement shows its new status and file.
 *
 * @return array{admin: User, participant: Participant, batch: Batch, program: Program, requirement: Requirement}
 */
function profileUploadSetup(): array
{
    Employee::forceCreate([
        'EMPCODE' => 'EMP-PUP-01',
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Lombos',
        'FIRSTNAME' => 'Kenneth',
        'MI' => 'V',
        'POSITION' => 'Supervising TESD Specialist',
        'SG' => '22',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    $program = Program::create([
        'title' => 'Profile Upload Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => 'EMP-PUP-CREATOR',
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
        'empcode' => 'EMP-PUP-01',
        'attendance' => 'Complete',
        'hours' => 16,
        'added_by' => 'system',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
    ]);

    $admin = User::factory()->create(['empcode' => 'EMP-PUP-ADMIN', 'access' => 'admin']);

    return compact('admin', 'participant', 'batch', 'program', 'requirement');
}

/**
 * @param  array{participant: Participant, batch: Batch, program: Program, requirement: Requirement}  $setup
 * @return array<string, mixed>
 */
function profileUploadPayload(array $setup, UploadedFile $file, string $status = 'Approved'): array
{
    return [
        'participant_id' => $setup['participant']->id,
        'batch_id' => $setup['batch']->id,
        'program_code' => $setup['program']->program_code,
        'requirement_id' => $setup['requirement']->id,
        'status' => $status,
        'remarks' => 'Encoded from the training profile',
        'file' => $file,
    ];
}

test('an admin can upload a requirement submission from the training profile and the profile shows it', function () {
    Storage::fake('public');
    $setup = profileUploadSetup();

    $this->actingAs($setup['admin'])
        ->post(route('submissions.store'), profileUploadPayload($setup, UploadedFile::fake()->create('treap.pdf', 120, 'application/pdf')))
        ->assertSessionHasNoErrors();

    $submission = Submission::where('participant_id', $setup['participant']->id)
        ->where('requirement_id', $setup['requirement']->id)
        ->firstOrFail();

    expect($submission->status)->toBe('Approved')
        ->and($submission->remarks)->toBe('Encoded from the training profile')
        ->and($submission->added_by)->toBe('EMP-PUP-ADMIN')
        ->and($submission->submitted_at)->not->toBeNull();
    Storage::disk('public')->assertExists($submission->file_path);

    $program = $this->actingAs($setup['admin'])
        ->getJson(route('employees.progress', ['empcode' => 'EMP-PUP-01']))
        ->assertSuccessful()
        ->json('enrolled_programs.0');

    expect($program['approved_submissions'])->toBe(1)
        ->and($program['requirements'][0]['submission']['status'])->toBe('Approved')
        ->and($program['requirements'][0]['submission']['file_path'])->toBe($submission->file_path);
});

test('uploading again from the training profile replaces the previous file', function () {
    Storage::fake('public');
    $setup = profileUploadSetup();

    $this->actingAs($setup['admin'])
        ->post(route('submissions.store'), profileUploadPayload($setup, UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'), 'Pending'));
    $firstPath = Submission::firstOrFail()->file_path;

    $this->actingAs($setup['admin'])
        ->post(route('submissions.store'), profileUploadPayload($setup, UploadedFile::fake()->create('second.pdf', 50, 'application/pdf')))
        ->assertSessionHasNoErrors();

    $submission = Submission::firstOrFail();

    expect(Submission::count())->toBe(1)
        ->and($submission->status)->toBe('Approved')
        ->and($submission->file_path)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($submission->file_path);
});

test('the training profile upload only accepts PDF files', function () {
    Storage::fake('public');
    $setup = profileUploadSetup();

    $this->actingAs($setup['admin'])
        ->postJson(route('submissions.store'), profileUploadPayload($setup, UploadedFile::fake()->create('treap.docx', 50)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Submission::count())->toBe(0);
});

test('non-admin users cannot upload submissions for other employees', function () {
    Storage::fake('public');
    $setup = profileUploadSetup();
    $user = User::factory()->create(['empcode' => 'EMP-PUP-USER', 'access' => 'user']);

    $this->actingAs($user)
        ->post(route('submissions.store'), profileUploadPayload($setup, UploadedFile::fake()->create('treap.pdf', 50, 'application/pdf')))
        ->assertForbidden();

    expect(Submission::count())->toBe(0);
});
