<?php

use App\Models\Batch;
use App\Models\EvaluationFacilitator;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSection;
use App\Models\Program;
use App\Models\User;

function evalCrudAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function evalCrudProgram(array $overrides = []): Program
{
    return Program::create(array_merge([
        'title' => 'Evaluation CRUD Test Program',
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

function evalCrudBatch(Program $program, array $overrides = []): Batch
{
    return Batch::create(array_merge([
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
    ], $overrides));
}

test('non-admin users cannot set up an evaluation form', function () {
    $user = User::factory()->create(['empcode' => 'EMP-EVCRUD-NA', 'access' => 'user']);
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);

    $this->actingAs($user)
        ->post(route('batches.evaluation-form.store', $batch), ['mode' => 'default'])
        ->assertForbidden();
});

test('admin can create a default-seeded evaluation form for a batch', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-01');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);

    $this->actingAs($admin)
        ->post(route('batches.evaluation-form.store', $batch), ['mode' => 'default'])
        ->assertSessionDoesntHaveErrors();

    $form = EvaluationForm::where('batch_id', $batch->id)->firstOrFail();
    expect($form->created_by_empcode)->toBe('EMP-EVCRUD-01');
    expect($form->sections()->count())->toBe(6);
    expect($form->sections()->pluck('key')->all())->toBe([
        EvaluationSection::KEY_CONTENT,
        EvaluationSection::KEY_METHODOLOGY,
        EvaluationSection::KEY_ENVIRONMENT,
        EvaluationSection::KEY_FACILITATORS,
        EvaluationSection::KEY_PLANNED_ACTIONS,
        EvaluationSection::KEY_OVERALL,
    ]);
});

test('a batch cannot have a second evaluation form', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-02');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);

    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();

    $this->actingAs($admin)
        ->post(route('batches.evaluation-form.store', $batch), ['mode' => 'default'])
        ->assertStatus(422);
});

test('admin can clone an evaluation form from a sibling batch', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-03');
    $program = evalCrudProgram(['title' => 'Clone Source Program']);
    $sourceBatch = evalCrudBatch($program, ['batch' => 'Batch A']);
    $targetBatch = evalCrudBatch($program, ['batch' => 'Batch B']);

    $sourceForm = EvaluationForm::create(['batch_id' => $sourceBatch->id, 'slug' => EvaluationForm::generateSlugFor($sourceBatch)]);
    $sourceForm->seedDefaults();
    $sourceForm->facilitators()->create(['name' => 'Juan Dela Cruz', 'role' => 'Resource Person/Facilitator', 'sort_order' => 0]);

    $this->actingAs($admin)
        ->post(route('batches.evaluation-form.store', $targetBatch), [
            'mode' => 'clone',
            'source_batch_id' => $sourceBatch->id,
        ])
        ->assertSessionDoesntHaveErrors();

    $targetForm = EvaluationForm::where('batch_id', $targetBatch->id)->firstOrFail();
    expect($targetForm->id)->not->toBe($sourceForm->id);
    expect($targetForm->sections()->count())->toBe($sourceForm->sections()->count());
    expect($targetForm->sections()->withCount('questions')->get()->sum('questions_count'))
        ->toBe($sourceForm->sections()->withCount('questions')->get()->sum('questions_count'));
    expect($targetForm->facilitators()->count())->toBe(1);
    expect($targetForm->facilitators()->first()->name)->toBe('Juan Dela Cruz');
});

test('admin can update evaluation form settings', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-04');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);

    $this->actingAs($admin)
        ->put(route('evaluation-forms.update', $form), [
            'title' => 'Updated Title',
            'intro_text' => 'Please answer honestly.',
            'is_active' => false,
        ])
        ->assertSessionDoesntHaveErrors();

    $form->refresh();
    expect($form->title)->toBe('Updated Title');
    expect($form->intro_text)->toBe('Please answer honestly.');
    expect($form->is_active)->toBeFalse();
});

test('admin can add, update, and delete a question in a section', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-05');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $section = $form->sections()->create(['key' => EvaluationSection::KEY_CONTENT, 'title' => 'I. Content', 'sort_order' => 0]);

    $this->actingAs($admin)
        ->post(route('evaluation-sections.questions.store', $section), [
            'type' => EvaluationQuestion::TYPE_CHECKBOX,
            'label' => 'How was the pacing?',
            'options' => ['too slow', 'just right', 'too fast'],
            'is_required' => true,
        ])
        ->assertSessionDoesntHaveErrors();

    $question = $section->questions()->firstOrFail();
    expect($question->options)->toBe(['too slow', 'just right', 'too fast']);

    $this->actingAs($admin)
        ->put(route('evaluation-questions.update', $question), [
            'type' => EvaluationQuestion::TYPE_TEXT,
            'label' => 'Updated label',
            'is_required' => false,
        ])
        ->assertSessionDoesntHaveErrors();

    $question->refresh();
    expect($question->label)->toBe('Updated label');
    expect($question->type)->toBe(EvaluationQuestion::TYPE_TEXT);
    expect($question->options)->toBeNull();

    $this->actingAs($admin)->delete(route('evaluation-questions.destroy', $question));
    expect(EvaluationQuestion::find($question->id))->toBeNull();
});

test('admin can add, reorder, and delete facilitators', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-06');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);

    $this->actingAs($admin)->post(route('evaluation-forms.facilitators.store', $form), ['name' => 'Facilitator A'])->assertSessionDoesntHaveErrors();
    $this->actingAs($admin)->post(route('evaluation-forms.facilitators.store', $form), ['name' => 'Facilitator B'])->assertSessionDoesntHaveErrors();

    $first = $form->facilitators()->orderBy('sort_order')->first();
    $second = $form->facilitators()->orderBy('sort_order')->skip(1)->first();
    expect($first->name)->toBe('Facilitator A');

    $this->actingAs($admin)->post(route('evaluation-facilitators.move-up', $second));

    expect(EvaluationFacilitator::find($first->id)->sort_order)->toBe(1);
    expect(EvaluationFacilitator::find($second->id)->sort_order)->toBe(0);

    $this->actingAs($admin)->delete(route('evaluation-facilitators.destroy', $second));
    expect(EvaluationFacilitator::find($second->id))->toBeNull();
});
