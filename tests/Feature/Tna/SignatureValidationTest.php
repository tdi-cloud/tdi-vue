<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function tnaValidSignature(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
}

function tnaUploadedJpegSignature(): string
{
    return 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes-for-testing');
}

function tnaMakeEmployee(string $empcode, string $position): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

test('self-rating store accepts a valid drawn signature', function () {
    $position = 'Test Officer 1';
    $employee = tnaMakeEmployee('EMP-001', $position);
    $supervisor = tnaMakeEmployee('EMP-002', 'Supervisor');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($user)->post(route('tna.self-rating.store'), [
        'name' => 'Juan D. Dela Cruz',
        'office' => 'Test Office',
        'division' => 'Test Section',
        'supervisor_empcode' => $supervisor->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'supervisor_position' => 'Supervisor',
        'signature' => tnaValidSignature(),
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('signature');
    $response->assertRedirect(route('tna.self-rating'));
    expect(TnaAssessment::where('user_id', $user->id)->first()?->signature)->toBe(tnaValidSignature());
});

test('self-rating store rejects a malformed signature', function () {
    $position = 'Test Officer 2';
    $employee = tnaMakeEmployee('EMP-003', $position);
    $supervisor = tnaMakeEmployee('EMP-004', 'Supervisor');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($user)->post(route('tna.self-rating.store'), [
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisor->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => 'Juan D. Dela Cruz',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('signature');
});

test('supervisory store accepts a valid drawn signature', function () {
    $position = 'Test Officer 3';
    $employee = tnaMakeEmployee('EMP-005', $position);
    $supervisorEmployee = tnaMakeEmployee('EMP-006', 'Supervisor');
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
        'office' => 'Test Office',
        'division' => 'Test Section',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'supervisor_position' => 'Supervisor',
        'signature' => tnaValidSignature(),
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
        'office' => 'Test Office',
        'division' => 'Test Section',
        'subordinate_name' => 'Juan D. Dela Cruz',
        'subordinate_position' => $position,
        'signature' => tnaValidSignature(),
        'fasd_empcode' => 'EMP-999',
        'fasd_name' => 'Ana FASD',
        'fasd_position' => 'FASD',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('tna.supervisory.index'));
    expect($assessment->fresh()->supervisor_form['signature'] ?? null)->toBe(tnaValidSignature());
});

test('supervisory store rejects a malformed signature', function () {
    $position = 'Test Officer 4';
    $employee = tnaMakeEmployee('EMP-007', $position);
    $supervisorEmployee = tnaMakeEmployee('EMP-008', 'Supervisor');
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
        'signature' => tnaValidSignature(),
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
        'signature' => 'Maria Supervisor',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('signature');
});

test('self-rating store accepts a blank signature', function () {
    $position = 'Test Officer 5';
    $employee = tnaMakeEmployee('EMP-009', $position);
    $supervisor = tnaMakeEmployee('EMP-010', 'Supervisor');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($user)->post(route('tna.self-rating.store'), [
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisor->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('signature');
    $response->assertRedirect(route('tna.self-rating'));
    expect(TnaAssessment::where('user_id', $user->id)->first()?->signature)->toBeNull();
});

test('self-rating store accepts an uploaded jpeg signature', function () {
    $position = 'Test Officer 6';
    $employee = tnaMakeEmployee('EMP-011', $position);
    $supervisor = tnaMakeEmployee('EMP-012', 'Supervisor');
    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element 1',
        'type' => 'core',
        'sort_order' => 1,
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $response = $this->actingAs($user)->post(route('tna.self-rating.store'), [
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisor->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => tnaUploadedJpegSignature(),
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('signature');
    expect(TnaAssessment::where('user_id', $user->id)->first()?->signature)->toBe(tnaUploadedJpegSignature());
});

test('supervisory store accepts a blank signature', function () {
    $position = 'Test Officer 7';
    $employee = tnaMakeEmployee('EMP-013', $position);
    $supervisorEmployee = tnaMakeEmployee('EMP-014', 'Supervisor');
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
        'fasd_empcode' => 'EMP-999',
        'fasd_name' => 'Ana FASD',
        'fasd_position' => 'FASD',
        'ratings' => [
            ['competency_id' => $competency->id, 'criticality' => 2, 'competence' => 3, 'frequency' => 2],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('signature');
    $response->assertRedirect(route('tna.supervisory.index'));
    expect($assessment->fresh()->supervisor_form['signature'] ?? null)->toBeNull();
});
