<?php

use App\Models\AbsentJustification;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;

/**
 * @return array{0: Program, 1: Batch, 2: Requirement}
 */
function dedupeTestSetup(): array
{
    $program = Program::create([
        'title' => 'Dedupe Test Program',
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
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

function dedupeTestParticipant(Batch $batch, string $empcode): Participant
{
    return Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Pending', 'hours' => 0, 'added_by' => 'system',
    ]);
}

test('dry run reports the duplicate with no submission as the one to delete, and changes nothing', function () {
    [$program, $batch, $requirement] = dedupeTestSetup();

    $withSubmission = dedupeTestParticipant($batch, 'EMP-DEDUPE-01');
    $withoutSubmission = dedupeTestParticipant($batch, 'EMP-DEDUPE-01');

    Submission::create([
        'participant_id' => $withSubmission->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $this->artisan('participants:dedupe')
        ->expectsOutputToContain("mananatili #{$withSubmission->id}")
        ->assertExitCode(0);

    // Dry run — dapat walang binago.
    expect(Participant::count())->toBe(2);
    expect(Participant::find($withoutSubmission->id))->not->toBeNull();
});

test('force actually deletes the duplicate that has no submission', function () {
    [$program, $batch, $requirement] = dedupeTestSetup();

    $withSubmission = dedupeTestParticipant($batch, 'EMP-DEDUPE-02');
    $withoutSubmission = dedupeTestParticipant($batch, 'EMP-DEDUPE-02');

    Submission::create([
        'participant_id' => $withSubmission->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'submitted_at' => now(),
    ]);

    $this->artisan('participants:dedupe', ['--force' => true])->assertExitCode(0);

    expect(Participant::count())->toBe(1);
    expect(Participant::find($withSubmission->id))->not->toBeNull();
    expect(Participant::find($withoutSubmission->id))->toBeNull();
    // Ang submission ng nanalong row ay dapat buo pa rin.
    expect(Submission::where('participant_id', $withSubmission->id)->count())->toBe(1);
});

test('when both duplicates have submissions for different requirements, the loser submission is moved before deletion', function () {
    [$program, $batch, $requirementA] = dedupeTestSetup();
    $requirementB = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'REAP',
        'name' => Requirement::nameFor('REAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
    ]);

    $winner = dedupeTestParticipant($batch, 'EMP-DEDUPE-03');
    $loser = dedupeTestParticipant($batch, 'EMP-DEDUPE-03');

    Submission::create([
        'participant_id' => $winner->id, 'program_code' => $program->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirementA->id, 'status' => 'Approved', 'submitted_at' => now(),
    ]);
    Submission::create([
        'participant_id' => $loser->id, 'program_code' => $program->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirementB->id, 'status' => 'Pending', 'submitted_at' => now(),
    ]);

    $this->artisan('participants:dedupe', ['--force' => true])->assertExitCode(0);

    expect(Participant::count())->toBe(1);
    expect(Participant::find($loser->id))->toBeNull();
    // Parehong requirement ay dapat nasa nag-iisang natirang participant na.
    expect(Submission::where('participant_id', $winner->id)->pluck('requirement_id')->sort()->values()->all())
        ->toBe(collect([$requirementA->id, $requirementB->id])->sort()->values()->all());
});

test('a genuine conflict — both duplicates submitted the same requirement — is not merged or deleted', function () {
    [$program, $batch, $requirement] = dedupeTestSetup();

    $winner = dedupeTestParticipant($batch, 'EMP-DEDUPE-04');
    $loser = dedupeTestParticipant($batch, 'EMP-DEDUPE-04');

    Submission::create([
        'participant_id' => $winner->id, 'program_code' => $program->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirement->id, 'status' => 'Approved', 'submitted_at' => now(),
    ]);
    Submission::create([
        'participant_id' => $loser->id, 'program_code' => $program->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirement->id, 'status' => 'Pending', 'submitted_at' => now(),
    ]);

    $this->artisan('participants:dedupe', ['--force' => true])
        ->expectsOutputToContain('manual review')
        ->assertExitCode(0);

    // Parehong row (at parehong submission) ay dapat buo pa rin — walang na-delete.
    expect(Participant::count())->toBe(2);
    expect(Submission::count())->toBe(2);
});

test('when neither duplicate has any data, the earliest-created row is kept', function () {
    [$program, $batch, $requirement] = dedupeTestSetup();

    $first = dedupeTestParticipant($batch, 'EMP-DEDUPE-05');
    $second = dedupeTestParticipant($batch, 'EMP-DEDUPE-05');

    $this->artisan('participants:dedupe', ['--force' => true])->assertExitCode(0);

    expect(Participant::count())->toBe(1);
    expect(Participant::find($first->id))->not->toBeNull();
    expect(Participant::find($second->id))->toBeNull();
});

test('certificates and absence justification are also moved to the surviving participant before deletion', function () {
    [$program, $batch, $requirement] = dedupeTestSetup();

    // "$empty" ay may pinakaunang id, pero dahil may certificate/justification
    // ang "$withRecords", SIYA ang dapat manalo — susubukan ng test na
    // ma-preserve ang mga record na iyon sa kahit sinong survivor.
    $empty = dedupeTestParticipant($batch, 'EMP-DEDUPE-06');
    $withRecords = dedupeTestParticipant($batch, 'EMP-DEDUPE-06');

    Certificate::create([
        'participant_id' => $withRecords->id,
        'batch_id' => $batch->id,
        'program_code' => $program->program_code,
        'empcode' => $withRecords->empcode,
        'type' => 'Participation',
    ]);
    AbsentJustification::create([
        'participant_id' => $withRecords->id,
        'file_path' => 'justifications/test.pdf',
    ]);

    $this->artisan('participants:dedupe', ['--force' => true])
        ->expectsOutputToContain("mananatili #{$withRecords->id}")
        ->assertExitCode(0);

    expect(Participant::count())->toBe(1);
    expect(Participant::find($empty->id))->toBeNull();
    expect(Certificate::where('participant_id', $withRecords->id)->count())->toBe(1);
    expect(AbsentJustification::where('participant_id', $withRecords->id)->count())->toBe(1);
});

test('non-duplicate participants across different batches or employees are left untouched', function () {
    [$program, $batchA, $requirement] = dedupeTestSetup();
    $batchB = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 2',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    dedupeTestParticipant($batchA, 'EMP-DEDUPE-07');
    dedupeTestParticipant($batchB, 'EMP-DEDUPE-07'); // ibang batch, hindi duplicate
    dedupeTestParticipant($batchA, 'EMP-DEDUPE-08'); // ibang empleyado, hindi duplicate

    $this->artisan('participants:dedupe', ['--force' => true])
        ->expectsOutputToContain('Walang nahanap na duplicate')
        ->assertExitCode(0);

    expect(Participant::count())->toBe(3);
});
