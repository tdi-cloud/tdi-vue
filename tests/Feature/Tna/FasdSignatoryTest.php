<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function fasdTestEmployee(string $empcode, string $position, string $region = 'NCR', string $lastname = 'Dela Cruz'): Employee
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
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => $region,
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

test('fasd search only returns employees in the supervisor\'s own region', function () {
    $supervisorEmployee = fasdTestEmployee('EMP-101', 'Supervisor', 'NCR');
    $sameRegionCandidate = fasdTestEmployee('EMP-102', 'FASD', 'NCR', 'Santos');
    $otherRegionCandidate = fasdTestEmployee('EMP-103', 'FASD', 'Region IV-A', 'Santos');
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $response = $this->actingAs($supervisorUser)
        ->getJson(route('tna.fasd.search').'?q=Santos');

    $response->assertOk();
    $empcodes = collect($response->json())->pluck('empcode');
    expect($empcodes)->toContain($sameRegionCandidate->EMPCODE);
    expect($empcodes)->not->toContain($otherRegionCandidate->EMPCODE);
});

test('fasd search excludes the searching supervisor themselves', function () {
    $supervisorEmployee = fasdTestEmployee('EMP-104', 'Supervisor', 'NCR', 'Reyes');
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);

    $response = $this->actingAs($supervisorUser)
        ->getJson(route('tna.fasd.search').'?q=Reyes');

    $response->assertOk();
    expect(collect($response->json())->pluck('empcode'))->not->toContain($supervisorEmployee->EMPCODE);
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
