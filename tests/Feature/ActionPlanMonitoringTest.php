<?php

use App\Models\ActionPlanActivity;
use App\Models\Batch;
use App\Models\Employee;
use App\Models\ImplementationReport;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\TerminalReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-05-15 09:00:00');
});

/**
 * @return array{user: User, batch: Batch, participant: Participant, report: TerminalReport}
 */
function actionPlanSetup(string $empcode, string $region = 'NCR'): array
{
    Employee::forceCreate([
        'EMPCODE' => $empcode, 'OFFICE/DIVISION' => 'Planning Division', 'LASTNAME' => 'Santos', 'FIRSTNAME' => 'Ana',
        'MI' => '', 'POSITION' => 'Training Specialist I', 'SG' => '11', 'PLANTILLA STATUS' => 'Permanent', 'SEX' => 'F',
        'REGION' => $region, 'OFFICE' => 'Central Office', 'LOCATION' => 'Main', 'SECTION' => 'Test', 'UNIT' => 'Test',
    ]);
    $user = User::factory()->create(['empcode' => $empcode, 'access' => 'user']);

    $program = Program::create([
        'title' => 'Coaching Program '.$empcode, 'modality' => 'In-person', 'pax' => '20', 'category' => 'Local-Public',
        'type' => 'TECHNICAL', 'initiated' => 'TDI', 'cost' => '0', 'fund' => 'CO-SDP', 'origin' => 'Local',
    ]);
    $batch = Batch::create([
        'program_code' => $program->program_code, 'batch' => 'Batch 1', 'status' => 'Closed', 'modality' => 'In-person',
        'date_start' => '2026-03-02', 'date_end' => '2026-03-06', 'time_start' => '08:00', 'time_end' => '17:00', 'days' => '5', 'hours' => '40',
    ]);
    $participant = Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode, 'attendance' => 'Complete', 'hours' => 40, 'added_by' => 'system',
    ]);
    $report = TerminalReport::create([
        'participant_id' => $participant->id, 'batch_id' => $batch->id, 'report_type' => 'REAP',
        'program_description' => ['title' => $program->title], 'competencies_gained' => 'Coaching', 'reap_summary' => 'Coach my team',
        'reap_objectives' => 'Better teams', 'prepared_by' => ['name' => 'Ana Santos'], 'approved_by' => ['name' => 'Boss'],
    ]);

    return compact('user', 'batch', 'participant', 'report');
}

function actionPlanActivity(TerminalReport $report, array $overrides = []): ActionPlanActivity
{
    return $report->activities()->create($overrides + [
        'participant_id' => $report->participant_id,
        'batch_id' => $report->batch_id,
        'activity' => 'Echo training to unit staff',
        'expected_output' => '10 staff oriented',
        'start_date' => '2026-05-01',
        'target_date' => '2026-06-30',
        'budget' => 5000,
    ]);
}

/**
 * @return array<string, mixed>
 */
function implementationReportPayload(array $overrides = []): array
{
    return $overrides + [
        'accomplishment' => 'Conducted the echo training for the unit.',
        'actual_output' => '12 staff oriented',
        'date_completed' => '2026-05-14',
        'actual_cost' => '4500',
        'file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
    ];
}

test('an activity status follows its schedule until a report is submitted', function () {
    ['report' => $report] = actionPlanSetup('EMP-AP-01');

    $upcoming = actionPlanActivity($report, ['start_date' => '2026-06-01', 'target_date' => '2026-06-30']);
    $ongoing = actionPlanActivity($report, ['start_date' => '2026-05-01', 'target_date' => '2026-05-31']);
    $overdue = actionPlanActivity($report, ['start_date' => '2026-04-01', 'target_date' => '2026-05-10']);

    expect($upcoming->monitoringStatus())->toBe(ActionPlanActivity::STATUS_UPCOMING)
        ->and($ongoing->monitoringStatus())->toBe(ActionPlanActivity::STATUS_ONGOING)
        ->and($overdue->monitoringStatus())->toBe(ActionPlanActivity::STATUS_OVERDUE);

    foreach ([$upcoming, $ongoing, $overdue] as $activity) {
        expect(ActionPlanActivity::withMonitoringStatus($activity->monitoringStatus())->pluck('id')->all())->toBe([$activity->id]);
    }
});

test('a participant submits an implementation report and the activity goes for review', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-02');
    $activity = actionPlanActivity($report);

    $this->actingAs($user)
        ->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload())
        ->assertSessionHasNoErrors();

    $implementationReport = $activity->fresh()->implementationReport;
    expect($implementationReport->status)->toBe(ImplementationReport::STATUS_PENDING)
        ->and((float) $implementationReport->actual_cost)->toBe(4500.0)
        ->and($activity->fresh()->monitoringStatus())->toBe(ActionPlanActivity::STATUS_FOR_REVIEW);
    Storage::disk('public')->assertExists($implementationReport->file_path);
});

test('an implementation report needs proof and cannot be dated in the future', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-03');
    $activity = actionPlanActivity($report);

    $this->actingAs($user)
        ->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload(['file' => null, 'date_completed' => '2026-06-01']))
        ->assertSessionHasErrors(['file', 'date_completed']);
});

test('an approved implementation report can no longer be replaced', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-04');
    $activity = actionPlanActivity($report);
    $this->actingAs($user)->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload());
    $activity->implementationReport()->update(['status' => ImplementationReport::STATUS_APPROVED]);

    $this->actingAs($user)
        ->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload())
        ->assertForbidden();
});

test('a participant cannot report on someone else\'s activity', function () {
    ['report' => $report, 'batch' => $batch] = actionPlanSetup('EMP-AP-05');
    $activity = actionPlanActivity($report);
    ['user' => $otherUser] = actionPlanSetup('EMP-AP-06');
    Participant::create(['sort_order' => 2, 'batch_id' => $batch->id, 'empcode' => 'EMP-AP-06', 'attendance' => 'Complete', 'hours' => 40, 'added_by' => 'system']);

    $this->actingAs($otherUser)
        ->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload())
        ->assertNotFound();
});

test('a participant can add an activity after submitting the plan and remove it while it has no report', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-07');

    $this->actingAs($user)
        ->post(route('programs.action-plan.activities.store', $batch), [
            'activity' => 'Mentoring sessions', 'expected_output' => '6 sessions', 'start_date' => '2026-06-01', 'target_date' => '2026-09-30', 'budget' => null,
        ])
        ->assertSessionHasNoErrors();

    $added = $report->activities()->first();
    expect($added->added_after_submission)->toBeTrue();

    $this->actingAs($user)->delete(route('programs.action-plan.activities.destroy', [$batch, $added]))->assertSessionHasNoErrors();
    expect(ActionPlanActivity::count())->toBe(0);
});

test('activities from the submitted form cannot be removed from the tracker', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-08');
    $activity = actionPlanActivity($report);

    $this->actingAs($user)->delete(route('programs.action-plan.activities.destroy', [$batch, $activity]))->assertForbidden();
});

test('the target date cannot be before the start date', function () {
    ['user' => $user, 'batch' => $batch] = actionPlanSetup('EMP-AP-09');

    $this->actingAs($user)
        ->post(route('programs.action-plan.activities.store', $batch), [
            'activity' => 'Mentoring', 'expected_output' => '6 sessions', 'start_date' => '2026-06-01', 'target_date' => '2026-05-01',
        ])
        ->assertSessionHasErrors('target_date');
});

test('activities cannot be added before a re-entry action plan is submitted', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-10');
    $report->update(['reap_summary' => null]);

    $this->actingAs($user)
        ->post(route('programs.action-plan.activities.store', $batch), [
            'activity' => 'Mentoring', 'expected_output' => '6 sessions', 'start_date' => '2026-06-01', 'target_date' => '2026-07-01',
        ])
        ->assertForbidden();
});

test('the program page lists the participant\'s action plan for the tracker', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-11');
    actionPlanActivity($report);

    $this->actingAs($user)
        ->get(route('programs.my-progress', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.can_add_action_plan_activity', true)
            ->where('program.action_plan.0.activity', 'Echo training to unit staff')
            ->where('program.action_plan.0.status', ActionPlanActivity::STATUS_ONGOING)
            ->where('program.action_plan.0.schedule', 'May 1 – June 30, 2026')
        );
});

test('admins see every activity on the monitoring page and can filter by status', function () {
    ['report' => $reportA] = actionPlanSetup('EMP-AP-12');
    ['report' => $reportB] = actionPlanSetup('EMP-AP-13', 'Region IV-A');
    actionPlanActivity($reportA, ['start_date' => '2026-04-01', 'target_date' => '2026-05-10']);
    actionPlanActivity($reportB);
    $admin = User::factory()->create(['empcode' => 'EMP-AP-ADMIN', 'access' => 'admin']);

    $this->actingAs($admin)->get(route('action-plan-monitoring.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ActionPlanMonitoring/Index')
            ->has('activities.data', 2)
            ->where('stats.total', 2)
            ->where('stats.by_status.'.ActionPlanActivity::STATUS_OVERDUE, 1)
            ->where('stats.planned_budget', 10000)
        );

    $this->actingAs($admin)->get(route('action-plan-monitoring.index', ['status' => ActionPlanActivity::STATUS_OVERDUE]))
        ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1)->where('activities.data.0.empcode', 'EMP-AP-12'));

    $this->actingAs($admin)->get(route('action-plan-monitoring.index', ['region' => 'Region IV-A']))
        ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1)->where('activities.data.0.empcode', 'EMP-AP-13'));
});

test('non-admins cannot open the monitoring page', function () {
    ['user' => $user] = actionPlanSetup('EMP-AP-14');

    $this->actingAs($user)->get(route('action-plan-monitoring.index'))->assertForbidden();
});

test('any admin can approve or return an implementation report, and returning needs a reason', function () {
    ['user' => $user, 'batch' => $batch, 'report' => $report] = actionPlanSetup('EMP-AP-15');
    $activity = actionPlanActivity($report);
    $this->actingAs($user)->post(route('programs.action-plan.report.submit', [$batch, $activity]), implementationReportPayload());
    $implementationReport = $activity->fresh()->implementationReport;
    $admin = User::factory()->create(['empcode' => 'EMP-AP-ANY-ADMIN', 'access' => 'admin', 'name' => 'Any Admin']);

    $this->actingAs($admin)
        ->patch(route('action-plan-monitoring.review', $implementationReport), ['status' => 'Rejected'])
        ->assertSessionHasErrors('remarks');

    $this->actingAs($admin)
        ->patch(route('action-plan-monitoring.review', $implementationReport), ['status' => 'Approved'])
        ->assertSessionHasNoErrors();

    expect($implementationReport->fresh()->status)->toBe('Approved')
        ->and($implementationReport->fresh()->reviewed_by)->toBe('Any Admin')
        ->and($activity->fresh()->monitoringStatus())->toBe(ActionPlanActivity::STATUS_COMPLETED);
});

test('resubmitting the report form keeps existing activities and never drops ones with a report under review', function () {
    ['user' => $user, 'batch' => $batch, 'participant' => $participant, 'report' => $report] = actionPlanSetup('EMP-AP-16');
    $requirement = Requirement::create(['batch_id' => $batch->id, 'title' => 'REAP', 'name' => 'REAP', 'due_date' => '2026-12-31', 'is_required' => true]);
    $report->update(['requirement_id' => $requirement->id]);
    $kept = actionPlanActivity($report, ['activity' => 'Keep me']);
    $locked = actionPlanActivity($report, ['activity' => 'Under review']);
    $removed = actionPlanActivity($report, ['activity' => 'Remove me']);
    $this->actingAs($user)->post(route('programs.action-plan.report.submit', [$batch, $locked]), implementationReportPayload());

    $this->actingAs($user)->put(route('programs.terminal-report.update', [$batch, $requirement]), [
        'program_description' => ['title' => 'Coaching', 'date_duration' => 'March 2-6, 2026', 'designed_for' => 'Staff'],
        'competencies_gained' => 'Coaching', 'reap_summary' => 'Coach my team', 'reap_objectives' => 'Better teams',
        'action_plan' => [
            ['id' => $kept->id, 'activity' => 'Keep me (edited)', 'expected_output' => 'Output', 'start_date' => '2026-05-01', 'target_date' => '2026-06-30', 'budget' => null],
            ['activity' => 'Brand new', 'expected_output' => 'Output', 'start_date' => '2026-07-01', 'target_date' => '2026-08-31', 'budget' => '1000'],
        ],
        'prepared_by' => ['name' => 'Ana Santos', 'position' => 'Specialist', 'date' => '2026-05-15'],
        'approved_by' => ['name' => 'Boss', 'position' => 'Chief'],
    ])->assertSessionHasNoErrors();

    $activities = $report->fresh()->activities;
    expect($activities->pluck('activity')->all())->toEqualCanonicalizing(['Keep me (edited)', 'Under review', 'Brand new'])
        ->and($activities->firstWhere('activity', 'Keep me (edited)')->id)->toBe($kept->id)
        ->and(ActionPlanActivity::find($removed->id))->toBeNull();
});
