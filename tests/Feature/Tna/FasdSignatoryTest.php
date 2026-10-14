<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function fasdTestEmployee(string $empcode, string $position, string $region = 'NCR', string $lastname = 'Dela Cruz', string $plantillaStatus = 'PERMANENT'): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => $plantillaStatus,
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

test('fasd search returns employees of any position and region except job orders', function () {
    $supervisorEmployee = fasdTestEmployee('EMP-101', 'Supervisor', 'NCR');
    $sameRegionCandidate = fasdTestEmployee('EMP-102', 'FASD', 'NCR', 'Santos');
    $otherRegionCandidate = fasdTestEmployee('EMP-103', 'Administrative Officer', 'Region IV-A', 'Santos');
    $contractualCandidate = fasdTestEmployee('EMP-105', 'Clerk', 'NCR', 'Santos', 'CONTRACTUAL');
    $jobOrderCandidate = fasdTestEmployee('EMP-106', 'Clerk', 'NCR', 'Santos', 'JOB ORDER');
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $response = $this->actingAs($supervisorUser)
        ->getJson(route('tna.fasd.search').'?q=Santos');

    $response->assertOk();
    $empcodes = collect($response->json())->pluck('empcode');
    expect($empcodes)->toContain($sameRegionCandidate->EMPCODE);
    expect($empcodes)->toContain($otherRegionCandidate->EMPCODE);
    expect($empcodes)->toContain($contractualCandidate->EMPCODE);
    expect($empcodes)->not->toContain($jobOrderCandidate->EMPCODE);
});

test('fasd search includes employees with a blank plantilla status', function () {
    $supervisorEmployee = fasdTestEmployee('EMP-127', 'Supervisor', 'NCR');
    $blankStatusCandidate = fasdTestEmployee('EMP-129', 'Clerk', 'R5', 'Anipan', '');
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $response = $this->actingAs($supervisorUser)
        ->getJson(route('tna.fasd.search').'?q=Anipan');

    $response->assertOk();
    expect(collect($response->json())->pluck('empcode'))->toContain($blankStatusCandidate->EMPCODE);
});

test('fasd search includes the searching supervisor so they can sign themselves', function () {
    $supervisorEmployee = fasdTestEmployee('EMP-104', 'Supervisor', 'NCR', 'Reyes');
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $response = $this->actingAs($supervisorUser)
        ->getJson(route('tna.fasd.search').'?q=Reyes');

    $response->assertOk();
    expect(collect($response->json())->pluck('empcode'))->toContain($supervisorEmployee->EMPCODE);
});

test('supervisory store saves the selected fasd signatory', function () {
    $position = 'Test Officer FASD 1';
    $employee = fasdTestEmployee('EMP-105', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-106', 'Supervisor', 'NCR');
    $fasdEmployee = fasdTestEmployee('EMP-107', 'FASD', 'NCR', 'Santos');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);
    $assessment->ratings()->create([
        'competency_id' => $competency->id,
        'criticality' => 2,
        'competence' => 3,
        'frequency' => 2,
    ]);

    $response = $this->actingAs($supervisorUser)->post(route('tna.supervisory.store', $assessment), [
        'name' => 'Maria Supervisor',
        'subordinate_name' => 'Juan D. Dela Cruz',
        'subordinate_position' => $position,
        'signature' => null,
        'fasd_empcode' => $fasdEmployee->EMPCODE,
        'fasd_name' => 'Juan D. Santos',
        'fasd_position' => 'FASD',
        'fasd_office' => 'Test Office',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('tna.supervisory.index'));

    $supervisorForm = $assessment->fresh()->supervisor_form;
    expect($supervisorForm['fasd_empcode'] ?? null)->toBe($fasdEmployee->EMPCODE);
    expect($supervisorForm['fasd_name'] ?? null)->toBe('Juan D. Santos');
    expect($supervisorForm['fasd_position'] ?? null)->toBe('FASD');
    expect($supervisorForm['fasd_office'] ?? null)->toBe('Test Office');
});

test('supervisory store fails without selecting a fasd signatory', function () {
    $position = 'Test Officer FASD 2';
    $employee = fasdTestEmployee('EMP-108', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-109', 'Supervisor', 'NCR');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);
    $assessment->ratings()->create([
        'competency_id' => $competency->id,
        'criticality' => 2,
        'competence' => 3,
        'frequency' => 2,
    ]);

    $response = $this->actingAs($supervisorUser)->post(route('tna.supervisory.store', $assessment), [
        'name' => 'Maria Supervisor',
        'subordinate_name' => 'Juan D. Dela Cruz',
        'subordinate_position' => $position,
        'signature' => null,
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('fasd_empcode');
    expect($assessment->fresh()->supervisor_reviewed_at)->toBeNull();
});

test('supervisor can change the fasd signatory after the assessment is already reviewed', function () {
    $position = 'Test Officer FASD 3';
    $employee = fasdTestEmployee('EMP-110', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-111', 'Supervisor', 'NCR');
    $oldFasd = fasdTestEmployee('EMP-112', 'FASD', 'NCR', 'Santos');
    $newFasd = fasdTestEmployee('EMP-113', 'FASD', 'NCR', 'Cruz');
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
        'supervisor_form' => [
            'name' => 'Maria Supervisor',
            'subordinate_name' => 'Juan D. Dela Cruz',
            'subordinate_position' => $position,
            'signature' => null,
            'fasd_empcode' => $oldFasd->EMPCODE,
            'fasd_name' => 'Juan D. Santos',
            'fasd_position' => 'FASD',
            'fasd_office' => 'Test Office',
        ],
    ]);

    $response = $this->actingAs($supervisorUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $newFasd->EMPCODE,
        'fasd_name' => 'Juan D. Cruz',
        'fasd_position' => 'FASD',
        'fasd_office' => 'Test Office',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('tna.supervisory.index'));

    $supervisorForm = $assessment->fresh()->supervisor_form;
    expect($supervisorForm['fasd_empcode'] ?? null)->toBe($newFasd->EMPCODE);
    expect($supervisorForm['fasd_name'] ?? null)->toBe('Juan D. Cruz');
    // Hindi dapat nagbago ang ibang bahagi ng supervisor_form
    expect($supervisorForm['name'] ?? null)->toBe('Maria Supervisor');
});

test('noted by defaults to the supervisor and can be changed and reset', function () {
    $position = 'Test Officer FASD 6';
    $employee = fasdTestEmployee('EMP-121', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-122', 'Supervisor', 'NCR', 'Garcia');
    $supervisorEmployee->forceFill(['OFFICE/DIVISION' => 'ODDG-PO'])->save();
    $fasd = fasdTestEmployee('EMP-123', 'FASD', 'NCR', 'Santos');
    $noter = fasdTestEmployee('EMP-124', 'Director', 'NCR', 'Lopez');
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'supervisor_position' => 'Supervisor',
        'signature' => null,
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
        'supervisor_form' => [
            'name' => 'Maria Supervisor',
            'fasd_empcode' => $fasd->EMPCODE,
            'fasd_name' => 'Juan D. Santos',
        ],
    ]);

    $edit = $this->actingAs($supervisorUser)->get(route('tna.supervisory.fasd.edit', $assessment));
    $edit->assertOk();
    expect($edit->inertiaProps('noted.empcode'))->toBe($supervisorEmployee->EMPCODE);
    expect($edit->inertiaProps('noted.office'))->toBe('ODDG-PO');

    $this->actingAs($supervisorUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $fasd->EMPCODE,
        'fasd_name' => 'Juan D. Santos',
        'noted_empcode' => $noter->EMPCODE,
        'noted_name' => 'Juan D. Lopez',
        'noted_position' => 'Director',
        'noted_office' => 'Test Office',
    ])->assertSessionDoesntHaveErrors();

    $supervisorForm = $assessment->fresh()->supervisor_form;
    expect($supervisorForm['noted_empcode'])->toBe($noter->EMPCODE);
    expect($supervisorForm['noted_name'])->toBe('Juan D. Lopez');

    // Leaving the noted by fields empty falls back to the supervisor again
    $this->actingAs($supervisorUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $fasd->EMPCODE,
        'fasd_name' => 'Juan D. Santos',
    ])->assertSessionDoesntHaveErrors();

    expect($assessment->fresh()->supervisor_form['noted_empcode'])->toBeNull();
    $edit = $this->actingAs($supervisorUser)->get(route('tna.supervisory.fasd.edit', $assessment));
    expect($edit->inertiaProps('noted.empcode'))->toBe($supervisorEmployee->EMPCODE);
});

test('the tna result pdf renders with a custom noted by signatory', function () {
    $position = 'Test Officer FASD 7';
    $employee = fasdTestEmployee('EMP-125', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-126', 'Supervisor', 'NCR');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

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
        'supervisor_form' => [
            'name' => 'Maria Supervisor',
            'fasd_empcode' => 'EMP-999',
            'fasd_name' => 'Juan D. Santos',
            'noted_empcode' => 'EMP-998',
            'noted_name' => 'Juan D. Lopez',
            'noted_position' => 'Director',
            'noted_office' => 'Test Office',
        ],
    ]);

    $this->actingAs($user)->get(route('tna.result.pdf', $assessment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('updating the fasd signatory is blocked before the assessment is reviewed', function () {
    $position = 'Test Officer FASD 4';
    $employee = fasdTestEmployee('EMP-114', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-115', 'Supervisor', 'NCR');
    $newFasd = fasdTestEmployee('EMP-116', 'FASD', 'NCR', 'Cruz');
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($supervisorUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $newFasd->EMPCODE,
        'fasd_name' => 'Juan D. Cruz',
    ]);

    $response->assertNotFound();
});

test('updating the fasd signatory is restricted to the assigned supervisor', function () {
    $position = 'Test Officer FASD 5';
    $employee = fasdTestEmployee('EMP-117', $position, 'NCR');
    $supervisorEmployee = fasdTestEmployee('EMP-118', 'Supervisor', 'NCR');
    $otherEmployee = fasdTestEmployee('EMP-119', 'Someone Else', 'NCR', 'Reyes');
    $newFasd = fasdTestEmployee('EMP-120', 'FASD', 'NCR', 'Cruz');
    $subordinateUser = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);
    $otherUser = User::factory()->create(['empcode' => $otherEmployee->EMPCODE]);

    $assessment = TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
        'supervisor_reviewed_at' => now(),
    ]);

    $response = $this->actingAs($otherUser)->patch(route('tna.supervisory.fasd.update', $assessment), [
        'fasd_empcode' => $newFasd->EMPCODE,
        'fasd_name' => 'Juan D. Cruz',
    ]);

    $response->assertForbidden();
});
