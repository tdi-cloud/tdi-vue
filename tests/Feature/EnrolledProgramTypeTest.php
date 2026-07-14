<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;

test('the home page exposes program_type for each enrolled program', function () {
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => 'EMP-701',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);

    $program = Program::create([
        'title' => 'Test Program',
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
        'status' => 'Open',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
    Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $employee->EMPCODE,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $enrolled = $response->inertiaProps('enrolledPrograms');
    expect($enrolled)->toHaveCount(1);
    expect($enrolled[0]['program_type'])->toBe('TECHNICAL');
});
