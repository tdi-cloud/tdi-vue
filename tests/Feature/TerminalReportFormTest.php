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
use Illuminate\Http\UploadedFile;
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
            ['activity' => 'Echo training', 'expected_output' => '10 staff oriented', 'start_date' => '2026-04-01', 'target_date' => '2026-06-30', 'budget' => '5000', 'remarks' => null],
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

test('generating a TREAP form creates the unsigned PDF but does not submit the requirement', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-03');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload())
        ->assertRedirect(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertSessionHasNoErrors();

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->report_type)->toBe('TREAP')
        ->and($report->program_description['designed_for'])->toBe('TESDA supervisors')
        ->and($report->activities)->toBeEmpty();

    Storage::disk('public')->assertExists($report->generated_pdf_path);
    expect(Storage::disk('public')->get($report->generated_pdf_path))->toStartWith('%PDF')
        ->and(Submission::where('requirement_id', $requirement->id)->exists())->toBeFalse();
});

test('uploading the signed copy is what submits the requirement for review', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-18');
    $requirement = terminalReportRequirement($batch, 'TREAP');
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload());

    $this->actingAs($user)
        ->post(route('programs.my-progress.submit', [$batch, $requirement]), [
            'file' => UploadedFile::fake()->create('signed-treap.pdf', 200, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $submission = Submission::where('participant_id', $participant->id)->where('requirement_id', $requirement->id)->first();
    expect($submission->status)->toBe('Pending');

    $this->actingAs($user)
        ->get(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('signedCopy.status', 'Pending')
            ->where('signedCopy.file_url', '/storage/'.$submission->file_path)
            ->where('signedCopy.older_than_draft', false)
        );
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

test('generating a complete REAP form saves the action plan', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-05');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)
        ->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(reentryPlanPayload()))
        ->assertSessionHasNoErrors();

    $report = TerminalReport::where('participant_id', $participant->id)->first();
    expect($report->report_type)->toBe('REAP')
        ->and($report->activities)->toHaveCount(1)
        ->and($report->activities->first()->activity)->toBe('Echo training');
});

test('regenerating the form replaces the unsigned PDF and leaves the signed copy untouched', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-06');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload());
    $firstDraft = TerminalReport::first()->generated_pdf_path;
    $signedCopy = Submission::create([
        'participant_id' => $participant->id, 'program_code' => $batch->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirement->id, 'status' => 'Rejected', 'remarks' => 'Please fix the venue.',
        'file_path' => 'submissions/signed.pdf', 'submitted_at' => now(),
    ]);

    $this->travel(2)->seconds();
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload([
        'program_description' => ['venue' => 'TESDA Complex, Taguig'],
    ]));

    $report = TerminalReport::first();
    expect(TerminalReport::count())->toBe(1)
        ->and($report->program_description['venue'])->toBe('TESDA Complex, Taguig')
        ->and($report->generated_pdf_path)->not->toBe($firstDraft)
        ->and($signedCopy->fresh()->status)->toBe('Rejected')
        ->and($signedCopy->fresh()->file_path)->toBe('submissions/signed.pdf');
    Storage::disk('public')->assertMissing($firstDraft);

    $this->actingAs($user)
        ->get(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertInertia(fn (Assert $page) => $page->where('signedCopy.older_than_draft', true));
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

test('after generating, the page shows the unsigned PDF and asks for the signed copy', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-15');
    $requirement = terminalReportRequirement($batch, 'REAP');

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload(reentryPlanPayload()));
    $report = TerminalReport::where('participant_id', $participant->id)->first();

    $this->actingAs($user)
        ->get(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyPrograms/TerminalReportGenerated')
            ->where('pdfUrl', '/storage/'.$report->generated_pdf_path)
            ->where('signedCopy', null)
            ->where('activitiesCount', 1)
            ->where('canEdit', true)
        );
});

test('the generated page sends participants to the form when nothing was generated yet', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-16');
    $requirement = terminalReportRequirement($batch, 'TREAP');

    $this->actingAs($user)
        ->get(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertRedirect(route('programs.terminal-report.edit', [$batch, $requirement]));
});

test('an approved report can still be viewed on the generated page but not edited', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant] = terminalReportSetup('EMP-TR-17');
    $requirement = terminalReportRequirement($batch, 'TREAP');
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload());
    Submission::create([
        'participant_id' => $participant->id, 'program_code' => $batch->program_code, 'batch_id' => $batch->id,
        'requirement_id' => $requirement->id, 'status' => 'Approved', 'file_path' => 'submissions/signed.pdf', 'submitted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('programs.terminal-report.generated', [$batch, $requirement]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('signedCopy.status', 'Approved'));
});

test('the program page tells participants to sign and upload a generated report', function () {
    ['user' => $user, 'batch' => $batch] = terminalReportSetup('EMP-TR-19');
    $requirement = terminalReportRequirement($batch, 'TREAP');
    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), terminalReportPayload());

    $this->actingAs($user)
        ->get(route('programs.my-progress', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.requirements.0.report_generated', true)
            ->where('program.requirements.0.status', null)
        );
});
