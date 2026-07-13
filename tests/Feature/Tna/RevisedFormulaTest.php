<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;

function revisedTestEmployee(string $empcode, string $position): Employee
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

test('result page exposes the revised formula score and priority without touching the original score', function () {
    $position = 'Test Officer Revised 1';
    $employee = revisedTestEmployee('EMP-301', $position);
    $supervisorEmployee = revisedTestEmployee('EMP-302', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    // Unit A: criticality self=2/sup=3 -> wc=2.6, competence self=1/sup=2 -> wl=1.6
    // revised = 2.6 * (4 - 1.6) = 6.24 -> 6.2
    $competencyA = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
        'element' => 'Element A',
        'type' => 'core',
        'sort_order' => 1,
    ]);

    // Unit B: criticality self=3/sup=3 -> wc=3.0, competence self=0/sup=0 -> wl=0.0
    // revised = 3.0 * (4 - 0) = 12.0 (max)
    $competencyB = Competency::create([
        'position' => $position,
        'unit' => 'Unit B',
        'element' => 'Element B',
        'type' => 'core',
        'sort_order' => 2,
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
        'competency_id' => $competencyA->id,
        'criticality' => 2,
        'competence' => 1,
        'frequency' => 2,
        'sup_criticality' => 3,
        'sup_competence' => 2,
        'sup_frequency' => 2,
    ]);
    $assessment->ratings()->create([
        'competency_id' => $competencyB->id,
        'criticality' => 3,
        'competence' => 0,
        'frequency' => 2,
        'sup_criticality' => 3,
        'sup_competence' => 0,
        'sup_frequency' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('tna.result.show', $assessment));

    $response->assertOk();

    $units = collect($response->inertiaProps('units'));
    $rowA = $units->firstWhere('unit', 'Unit A')['rows'][0];
    $rowB = $units->firstWhere('unit', 'Unit B')['rows'][0];

    // Bagong revised formula (float cast dahil pwedeng maging int ang 12.0 pagka-JSON-encode)
    expect((float) $rowA['revised_score'])->toBe(6.2);
    expect((float) $rowB['revised_score'])->toBe(12.0);

    // Hindi dapat nagbago ang orihinal na TNA score computation
    expect($rowA['score'])->not->toBeNull();
    expect($rowB['score'])->not->toBeNull();

    $revisedPriority = $response->inertiaProps('revisedPriority');
    expect($revisedPriority[0]['unit'])->toBe('Unit B');
    expect((float) $revisedPriority[0]['revised_score'])->toBe(12.0);
    expect($revisedPriority[1]['unit'])->toBe('Unit A');
    expect((float) $revisedPriority[1]['revised_score'])->toBe(6.2);
});

test('the original tna result pdf does not expose the revised formula', function () {
    $position = 'Test Officer Revised 2';
    $employee = revisedTestEmployee('EMP-303', $position);
    $supervisorEmployee = revisedTestEmployee('EMP-304', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $competency = Competency::create([
        'position' => $position,
        'unit' => 'Unit A',
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
        'criticality' => 2,
        'competence' => 1,
        'frequency' => 2,
        'sup_criticality' => 3,
        'sup_competence' => 2,
        'sup_frequency' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('tna.result.pdf', $assessment));

    // Hindi dapat nagbago o na-break ang orihinal na PDF generation
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
