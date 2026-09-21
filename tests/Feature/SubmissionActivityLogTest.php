<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\SubmissionActivityLog;
use App\Models\User;

function submissionActivityLogTestAdmin(string $empcode, string $name = 'Ana Admin'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin', 'name' => $name]);
}

function submissionActivityLogTestRequirement(string $empcode): Requirement
{
    $program = Program::create([
        'title' => 'Submission Log Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => $empcode,
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-05',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '5',
        'hours' => '40',
        'added_by' => $empcode,
    ]);

    (new Employee)->forceFill([
        'EMPCODE' => 'PART-01',
        'OFFICE/DIVISION' => 'TDI',
        'LASTNAME' => 'Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => '',
        'POSITION' => 'Staff',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'TDI',
        'LOCATION' => 'Manila',
        'SECTION' => 'N/A',
        'UNIT' => 'N/A',
    ])->save();

    Participant::create([
        'batch_id' => $batch->id,
        'empcode' => 'PART-01',
        'attendance' => 'Present',
        'hours' => 40,
        'added_by' => $empcode,
    ]);

    return Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => 'Terminal Report',
        'due_date' => '2026-01-10',
    ]);
}

test('encoding a new submission records exactly one "encoded" activity log entry', function () {
    $admin = submissionActivityLogTestAdmin('EMP-SUB-01', 'Ana Admin');
    $requirement = submissionActivityLogTestRequirement('EMP-SUB-01');
    $participant = Participant::first();

    $this->actingAs($admin)
        ->post(route('submissions.store'), [
            'participant_id' => $participant->id,
            'program_code' => $requirement->batch->program_code,
            'batch_id' => $requirement->batch_id,
            'requirement_id' => $requirement->id,
            'notes' => 'Initial submission',
        ])
        ->assertSessionDoesntHaveErrors();

    $submission = Submission::where('participant_id', $participant->id)->firstOrFail();

    expect(SubmissionActivityLog::where('submission_id', $submission->id)->count())->toBe(1);

    $log = SubmissionActivityLog::where('submission_id', $submission->id)->first();
    expect($log->action)->toBe('encoded')
        ->and($log->participant_name)->toBe('Juan Cruz')
        ->and($log->requirement_name)->toBe('Terminal Report')
        ->and($log->performed_by)->toBe('Ana Admin');
});

test('editing an existing submission records an "updated" activity log entry with the changed fields', function () {
    $admin = submissionActivityLogTestAdmin('EMP-SUB-02', 'Ben Editor');
    $requirement = submissionActivityLogTestRequirement('EMP-SUB-02');
    $participant = Participant::first();

    $submission = Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $requirement->batch->program_code,
        'batch_id' => $requirement->batch_id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
        'notes' => 'Original note',
    ]);

    $this->actingAs($admin)
        ->post(route('submissions.store'), [
            'participant_id' => $participant->id,
            'program_code' => $requirement->batch->program_code,
            'batch_id' => $requirement->batch_id,
            'requirement_id' => $requirement->id,
            'notes' => 'Updated note',
        ])
        ->assertSessionDoesntHaveErrors();

    $log = SubmissionActivityLog::where('submission_id', $submission->id)->where('action', 'updated')->first();

    expect($log)->not->toBeNull()
        ->and($log->performed_by)->toBe('Ben Editor')
        ->and($log->meta['changed_fields'])->toContain('notes');
});

test('reviewing a submission records a "reviewed" activity log entry', function () {
    $admin = submissionActivityLogTestAdmin('EMP-SUB-03', 'Cara Reviewer');
    $requirement = submissionActivityLogTestRequirement('EMP-SUB-03');
    $participant = Participant::first();

    $submission = Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $requirement->batch->program_code,
        'batch_id' => $requirement->batch_id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('submissions.review', $submission), [
            'status' => 'Approved',
            'remarks' => 'Looks good',
        ])
        ->assertSessionDoesntHaveErrors();

    $log = SubmissionActivityLog::where('submission_id', $submission->id)->where('action', 'reviewed')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('Approved')
        ->and($log->performed_by)->toBe('Cara Reviewer')
        ->and($log->meta['remarks'])->toBe('Looks good');
});

test('deleting a submission records a "deleted" activity log entry', function () {
    $admin = submissionActivityLogTestAdmin('EMP-SUB-04', 'Dana Deleter');
    $requirement = submissionActivityLogTestRequirement('EMP-SUB-04');
    $participant = Participant::first();

    $submission = Submission::create([
        'participant_id' => $participant->id,
        'program_code' => $requirement->batch->program_code,
        'batch_id' => $requirement->batch_id,
        'requirement_id' => $requirement->id,
        'status' => 'Pending',
    ]);

    $this->actingAs($admin)
        ->delete(route('submissions.destroy', $submission))
        ->assertSessionDoesntHaveErrors();

    $log = SubmissionActivityLog::where('submission_id', $submission->id)->where('action', 'deleted')->first();
    expect($log)->not->toBeNull()
        ->and($log->performed_by)->toBe('Dana Deleter');
});

test('the hidden activity log page shows correct stats, per-user breakdown, and entries for the selected date', function () {
    $admin = submissionActivityLogTestAdmin('EMP-SUB-05', 'Elle Viewer');

    SubmissionActivityLog::create(['participant_name' => 'A', 'requirement_name' => 'R1', 'action' => 'encoded', 'performed_by' => 'Someone']);
    SubmissionActivityLog::create(['participant_name' => 'A', 'requirement_name' => 'R1', 'action' => 'updated', 'performed_by' => 'Someone']);
    SubmissionActivityLog::create(['participant_name' => 'B', 'requirement_name' => 'R2', 'action' => 'reviewed', 'performed_by' => 'Other']);
    SubmissionActivityLog::create(['participant_name' => 'B', 'requirement_name' => 'R2', 'action' => 'deleted', 'performed_by' => 'Other']);

    // Ibang araw — hindi dapat mabilang sa "today".
    $old = SubmissionActivityLog::create(['participant_name' => 'C', 'requirement_name' => 'R3', 'action' => 'encoded', 'performed_by' => 'Someone']);
    $old->created_at = now()->subDays(3);
    $old->save();

    $response = $this->actingAs($admin)->get(route('submissions.activity-log'));
    $response->assertOk();

    expect($response->inertiaProps('stats.encoded'))->toBe(1);
    expect($response->inertiaProps('stats.updated'))->toBe(1);
    expect($response->inertiaProps('stats.reviewed'))->toBe(1);
    expect($response->inertiaProps('stats.deleted'))->toBe(1);
    expect($response->inertiaProps('logs.data'))->toHaveCount(4);

    $userStats = collect($response->inertiaProps('userStats'));
    expect($userStats->firstWhere('performed_by', 'Someone')['total'])->toBe(2);
    expect($userStats->firstWhere('performed_by', 'Other')['total'])->toBe(2);
});

test('non-admin users cannot access the hidden submissions activity log page', function () {
    $user = User::factory()->create(['empcode' => 'EMP-SUB-06', 'access' => 'user']);

    $this->actingAs($user)->get(route('submissions.activity-log'))->assertForbidden();
});
