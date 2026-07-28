<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\EvaluationForm;
use App\Models\Participant;
use App\Models\Program;

function evalShowProgram(): Program
{
    return Program::create([
        'title' => 'Evaluation Show Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);
}

function evalShowBatch(Program $program): Batch
{
    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->addDays(5)->toDateString(),
        'date_end' => now()->addDays(6)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

test('an unknown slug returns 404', function () {
    $this->get(route('evaluate.show', 'does-not-exist'))->assertNotFound();
});

test('an inactive evaluation form renders a closed notice instead of a 404', function () {
    $batch = evalShowBatch(evalShowProgram());
    $form = EvaluationForm::create([
        'batch_id' => $batch->id,
        'slug' => EvaluationForm::generateSlugFor($batch),
        'is_active' => false,
    ]);
    $form->seedDefaults();

    $response = $this->get(route('evaluate.show', $form->slug));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Evaluation/Closed')
        ->where('form.slug', $form->slug)
    );
});

test('an active evaluation form renders publicly with sections, facilitators, and participants', function () {
    $batch = evalShowBatch(evalShowProgram());
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();
    $form->facilitators()->create(['name' => 'Resource Person One', 'sort_order' => 0]);

    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-EVSHOW-01',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Ramirez',
        'FIRSTNAME' => 'Liza',
        'MI' => 'T',
        'POSITION' => 'Trainer',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
    Participant::create(['batch_id' => $batch->id, 'empcode' => $employee->EMPCODE, 'attendance' => 'Present', 'added_by' => 'EMP-EVSHOW-ADMIN']);

    $response = $this->get(route('evaluate.show', $form->slug));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Evaluation/Form')
        ->where('form.slug', $form->slug)
        ->has('form.sections', 6)
        ->has('form.facilitators', 1)
        ->has('participants', 1)
        ->where('participants.0.name', $employee->name)
    );
});
