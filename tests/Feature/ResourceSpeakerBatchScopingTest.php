<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\EvaluationForm;
use App\Models\Participant;
use App\Models\Program;
use App\Models\ResourceSpeaker;
use App\Models\User;

function rsAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function rsProgram(array $overrides = []): Program
{
    return Program::create(array_merge([
        'title' => 'Resource Speaker Scoping Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ], $overrides));
}

function rsBatch(Program $program, array $overrides = []): Batch
{
    return Batch::create(array_merge([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ], $overrides));
}

function rsEmployee(string $empcode): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
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

    return $employee;
}

test('admin can add a resource speaker scoped to a specific batch', function () {
    $admin = rsAdmin('EMP-RS-01');
    $program = rsProgram();
    $batch = rsBatch($program);

    $this->actingAs($admin)
        ->post(route('programs.resource-speakers.store', $program), [
            'name' => 'Juan Dela Cruz',
            'batch_id' => $batch->id,
        ])
        ->assertSessionDoesntHaveErrors();

    $speaker = ResourceSpeaker::first();
    expect($speaker->batch_id)->toBe($batch->id);
});

test('admin can add a general (program-wide) resource speaker with no batch', function () {
    $admin = rsAdmin('EMP-RS-02');
    $program = rsProgram();
    rsBatch($program);

    $this->actingAs($admin)
        ->post(route('programs.resource-speakers.store', $program), ['name' => 'General Speaker'])
        ->assertSessionDoesntHaveErrors();

    $speaker = ResourceSpeaker::first();
    expect($speaker->batch_id)->toBeNull();
});

test('a batch belonging to a different program cannot be used to scope a resource speaker', function () {
    $admin = rsAdmin('EMP-RS-03');
    $program = rsProgram();
    $otherProgram = rsProgram(['title' => 'Other Program']);
    $otherBatch = rsBatch($otherProgram);

    $this->actingAs($admin)
        ->post(route('programs.resource-speakers.store', $program), [
            'name' => 'Juan Dela Cruz',
            'batch_id' => $otherBatch->id,
        ])
        ->assertSessionHasErrors('batch_id');

    expect(ResourceSpeaker::count())->toBe(0);
});

test('an enrolled participant only sees resource speakers for their own batch, plus general ones', function () {
    $program = rsProgram();
    $batchA = rsBatch($program, ['batch' => 'Batch A']);
    $batchB = rsBatch($program, ['batch' => 'Batch B']);

    $general = ResourceSpeaker::create([
        'program_id' => $program->id,
        'program_code' => $program->program_code,
        'batch_id' => null,
        'name' => 'General Speaker',
    ]);
    $speakerA = ResourceSpeaker::create([
        'program_id' => $program->id,
        'program_code' => $program->program_code,
        'batch_id' => $batchA->id,
        'name' => 'Batch A Speaker',
    ]);
    $speakerB = ResourceSpeaker::create([
        'program_id' => $program->id,
        'program_code' => $program->program_code,
        'batch_id' => $batchB->id,
        'name' => 'Batch B Speaker',
    ]);

    $employee = rsEmployee('EMP-RS-04');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    Participant::create([
        'sort_order' => 1,
        'batch_id' => $batchA->id,
        'empcode' => $employee->EMPCODE,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);

    $response = $this->actingAs($user)->get(route('programs.my-progress', $batchA));

    $response->assertOk();
    $names = collect($response->inertiaProps('program')['resource_speakers'])->pluck('name');

    expect($names)->toContain('General Speaker')
        ->toContain('Batch A Speaker')
        ->not->toContain('Batch B Speaker');
});

test('adding an evaluation facilitator scopes the auto-created resource speaker to that facilitator\'s batch', function () {
    $admin = rsAdmin('EMP-RS-05');
    $program = rsProgram();
    $batch = rsBatch($program);
    $form = EvaluationForm::create([
        'batch_id' => $batch->id,
        'slug' => EvaluationForm::generateSlugFor($batch),
    ]);

    $this->actingAs($admin)
        ->post(route('evaluation-forms.facilitators.store', $form), ['name' => 'Facilitator One'])
        ->assertSessionDoesntHaveErrors();

    $facilitator = $form->facilitators()->first();
    $speaker = ResourceSpeaker::find($facilitator->resource_speaker_id);

    expect($speaker->batch_id)->toBe($batch->id);
});
