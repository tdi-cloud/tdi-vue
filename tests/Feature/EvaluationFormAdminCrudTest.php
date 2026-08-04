<?php

use App\Models\Batch;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationFacilitator;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Program;
use App\Models\ResourceSpeaker;
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

test('adding a facilitator also creates a matching resource speaker on the program, and keeps them in sync', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-12');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);

    $this->actingAs($admin)
        ->post(route('evaluation-forms.facilitators.store', $form), ['name' => 'Juan Dela Cruz', 'role' => 'Trainer'])
        ->assertSessionDoesntHaveErrors();

    $facilitator = $form->facilitators()->first();
    expect($facilitator->resource_speaker_id)->not->toBeNull();

    $resourceSpeaker = ResourceSpeaker::find($facilitator->resource_speaker_id);
    expect($resourceSpeaker)->not->toBeNull();
    expect($resourceSpeaker->program_id)->toBe($program->id);
    expect($resourceSpeaker->name)->toBe('Juan Dela Cruz');
    expect($resourceSpeaker->designation)->toBe('Trainer');

    // Editing the facilitator keeps the linked resource speaker in sync.
    $this->actingAs($admin)
        ->put(route('evaluation-facilitators.update', $facilitator), ['name' => 'Juan D. Cruz', 'role' => 'Lead Trainer'])
        ->assertSessionDoesntHaveErrors();

    expect($resourceSpeaker->fresh()->name)->toBe('Juan D. Cruz');
    expect($resourceSpeaker->fresh()->designation)->toBe('Lead Trainer');

    // Deleting the facilitator removes the linked resource speaker too.
    $this->actingAs($admin)->delete(route('evaluation-facilitators.destroy', $facilitator));

    expect(EvaluationFacilitator::find($facilitator->id))->toBeNull();
    expect(ResourceSpeaker::find($resourceSpeaker->id))->toBeNull();
});

test('a superadmin can delete an entire evaluation form to reset it', function () {
    $superadmin = User::factory()->create(['empcode' => 'EMP-EVCRUD-07', 'access' => 'superadmin']);
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();
    $sectionId = $form->sections()->first()->id;

    $this->actingAs($superadmin)
        ->delete(route('evaluation-forms.destroy', $form))
        ->assertSessionDoesntHaveErrors();

    expect(EvaluationForm::find($form->id))->toBeNull();
    expect(EvaluationSection::find($sectionId))->toBeNull();
});

test('a regular admin cannot delete an evaluation form', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-08');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);

    $this->actingAs($admin)
        ->delete(route('evaluation-forms.destroy', $form))
        ->assertForbidden();

    expect(EvaluationForm::find($form->id))->not->toBeNull();
});

test('admin can delete a single evaluation response, and its answers are removed too', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-09');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();

    $response = EvaluationResponse::create([
        'evaluation_form_id' => $form->id,
        'email' => 'respondent@example.com',
        'respondent_name' => 'Juan Dela Cruz',
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
    ]);
    $question = $form->sections()->first()->questions()->first();
    EvaluationAnswer::create([
        'evaluation_response_id' => $response->id,
        'evaluation_question_id' => $question->id,
        'value_numeric' => 5,
    ]);

    $this->actingAs($admin)
        ->delete(route('evaluation-forms.responses.destroy', [$form, $response]))
        ->assertSessionDoesntHaveErrors();

    expect(EvaluationResponse::find($response->id))->toBeNull();
    expect(EvaluationAnswer::where('evaluation_response_id', $response->id)->count())->toBe(0);
});

test('deleting a response that belongs to a different evaluation form returns 404', function () {
    $admin = evalCrudAdmin('EMP-EVCRUD-10');
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);

    $otherBatch = evalCrudBatch($program, ['batch' => 'Batch 2']);
    $otherForm = EvaluationForm::create(['batch_id' => $otherBatch->id, 'slug' => EvaluationForm::generateSlugFor($otherBatch)]);
    $response = EvaluationResponse::create([
        'evaluation_form_id' => $otherForm->id,
        'email' => 'respondent@example.com',
        'respondent_name' => 'Juan Dela Cruz',
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
    ]);

    $this->actingAs($admin)
        ->delete(route('evaluation-forms.responses.destroy', [$form, $response]))
        ->assertNotFound();

    expect(EvaluationResponse::find($response->id))->not->toBeNull();
});

test('non-admin users cannot delete an evaluation response', function () {
    $user = User::factory()->create(['empcode' => 'EMP-EVCRUD-11', 'access' => 'user']);
    $program = evalCrudProgram();
    $batch = evalCrudBatch($program);
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $response = EvaluationResponse::create([
        'evaluation_form_id' => $form->id,
        'email' => 'respondent@example.com',
        'respondent_name' => 'Juan Dela Cruz',
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
    ]);

    $this->actingAs($user)
        ->delete(route('evaluation-forms.responses.destroy', [$form, $response]))
        ->assertForbidden();

    expect(EvaluationResponse::find($response->id))->not->toBeNull();
});
