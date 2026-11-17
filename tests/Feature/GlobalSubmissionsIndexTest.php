<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Submission;
use App\Models\User;

function globalSubIndexAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function globalSubIndexEmployee(string $empcode, string $lastname, string $region = 'NCR'): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Division',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

/**
 * @return array{0: Program, 1: Batch, 2: Requirement}
 */
function globalSubIndexSetup(string $programTitle): array
{
    $program = Program::create([
        'title' => $programTitle,
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

function globalSubIndexSubmission(Program $program, Batch $batch, Requirement $requirement, string $empcode, string $status): Submission
{
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

test('admin can view submissions across multiple programs on the global submissions page', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-01');

    [$programA, $batchA, $reqA] = globalSubIndexSetup('Global Submissions Test Program A');
    [$programB, $batchB, $reqB] = globalSubIndexSetup('Global Submissions Test Program B');

    $empA = globalSubIndexEmployee('EMP-GSI-01', 'Santos');
    $empB = globalSubIndexEmployee('EMP-GSI-02', 'Reyes');

    globalSubIndexSubmission($programA, $batchA, $reqA, $empA->EMPCODE, 'Pending');
    globalSubIndexSubmission($programB, $batchB, $reqB, $empB->EMPCODE, 'Approved');

    $response = $this->actingAs($admin)->get(route('submissions.index'));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(2);
    expect($data->pluck('program.program_code')->sort()->values()->all())
        ->toBe(collect([$programA->program_code, $programB->program_code])->sort()->values()->all());

    $response->assertInertia(fn ($page) => $page
        ->missing('stats')
        ->loadDeferredProps(fn ($reload) => $reload
            ->where('stats.total', 2)
            ->where('stats.pending', 1)
            ->where('stats.approved', 1)));
});

test('status filter narrows the global submissions list', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-02');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Status Filter Program');

    $empPending = globalSubIndexEmployee('EMP-GSI-03', 'Cruz');
    $empApproved = globalSubIndexEmployee('EMP-GSI-04', 'Bautista');

    globalSubIndexSubmission($program, $batch, $requirement, $empPending->EMPCODE, 'Pending');
    globalSubIndexSubmission($program, $batch, $requirement, $empApproved->EMPCODE, 'Approved');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['status' => 'Pending']));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['status'])->toBe('Pending');
});

test('program filter narrows the global submissions list to one program', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-03');
    [$programA, $batchA, $reqA] = globalSubIndexSetup('Global Submissions Program Filter A');
    [$programB, $batchB, $reqB] = globalSubIndexSetup('Global Submissions Program Filter B');

    $empA = globalSubIndexEmployee('EMP-GSI-05', 'Garcia');
    $empB = globalSubIndexEmployee('EMP-GSI-06', 'Villanueva');

    globalSubIndexSubmission($programA, $batchA, $reqA, $empA->EMPCODE, 'Pending');
    globalSubIndexSubmission($programB, $batchB, $reqB, $empB->EMPCODE, 'Pending');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['program_code' => $programA->program_code]));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['program_code'])->toBe($programA->program_code);
});

test('search filter matches by employee name, empcode, or requirement title', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-04');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Search Program');

    $target = globalSubIndexEmployee('EMP-GSI-UNIQUE-07', 'Fernandez');
    $other = globalSubIndexEmployee('EMP-GSI-08', 'Torres');

    globalSubIndexSubmission($program, $batch, $requirement, $target->EMPCODE, 'Pending');
    globalSubIndexSubmission($program, $batch, $requirement, $other->EMPCODE, 'Pending');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['search' => 'Fernandez']));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['participant']['empcode'])->toBe($target->EMPCODE);
});

test('each submission exposes the participant attendance and justification for inline editing', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-05');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Attendance Program');

    $employee = globalSubIndexEmployee('EMP-GSI-09', 'Aquino');
    $submission = globalSubIndexSubmission($program, $batch, $requirement, $employee->EMPCODE, 'Pending');
    $submission->participant->update(['attendance' => 'Complete', 'hours' => 16]);

    $response = $this->actingAs($admin)->get(route('submissions.index'));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    $row = $data->first();
    expect($row['participant']['attendance'])->toBe('Complete');
    expect($row['participant']['hours'])->toEqual(16);
    expect($row['participant'])->toHaveKey('justification');
});

test('non-admin users cannot access the global submissions page', function () {
    $employee = globalSubIndexEmployee('EMP-GSI-REG-01', 'Reyes');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('submissions.index'))->assertForbidden();
});

test('a misspelled submissions search with no results suggests the closest known word', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-SUGGEST');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Suggestion Program');
    $employee = globalSubIndexEmployee('EMP-GSI-SUGGEST-01', 'Fernandez');
    globalSubIndexSubmission($program, $batch, $requirement, $employee->EMPCODE, 'Pending');

    $typo = $this->actingAs($admin)->get(route('submissions.index', ['search' => 'Fernandes']));
    expect($typo->inertiaProps('submissions')['data'])->toBeEmpty()
        ->and($typo->inertiaProps('suggestion'))->toBe('fernandez');

    $exact = $this->actingAs($admin)->get(route('submissions.index', ['search' => 'Fernandez']));
    expect($exact->inertiaProps('suggestion'))->toBeNull();
});

test('region filter narrows the global submissions list to employees of one region', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-RG');
    [$program, $batch, $requirement] = globalSubIndexSetup('Global Submissions Region Filter Program');

    $empNcr = globalSubIndexEmployee('EMP-GSI-RG-01', 'Mendoza', 'NCR');
    $empVii = globalSubIndexEmployee('EMP-GSI-RG-02', 'Aquino', 'Region VII');

    globalSubIndexSubmission($program, $batch, $requirement, $empNcr->EMPCODE, 'Pending');
    globalSubIndexSubmission($program, $batch, $requirement, $empVii->EMPCODE, 'Pending');

    $response = $this->actingAs($admin)->get(route('submissions.index', ['region' => 'Region VII']));
    $response->assertOk();

    $data = collect($response->inertiaProps('submissions')['data']);
    expect($data)->toHaveCount(1);
    expect($data->first()['participant']['empcode'])->toBe($empVii->EMPCODE);
    expect($response->inertiaProps('regions'))->toContain('NCR', 'Region VII');
    expect($response->inertiaProps('filters.region'))->toBe('Region VII');
    expect($response->inertiaProps('approvablePendingCount'))->toBe(1);
});

test('requirement filter narrows the list to one requirement title across programs, and lists every title to choose from', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-REQ1');
    [$programA, $batchA, $treapA] = globalSubIndexSetup('Global Submissions Requirement Filter A');
    [$programB, $batchB, $treapB] = globalSubIndexSetup('Global Submissions Requirement Filter B');
    $reapA = Requirement::create([
        'batch_id' => $batchA->id,
        'title' => 'REAP',
        'name' => Requirement::nameFor('REAP'),
        'due_date' => now()->addWeeks(2)->toDateString(),
        'is_required' => true,
    ]);

    globalSubIndexSubmission($programA, $batchA, $treapA, globalSubIndexEmployee('EMP-GSI-R1', 'Aquino')->EMPCODE, 'Pending');
    globalSubIndexSubmission($programB, $batchB, $treapB, globalSubIndexEmployee('EMP-GSI-R2', 'Mendoza')->EMPCODE, 'Approved');
    $reap = globalSubIndexSubmission($programA, $batchA, $reapA, globalSubIndexEmployee('EMP-GSI-R3', 'Ramos')->EMPCODE, 'Pending');

    $treapOnly = $this->actingAs($admin)->get(route('submissions.index', ['requirement' => 'TREAP']))->assertOk();

    expect(collect($treapOnly->inertiaProps('submissions')['data'])->pluck('requirement.title')->all())->toBe(['TREAP', 'TREAP'])
        ->and($treapOnly->inertiaProps('requirements'))->toBe([
            ['value' => 'REAP', 'label' => 'REAP — Terminal Report and Re-entry Action Plan'],
            ['value' => 'TREAP', 'label' => 'TREAP — Terminal Report'],
            ['value' => '__other__', 'label' => 'Other documents (no requirement)'],
        ]);

    $reapOnly = $this->actingAs($admin)->get(route('submissions.index', ['requirement' => 'REAP']))->assertOk();

    expect(collect($reapOnly->inertiaProps('submissions')['data'])->pluck('id')->all())->toBe([$reap->id])
        ->and($reapOnly->inertiaProps('filters.requirement'))->toBe('REAP');
});

test('the requirement filter keeps submissions of a deleted requirement under its title, and "Other documents" shows those sent without one', function () {
    $admin = globalSubIndexAdmin('EMP-GSI-ADM-REQ2');
    [$programA, $batchA, $treapA] = globalSubIndexSetup('Global Submissions Requirement Kept A');
    [$programB, $batchB, $treapB] = globalSubIndexSetup('Global Submissions Requirement Kept B');

    $live = globalSubIndexSubmission($programA, $batchA, $treapA, globalSubIndexEmployee('EMP-GSI-K1', 'Lopez')->EMPCODE, 'Pending');
    $kept = globalSubIndexSubmission($programB, $batchB, $treapB, globalSubIndexEmployee('EMP-GSI-K2', 'Navarro')->EMPCODE, 'Pending');
    $treapB->delete();

    $otherParticipant = Participant::create([
        'sort_order' => 2, 'batch_id' => $batchA->id, 'empcode' => globalSubIndexEmployee('EMP-GSI-K3', 'Pascual')->EMPCODE,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
    $otherDocument = Submission::create([
        'participant_id' => $otherParticipant->id,
        'program_code' => $programA->program_code,
        'batch_id' => $batchA->id,
        'requirement_id' => null,
        'document_title' => 'Certificate of Completion',
        'status' => 'Pending',
        'file_path' => 'submissions/certificate.pdf',
        'submitted_at' => now(),
    ]);

    expect($kept->fresh()->requirement_id)->toBeNull();

    $treap = $this->actingAs($admin)->get(route('submissions.index', ['requirement' => 'TREAP']));
    expect(collect($treap->inertiaProps('submissions')['data'])->pluck('id')->sort()->values()->all())->toBe(collect([$live->id, $kept->id])->sort()->values()->all());

    $other = $this->actingAs($admin)->get(route('submissions.index', ['requirement' => '__other__']));
    expect(collect($other->inertiaProps('submissions')['data'])->pluck('id')->all())->toBe([$otherDocument->id]);
});
