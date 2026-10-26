<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\ResourceSpeaker;
use App\Models\Submission;
use App\Models\TerminalReport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
});

function terminalReportEmployee(string $empcode): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Planning Division',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'P',
        'POSITION' => 'Training Specialist I',
        'SG' => '11',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Central Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

/**
 * @return array{user: User, batch: Batch, participant: Participant, program: Program}
 */
function terminalReportSetup(string $empcode, string $attendance = 'Complete'): array
{
    terminalReportEmployee($empcode);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $program = Program::create([
        'title' => 'Leadership Development Program',
        'description' => 'Strengthen the leadership skills of supervisors.',
        'modality' => 'In-person',
        'pax' => '20',
        'category' => 'Local-Public',
        'type' => 'SUPERVISORY/MANAGERIAL',
        'initiated' => 'TDI',
        'provider' => 'TESDA Development Institute',
        'cost' => '0',
        'fund' => 'CO-SDP',
        'origin' => 'Local',
        'added_by' => 'EMP-TR-ADMIN',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'In-person',
        'venue' => 'TDI Building, Taguig',
        'date_start' => '2026-03-03',
        'date_end' => '2026-03-07',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '5',
        'hours' => '40',
    ]);

    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => $attendance, 'hours' => 40, 'added_by' => 'system',
    ]);

    return compact('user', 'batch', 'participant', 'program');
}

function terminalReportRequirement(Batch $batch, string $title): Requirement
{
    return Requirement::create([
        'batch_id' => $batch->id,
        'title' => $title,
        'name' => Requirement::TYPES[$title]['name'] ?? $title,
        'due_date' => '2026-03-22',
        'is_required' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function terminalReportPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'program_description' => [
            'title' => 'Leadership Development Program',
            'date_duration' => 'March 3-7, 2026',
            'hours' => '40',
            'venue' => 'TDI Building, Taguig',
            'conducted_by' => 'TESDA Development Institute',
            'sponsoring_institution' => 'Technical Education and Skills Development Authority (TESDA)',
            'designed_for' => 'TESDA supervisors',
            'objectives' => 'Strengthen the leadership skills of supervisors.',
            'methodology' => 'Lectures and workshops',
            'course_outline' => "Leading teams\nCoaching",
            'resource_speakers' => 'Maria Santos, Director',
        ],
        'prepared_by' => ['empcode' => 'EMP-TR-01', 'name' => 'Juan P. Dela Cruz', 'position' => 'Training Specialist I', 'office' => 'Planning Division', 'date' => '2026-03-10'],
        'approved_by' => ['empcode' => 'EMP-TR-SUP', 'name' => 'Ana Reyes', 'position' => 'Chief TESD Specialist', 'office' => 'Planning Division', 'date' => null],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function reentryPlanPayload(): array
{
    return [
        'competencies_gained' => 'Coaching and delegation skills.',
        'reap_summary' => 'Echo the training to my unit and apply coaching sessions.',
        'reap_objectives' => 'Improve team performance.',
        'action_plan' => [
            ['activity' => 'Echo training', 'expected_output' => '10 staff oriented', 'schedule' => 'Q2 2026', 'budget' => 'None', 'remarks' => null],
        ],
    ];
}

test('the form page auto-fills the program description from the program and batch', function () {
    ['user' => $user, 'batch' => $batch, 'program' => $program] = terminalReportSetup('EMP-TR-01');
    $requirement = terminalReportRequirement($batch, 'TREAP');
    ResourceSpeaker::create(['program_id' => $program->id, 'program_code' => $program->program_code, 'name' => 'Maria Santos', 'designation' => 'Director']);

    $this->actingAs($user)
        ->get(route('programs.terminal-report.edit', [$batch, $requirement]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyPrograms/TerminalReport')
            ->where('reentryPlanRequired', false)
            ->where('autoFilled.title', 'Leadership Development Program')
            ->where('autoFilled.date_duration', 'March 3–7, 2026')
            ->where('autoFilled.hours', '40')
            ->where('autoFilled.venue', 'TDI Building, Taguig')
            ->where('autoFilled.conducted_by', 'TESDA Development Institute')
            ->where('autoFilled.objectives', 'Strengthen the leadership skills of supervisors.')
            ->where('autoFilled.resource_speakers', 'Maria Santos, Director')
            ->where('defaultPreparedBy.name', 'Juan P. Dela Cruz')
            ->where('defaultPreparedBy.position', 'Training Specialist I')
        );
});

test('a REAP requirement requires the re-entry action plan', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-02');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)
        ->get(route('programs.terminal-report.edit', [$batch, $requirement]))
        ->assertInertia(fn (Assert $page) => $page->where('reentryPlanRequired', true));
});

test('submitting a TREAP form generates the PDF and submits it for review when part II is skipped', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-03');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload())
        ->assertRedirect(route('programs.my-progress', $batch))
        ->assertSessionHasNoErrors();

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->report_type)->toBe('TREAP')
        ->and($report->program_description['designed_for'])->toBe('TESDA supervisors')
        ->and($report->action_plan)->toBeNull();

    $submission = Submission::where('participant_id', $participant->id)->where('requirement_id', $requirement->id)->first();
    expect($submission->status)->toBe('Pending');
    Storage::disk('public')->assertExists($submission->file_path);
    expect(Storage::disk('public')->get($submission->file_path))->toStartWith('%PDF');
});

test('a REAP form requires the re-entry action plan', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-04');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload())
        ->assertSessionHasErrors(['competencies_gained', 'reap_summary', 'reap_objectives', 'action_plan']);

    expect(TerminalReport::count())->toBe(0);
});

test('a TREAP participant who chooses to fill out part II must complete it', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-11');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(['include_reentry_plan' => true]))
        ->assertSessionHasErrors(['competencies_gained', 'reap_summary', 'reap_objectives', 'action_plan']);

    expect(TerminalReport::count())->toBe(0);
});

test('a TREAP participant can fill out part II and it is saved', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-12');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(['include_reentry_plan' => true] + reentryPlanPayload()))
        ->assertSessionHasNoErrors();

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->report_type)->toBe('TREAP')
        ->and($report->hasReentryPlan())->toBeTrue()
        ->and($report->reap_summary)->toBe('Echo the training to my unit and apply coaching sessions.');
});

test('the PDF always shows part II and marks it N/A when a TREAP participant skips it', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-13');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(['include_reentry_plan' => false]));

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->hasReentryPlan())->toBeFalse();

    $html = view('pdf.terminal-report', ['report' => $report])->render();
    expect($html)->toContain('Re-Entry Action Plan (REAP)')
        ->toContain('Details of the Action Plan')
        ->and(substr_count($html, 'N/A'))->toBe(8);
});

test('the PDF shows the filled part II instead of N/A', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-14');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(reentryPlanPayload()));

    $html = view('pdf.terminal-report', ['report' => TerminalReport::where('participant_id', $participant->id)->first()])->render();
    expect($html)->toContain('Coaching and delegation skills.')
        ->toContain('Echo training')
        ->not->toContain('N/A');
});

test('submitting a complete REAP form saves the action plan and submits the PDF', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-05');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(reentryPlanPayload()))
        ->assertSessionHasNoErrors();

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->report_type)->toBe('REAP')
        ->and($report->action_plan)->toHaveCount(1)
        ->and($report->action_plan[0]['activity'])->toBe('Echo training');

    expect(Submission::where('requirement_id', $requirement->id)->value('status'))->toBe('Pending');
});

test('resubmitting the form replaces the previous PDF and sends a rejected report back for review', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-06');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload());
    $submission = Submission::where('participant_id', $participant->id)->first();
    $firstPath = $submission->file_path;
    $submission->update(['status' => 'Rejected', 'remarks' => 'Please fix the venue.']);

    $this->travel(2)->seconds();
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload([
        'program_description' => ['venue' => 'TESDA Complex, Taguig'],
    ]));

    $submission->refresh();
    expect($submission->status)->toBe('Pending')
        ->and($submission->file_path)->not->toBe($firstPath)
        ->and(TerminalReport::count())->toBe(1)
        ->and(TerminalReport::first()->program_description['venue'])->toBe('TESDA Complex, Taguig');
    Storage::disk('public')->assertMissing($firstPath);
});

test('an approved report can no longer be edited', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-07');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    Submission::create([
        'participant_id' => $participant->id, 'program_code' => $batch->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirement->id, 'status' => 'Approved', 'file_path' => 'submissions/old.pdf', 'submitted_at' => now(),
    ]);

    $this->actingAs($user)->get(route('programs.terminal-report.edit', [$batch, $requirement]))->assertForbidden();
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload())->assertForbidden();
});

test('the form is only available for TREAP and REAP requirements', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-08');
    $requirement = terminalReportRequirement($batch, 'TDOR');

    $this->actingAs($user)->get(route('programs.terminal-report.edit', [$batch, $requirement]))->assertNotFound();
});

test('only participants of the batch can open the form', function () {
    ['batch' => $batch] = terminalReportSetup('EMP-TR-09');
    $requirement = terminalReportRequirement($batch, 'TREAP');
    $outsider = User::factory()->create(['empcode' => 'EMP-TR-OUT', 'access' => 'user']);

    $this->actingAs($outsider)->get(route('programs.terminal-report.edit', [$batch, $requirement]))->assertNotFound();
});

test('absent participants cannot submit the report', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-10', 'Absent');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)->get(route('programs.terminal-report.edit', [$batch, $requirement]))->assertForbidden();
});
