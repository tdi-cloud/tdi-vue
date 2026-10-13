<?php

use App\Models\Batch;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use Carbon\Carbon;

/**
 * A submission handed in 3 days after its requirement's due date (i.e. "Late").
 */
function lateSubmission(): Submission
{
    $program = Program::create([
        'title' => 'Edit Date Program',
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
        'due_date' => now()->subDays(10)->toDateString(),
        'is_required' => true,
    ]);

    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => 'EMP-ED-01',
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);

    return Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $program->program_code,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'file_path' => 'submissions/test.pdf',
        'submitted_at' => now()->subDays(7),
    ]);
}

test('admins and superadmins can edit the submission date', function (string $access) {
    $submission = lateSubmission();
    $onTime = Carbon::parse($submission->requirement->due_date)->subDay()->setTime(9, 30);

    $this->actingAs(User::factory()->create(['empcode' => 'EMP-ED-'.$access, 'access' => $access]))
        ->patch(route('submissions.submitted-at.update', $submission), ['submitted_at' => $onTime->toIso8601String()])
        ->assertRedirect()
        ->assertSessionHas('success', 'Submission date updated.');

    expect($submission->fresh()->submitted_at->equalTo($onTime))->toBeTrue();
})->with(['admin', 'superadmin']);

test('editing the submission date is recorded in the activity log', function () {
    $submission = lateSubmission();
    $previous = $submission->submitted_at->toDateTimeString();
    $onTime = Carbon::parse($submission->requirement->due_date)->subDay()->setTime(9, 30);

    $this->actingAs(User::factory()->create(['empcode' => 'EMP-ED-ADMIN', 'access' => 'admin']))
        ->patch(route('submissions.submitted-at.update', $submission), ['submitted_at' => $onTime->toIso8601String()]);

    $log = SubmissionActivityLog::where('submission_id', $submission->id)->where('action', 'updated')->sole();
    expect($log->meta['changed_fields'])->toBe(['submitted_at']);
    expect($log->meta['previous_submitted_at'])->toBe($previous);
    expect($log->meta['new_submitted_at'])->toBe($onTime->toDateTimeString());
});

test('the submission date cannot be set in the future', function () {
    $submission = lateSubmission();
    $original = $submission->submitted_at;

    $this->actingAs(User::factory()->create(['empcode' => 'EMP-ED-ADMIN', 'access' => 'admin']))
        ->patch(route('submissions.submitted-at.update', $submission), ['submitted_at' => now()->addDay()->toIso8601String()])
        ->assertSessionHasErrors('submitted_at');

    expect($submission->fresh()->submitted_at->equalTo($original))->toBeTrue();
});

test('regular users cannot edit the submission date', function () {
    $submission = lateSubmission();
    $original = $submission->submitted_at;

    $this->actingAs(User::factory()->create(['empcode' => 'EMP-ED-USER', 'access' => 'user']))
        ->patch(route('submissions.submitted-at.update', $submission), ['submitted_at' => now()->subDays(20)->toIso8601String()])
        ->assertForbidden();

    expect($submission->fresh()->submitted_at->equalTo($original))->toBeTrue();
});
