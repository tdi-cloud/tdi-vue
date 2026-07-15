<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function summaryTestEmployee(string $empcode, string $position, string $region = 'NCR', string $office = 'Test Office'): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => $office,
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

function summaryTestAdmin(string $empcode): User
{
    summaryTestEmployee($empcode, 'HRMO', 'CO', 'Central Office');

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

/**
 * Gumagawa ng isang finalized (supervisor-reviewed) na TnaAssessment na may
 * isang "needs training" na unit (mababang score) para lumabas sa priority.
 */
function summaryTestFinalizedAssessment(string $empcode, string $position, ?string $region = null, ?string $office = null, ?int $id = null): TnaAssessment
{
    $employee = summaryTestEmployee($empcode, $position, $region ?? 'NCR', $office ?? 'Test Office');
    $supervisorEmployee = summaryTestEmployee($empcode.'-SUP', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit Needing Training - '.$empcode,
        'element' => 'Element A',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $assessment = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
        'supervisor_form' => ['name' => 'Maria Supervisor'],
    ]);

    $assessment->ratings()->create([
        'competency_id' => $competency->id,
        'criticality' => 1,
        'competence' => 0,
        'frequency' => 1,
        'sup_criticality' => 1,
        'sup_competence' => 0,
        'sup_frequency' => 1,
    ]);

    return $assessment;
}

test('tna summary lists only employees with a finalized tna result, with their top priorities', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM1');

    $finalized = summaryTestFinalizedAssessment('EMP-TSUM-01', 'Test Officer A');

    // Hindi pa reviewed ng supervisor - hindi dapat lumabas.
    $unfinishedEmployee = summaryTestEmployee('EMP-TSUM-02', 'Test Officer B');
    $unfinishedUser = User::factory()->create(['empcode' => $unfinishedEmployee->EMPCODE]);
    TnaAssessment::create([
        'user_id' => $unfinishedUser->id,
        'position' => 'Test Officer B',
        'period' => config('tna.period'),
        'name' => 'Pedro Santos',
        'supervisor_empcode' => 'EMP-TSUM-SUP',
        'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now(),
        'supervisor_reviewed_at' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('tna-summary.index'));
    $response->assertOk();

    $rows = collect($response->inertiaProps('assessments')['data']);

    expect($rows->pluck('id'))->toContain($finalized->id);
    expect($rows)->toHaveCount(1);
    expect($rows->first()['top_priorities'])->toHaveCount(1);
    expect($rows->first()['top_priorities'][0]['unit'])->toBe('Unit Needing Training - EMP-TSUM-01');
});

test('tna summary only shows the latest finalized assessment per employee', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM2');

    $employee = summaryTestEmployee('EMP-TSUM-03', 'Test Officer C');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $competency = Competency::create([
        'position' => 'Test Officer C',
        'unit' => 'Old Unit',
        'element' => 'Element A',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    $old = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => 'Test Officer C',
        'period' => '2020-2022',
        'name' => 'Juan Dela Cruz',
        'supervisor_empcode' => 'EMP-TSUM-SUP',
        'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now()->subYears(4),
        'supervisor_reviewed_at' => now()->subYears(4),
    ]);
    $old->ratings()->create([
        'competency_id' => $competency->id,
        'criticality' => 1, 'competence' => 0, 'frequency' => 1,
        'sup_criticality' => 1, 'sup_competence' => 0, 'sup_frequency' => 1,
    ]);

    // Parehong user_id (same employee) — mas bagong finalized na assessment.
    $newCompetency = Competency::create([
        'position' => 'Test Officer C',
        'unit' => 'New Unit',
        'element' => 'Element A',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $latest = TnaAssessment::create([
        'user_id' => $user->id,
        'position' => 'Test Officer C',
        'period' => config('tna.period'),
        'name' => 'Juan Dela Cruz',
        'supervisor_empcode' => 'EMP-TSUM-SUP',
        'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
    ]);
    $latest->ratings()->create([
        'competency_id' => $newCompetency->id,
        'criticality' => 1, 'competence' => 0, 'frequency' => 1,
        'sup_criticality' => 1, 'sup_competence' => 0, 'sup_frequency' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('tna-summary.index'));
    $response->assertOk();

    $rows = collect($response->inertiaProps('assessments')['data'])
        ->where('empcode', 'EMP-TSUM-03');

    expect($rows)->toHaveCount(1);
    expect($rows->first()['id'])->toBe($latest->id);
});

test('tna summary can be filtered by region and office', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM3');

    summaryTestFinalizedAssessment('EMP-TSUM-04', 'Test Officer D', 'Region IV-A', 'Regional Office');
    summaryTestFinalizedAssessment('EMP-TSUM-05', 'Test Officer E', 'NCR', 'Test Office');

    $response = $this->actingAs($admin)->get(route('tna-summary.index', ['region' => 'Region IV-A']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('assessments')['data']);
    expect($rows->pluck('empcode')->all())->toBe(['EMP-TSUM-04']);

    $response = $this->actingAs($admin)->get(route('tna-summary.index', ['office' => 'Regional Office']));
    $response->assertOk();

    $rows = collect($response->inertiaProps('assessments')['data']);
    expect($rows->pluck('empcode')->all())->toBe(['EMP-TSUM-04']);
});

test('tna summary exposes offices grouped by region for cascading filters', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM6');

    summaryTestFinalizedAssessment('EMP-TSUM-10', 'Test Officer J', 'Region IV-A', 'Regional Office IV-A');
    summaryTestFinalizedAssessment('EMP-TSUM-11', 'Test Officer K', 'NCR', 'Test Office');

    $response = $this->actingAs($admin)->get(route('tna-summary.index'));
    $response->assertOk();

    $officesByRegion = $response->inertiaProps('officesByRegion');

    expect($officesByRegion['Region IV-A'])->toBe(['Regional Office IV-A']);
    expect($officesByRegion['NCR'])->toContain('Test Office');
    expect($officesByRegion['NCR'])->not->toContain('Regional Office IV-A');
});

test('tna summary can be filtered by a unit of competency present in someones top 3', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM7');

    // Dalawang empleyado, parehong may "Shared Unit" sa kanilang top 3.
    $employeeA = summaryTestEmployee('EMP-TSUM-12', 'Test Officer L');
    $userA = User::factory()->create(['empcode' => $employeeA->EMPCODE]);
    $sharedCompetencyA = Competency::create([
        'position' => 'Test Officer L', 'unit' => 'Shared Unit', 'element' => 'Element A', 'type' => 'core', 'sort_order' => 1,
    ]);
    $assessmentA = TnaAssessment::create([
        'user_id' => $userA->id, 'position' => 'Test Officer L', 'period' => config('tna.period'),
        'name' => 'Employee A', 'supervisor_empcode' => 'EMP-TSUM-SUP', 'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now(), 'supervisor_reviewed_at' => now(),
    ]);
    $assessmentA->ratings()->create([
        'competency_id' => $sharedCompetencyA->id,
        'criticality' => 1, 'competence' => 0, 'frequency' => 1,
        'sup_criticality' => 1, 'sup_competence' => 0, 'sup_frequency' => 1,
    ]);

    $employeeB = summaryTestEmployee('EMP-TSUM-13', 'Test Officer M');
    $userB = User::factory()->create(['empcode' => $employeeB->EMPCODE]);
    $sharedCompetencyB = Competency::create([
        'position' => 'Test Officer M', 'unit' => 'Shared Unit', 'element' => 'Element A', 'type' => 'core', 'sort_order' => 1,
    ]);
    $assessmentB = TnaAssessment::create([
        'user_id' => $userB->id, 'position' => 'Test Officer M', 'period' => config('tna.period'),
        'name' => 'Employee B', 'supervisor_empcode' => 'EMP-TSUM-SUP', 'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now(), 'supervisor_reviewed_at' => now(),
    ]);
    $assessmentB->ratings()->create([
        'competency_id' => $sharedCompetencyB->id,
        'criticality' => 1, 'competence' => 0, 'frequency' => 1,
        'sup_criticality' => 1, 'sup_competence' => 0, 'sup_frequency' => 1,
    ]);

    // Isang empleyado na walang "Shared Unit" sa top 3 (competent na siya rito).
    $employeeC = summaryTestEmployee('EMP-TSUM-14', 'Test Officer N');
    $userC = User::factory()->create(['empcode' => $employeeC->EMPCODE]);
    $otherCompetencyC = Competency::create([
        'position' => 'Test Officer N', 'unit' => 'Unrelated Unit', 'element' => 'Element A', 'type' => 'core', 'sort_order' => 1,
    ]);
    $assessmentC = TnaAssessment::create([
        'user_id' => $userC->id, 'position' => 'Test Officer N', 'period' => config('tna.period'),
        'name' => 'Employee C', 'supervisor_empcode' => 'EMP-TSUM-SUP', 'supervisor_name' => 'Maria Supervisor',
        'submitted_at' => now(), 'supervisor_reviewed_at' => now(),
    ]);
    $assessmentC->ratings()->create([
        'competency_id' => $otherCompetencyC->id,
        'criticality' => 3, 'competence' => 4, 'frequency' => 3,
        'sup_criticality' => 3, 'sup_competence' => 4, 'sup_frequency' => 3,
    ]);

    $response = $this->actingAs($admin)->get(route('tna-summary.index', ['unit' => 'Shared Unit']));
    $response->assertOk();

    $names = collect($response->inertiaProps('assessments')['data'])->pluck('name');
    expect($names)->toContain('Employee A');
    expect($names)->toContain('Employee B');
    expect($names)->not->toContain('Employee C');
});

test('tna summary exposes distinct units of competency for the filter dropdown', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM8');

    summaryTestFinalizedAssessment('EMP-TSUM-15', 'Test Officer O');

    $response = $this->actingAs($admin)->get(route('tna-summary.index'));
    $response->assertOk();

    expect($response->inertiaProps('units'))->toContain('Unit Needing Training - EMP-TSUM-15');
});

test('tna summary dashboard data aggregates band distribution and top priorities', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM4');

    summaryTestFinalizedAssessment('EMP-TSUM-06', 'Test Officer F');
    summaryTestFinalizedAssessment('EMP-TSUM-07', 'Test Officer G');

    $response = $this->actingAs($admin)->getJson(route('tna-summary.dashboard-data'));
    $response->assertOk();

    expect($response->json('total_employees'))->toBe(2);
    expect($response->json('band_labels'))->toContain('Not Competent');

    $bandLabels = $response->json('band_labels');
    $bandSeries = $response->json('band_series');
    $notCompetentIndex = array_search('Not Competent', $bandLabels);
    expect($bandSeries[$notCompetentIndex])->toBe(2);

    expect($response->json('top_units'))->toContain('Unit Needing Training - EMP-TSUM-06');
});

test('non-admin users cannot access the tna summary page', function () {
    $employee = summaryTestEmployee('EMP-TSUM-08', 'Test Officer H');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('tna-summary.index'))->assertForbidden();
});

test('an admin can view any employees tna result page even without being the owner or supervisor', function () {
    $admin = summaryTestAdmin('EMP-TSUM-ADM5');
    $assessment = summaryTestFinalizedAssessment('EMP-TSUM-09', 'Test Officer I');

    $response = $this->actingAs($admin)->get(route('tna.result.show', $assessment));

    $response->assertOk();
});
