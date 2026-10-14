<?php

use App\Models\Batch;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: Program, 1: Batch}
 */
function noReqProgramSetup(): array
{
    $program = Program::create([
        'title' => 'No Requirement Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => 'EMP-NOREQ-CREATOR',
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

    return [$program, $batch];
}

function noReqParticipant(Batch $batch, string $empcode): Participant
{
    return Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
}

function noReqRequirement(Batch $batch): Requirement
{
    return Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
    ]);
}

test('deleting a requirement keeps its submissions with the requirement title', function () {
    [$program, $batch] = noReqProgramSetup();
    $requirement = noReqRequirement($batch);
    $participant = noReqParticipant($batch, 'EMP-NOREQ-01');
    $admin = User::factory()->create(['empcode' => 'EMP-NOREQ-ADMIN-01', 'access' => 'admin']);

    $submission = Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Approved',
        'file_path' => 'submissions/treap.pdf',
        'submitted_at' => now(),
    ]);

    expect($submission->fresh()->document_title)->toBe('TREAP');

    $this->actingAs($admin)
        ->delete(route('programs.requirements.destroy', [$program, $requirement]))
        ->assertRedirect();

    $kept = Submission::find($submission->id);
    expect($kept)->not->toBeNull();
    expect($kept->requirement_id)->toBeNull();
    expect($kept->document_title)->toBe('TREAP');
    expect($kept->file_path)->toBe('submissions/treap.pdf');
    expect($kept->displayTitle())->toBe('TREAP');
});

test('a participant can submit a document when the batch has no requirements', function () {
    Storage::fake('public');
    [$program, $batch] = noReqProgramSetup();
    $participant = noReqParticipant($batch, 'EMP-NOREQ-02');
    $user = User::factory()->create(['empcode' => 'EMP-NOREQ-02', 'access' => 'user']);

    $this->actingAs($user)
        ->post(route('programs.my-progress.documents.submit', $batch), [
            'document_title' => 'Certificate of Attendance',
            'file' => UploadedFile::fake()->create('coa.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionDoesntHaveErrors();

    $submission = Submission::where('participant_id', $participant->id)->first();
    expect($submission->requirement_id)->toBeNull();
    expect($submission->document_title)->toBe('Certificate of Attendance');
    expect($submission->status)->toBe('Pending');
    Storage::disk('public')->assertExists($submission->file_path);

    $this->actingAs($user)->get(route('programs.my-progress', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('program.can_submit_other_documents', true)
            ->where('program.other_documents.0.title', 'Certificate of Attendance'));
});

test('a participant cannot submit an other document when the batch has requirements', function () {
    Storage::fake('public');
    [$program, $batch] = noReqProgramSetup();
    noReqRequirement($batch);
    noReqParticipant($batch, 'EMP-NOREQ-03');
    $user = User::factory()->create(['empcode' => 'EMP-NOREQ-03', 'access' => 'user']);

    $this->actingAs($user)
        ->post(route('programs.my-progress.documents.submit', $batch), [
            'document_title' => 'Certificate of Attendance',
            'file' => UploadedFile::fake()->create('coa.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(Submission::count())->toBe(0);
});

test('a participant can delete their own pending other document but not an approved one', function () {
    Storage::fake('public');
    [$program, $batch] = noReqProgramSetup();
    $participant = noReqParticipant($batch, 'EMP-NOREQ-04');
    $user = User::factory()->create(['empcode' => 'EMP-NOREQ-04', 'access' => 'user']);

    $base = [
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'document_title' => 'Other',
        'submitted_at' => now(),
    ];
    $pending = Submission::create($base + ['status' => 'Pending']);
    $approved = Submission::create($base + ['status' => 'Approved']);

    $this->actingAs($user)->delete(route('programs.my-progress.documents.destroy', [$batch, $pending]))->assertRedirect();
    $this->actingAs($user)->delete(route('programs.my-progress.documents.destroy', [$batch, $approved]))->assertForbidden();

    expect(Submission::find($pending->id))->toBeNull();
    expect(Submission::find($approved->id))->not->toBeNull();
});

test('an admin can encode an other document without a requirement', function () {
    Storage::fake('public');
    [$program, $batch] = noReqProgramSetup();
    $participant = noReqParticipant($batch, 'EMP-NOREQ-05');
    $admin = User::factory()->create(['empcode' => 'EMP-NOREQ-ADMIN-05', 'access' => 'admin']);

    $this->actingAs($admin)->post(route('submissions.store'), [
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'document_title' => 'Terminal Report',
        'status' => 'Approved',
        'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
    ])->assertSessionDoesntHaveErrors();

    $submission = Submission::where('participant_id', $participant->id)->first();
    expect($submission->requirement_id)->toBeNull();
    expect($submission->document_title)->toBe('Terminal Report');
    expect($submission->status)->toBe('Approved');

    // Editing it again updates the same record instead of creating a new one
    $this->actingAs($admin)->post(route('submissions.store'), [
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'submission_id' => $submission->id,
        'document_title' => 'Terminal Report (revised)',
        'status' => 'Rejected',
    ])->assertSessionDoesntHaveErrors();

    expect(Submission::count())->toBe(1);
    expect($submission->fresh()->document_title)->toBe('Terminal Report (revised)');
    expect($submission->fresh()->status)->toBe('Rejected');
});

test('an admin must give a title and a file for a new other document', function () {
    [$program, $batch] = noReqProgramSetup();
    $participant = noReqParticipant($batch, 'EMP-NOREQ-06');
    $admin = User::factory()->create(['empcode' => 'EMP-NOREQ-ADMIN-06', 'access' => 'admin']);

    $this->actingAs($admin)->post(route('submissions.store'), [
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
    ])->assertSessionHasErrors('document_title');

    $this->actingAs($admin)->post(route('submissions.store'), [
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'document_title' => 'Terminal Report',
    ])->assertSessionHasErrors('file');

    expect(Submission::count())->toBe(0);
});
