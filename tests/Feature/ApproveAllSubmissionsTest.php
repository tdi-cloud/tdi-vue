<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function approveAllUser(string $access): User
{
    return User::factory()->create(['empcode' => 'EMP-AA-'.$access, 'access' => $access]);
}

function approveAllProgram(string $title): Program
{
    return Program::create([
        'title' => $title,
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);
}

/**
 * @return array{0: Batch, 1: Requirement}
 */
function approveAllBatch(Program $program, string $label): array
{
    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => $label,
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

    return [$batch, $requirement];
}

function approveAllSubmission(Program $program, Batch $batch, Requirement $requirement, string $empcode, string $status): Submission
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Participant',
        'FIRSTNAME' => $empcode,
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

    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    return Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => $status,
        'file_path' => 'submissions/test.pdf',
        'submitted_at' => now(),
    ]);
}

// ── Per-program (admins) ─────────────────────────────────────────────────────

test('an admin can approve all pending submissions of a program', function () {
    $admin = approveAllUser('admin');
    $program = approveAllProgram('Program A');
    [$batch, $requirement] = approveAllBatch($program, 'Batch 1');
    $pendingOne = approveAllSubmission($program, $batch, $requirement, 'EMP-AA-P1', 'Pending');
    $pendingTwo = approveAllSubmission($program, $batch, $requirement, 'EMP-AA-P2', 'Pending');

    $otherProgram = approveAllProgram('Program B');
    [$otherBatch, $otherRequirement] = approveAllBatch($otherProgram, 'Batch 1');
    $otherPending = approveAllSubmission($otherProgram, $otherBatch, $otherRequirement, 'EMP-AA-P3', 'Pending');

    $this->actingAs($admin)
        ->post(route('programs.submissions.approve-all', $program))
        ->assertRedirect()
        ->assertSessionHas('success', '2 submission(s) approved.');

    expect($pendingOne->fresh()->status)->toBe('Approved');
    expect($pendingOne->fresh()->reviewed_by)->toBe($admin->name);
    expect($pendingOne->fresh()->reviewed_at)->not->toBeNull();
    expect($pendingTwo->fresh()->status)->toBe('Approved');
    expect($otherPending->fresh()->status)->toBe('Pending');
    expect(SubmissionActivityLog::where('action', 'reviewed')->count())->toBe(2);
});

test('approve all leaves rejected submissions untouched', function () {
    $admin = approveAllUser('admin');
    $program = approveAllProgram('Program A');
    [$batch, $requirement] = approveAllBatch($program, 'Batch 1');
    $rejected = approveAllSubmission($program, $batch, $requirement, 'EMP-AA-R1', 'Rejected');

    $this->actingAs($admin)->post(route('programs.submissions.approve-all', $program))->assertRedirect();

    expect($rejected->fresh()->status)->toBe('Rejected');
});

test('approve all can be limited to one batch of a program', function () {
    $admin = approveAllUser('admin');
    $program = approveAllProgram('Program A');
    [$batchOne, $requirementOne] = approveAllBatch($program, 'Batch 1');
    [$batchTwo, $requirementTwo] = approveAllBatch($program, 'Batch 2');
    $inBatchOne = approveAllSubmission($program, $batchOne, $requirementOne, 'EMP-AA-B1', 'Pending');
    $inBatchTwo = approveAllSubmission($program, $batchTwo, $requirementTwo, 'EMP-AA-B2', 'Pending');

    $this->actingAs($admin)
        ->post(route('programs.submissions.approve-all', $program), ['batch_id' => $batchOne->id])
        ->assertRedirect();

    expect($inBatchOne->fresh()->status)->toBe('Approved');
    expect($inBatchTwo->fresh()->status)->toBe('Pending');
});

test('regular users cannot approve all submissions of a program', function () {
    $program = approveAllProgram('Program A');
    [$batch, $requirement] = approveAllBatch($program, 'Batch 1');
    $pending = approveAllSubmission($program, $batch, $requirement, 'EMP-AA-U1', 'Pending');

    $this->actingAs(approveAllUser('user'))
        ->post(route('programs.submissions.approve-all', $program))
        ->assertForbidden();

    expect($pending->fresh()->status)->toBe('Pending');
});

// ── Global Submissions page (superadmins only) ───────────────────────────────

test('a superadmin can approve all pending submissions across every program', function () {
    $superadmin = approveAllUser('superadmin');
    $programA = approveAllProgram('Program A');
    [$batchA, $requirementA] = approveAllBatch($programA, 'Batch 1');
    $programB = approveAllProgram('Program B');
    [$batchB, $requirementB] = approveAllBatch($programB, 'Batch 1');
    $pendingA = approveAllSubmission($programA, $batchA, $requirementA, 'EMP-AA-G1', 'Pending');
    $pendingB = approveAllSubmission($programB, $batchB, $requirementB, 'EMP-AA-G2', 'Pending');
    $rejected = approveAllSubmission($programB, $batchB, $requirementB, 'EMP-AA-G3', 'Rejected');

    $this->actingAs($superadmin)
        ->post(route('submissions.approve-all'))
        ->assertRedirect()
        ->assertSessionHas('success', '2 submission(s) approved.');

    expect($pendingA->fresh()->status)->toBe('Approved');
    expect($pendingB->fresh()->status)->toBe('Approved');
    expect($rejected->fresh()->status)->toBe('Rejected');
});

test('the superadmin approve all follows the program filter', function () {
    $superadmin = approveAllUser('superadmin');
    $programA = approveAllProgram('Program A');
    [$batchA, $requirementA] = approveAllBatch($programA, 'Batch 1');
    $programB = approveAllProgram('Program B');
    [$batchB, $requirementB] = approveAllBatch($programB, 'Batch 1');
    $pendingA = approveAllSubmission($programA, $batchA, $requirementA, 'EMP-AA-F1', 'Pending');
    $pendingB = approveAllSubmission($programB, $batchB, $requirementB, 'EMP-AA-F2', 'Pending');

    $this->actingAs($superadmin)
        ->post(route('submissions.approve-all'), ['program_code' => $programA->program_code])
        ->assertRedirect();

    expect($pendingA->fresh()->status)->toBe('Approved');
    expect($pendingB->fresh()->status)->toBe('Pending');
});

test('an admin cannot use approve all on the global submissions page', function () {
    $program = approveAllProgram('Program A');
    [$batch, $requirement] = approveAllBatch($program, 'Batch 1');
    $pending = approveAllSubmission($program, $batch, $requirement, 'EMP-AA-X1', 'Pending');

    $this->actingAs(approveAllUser('admin'))
        ->post(route('submissions.approve-all'))
        ->assertForbidden();

    expect($pending->fresh()->status)->toBe('Pending');
});

test('the global submissions page only offers approve all to superadmins', function () {
    $program = approveAllProgram('Program A');
    [$batch, $requirement] = approveAllBatch($program, 'Batch 1');
    approveAllSubmission($program, $batch, $requirement, 'EMP-AA-V1', 'Pending');

    $this->actingAs(approveAllUser('superadmin'))
        ->get(route('submissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canApproveAll', true)
            ->where('approvablePendingCount', 1)
        );

    $this->actingAs(approveAllUser('admin'))
        ->get(route('submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canApproveAll', false));
});
