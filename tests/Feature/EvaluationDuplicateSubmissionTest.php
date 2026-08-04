<?php

use App\Models\Batch;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Program;

function evalDupProgram(): Program
{
    return Program::create([
        'title' => 'Evaluation Duplicate Test Program',
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

function evalDupBatch(Program $program): Batch
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

function evalDupForm(): EvaluationForm
{
    $batch = evalDupBatch(evalDupProgram());
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();

    return $form->load('sections.questions', 'facilitators');
}

function evalDupPayload(EvaluationForm $form, array $overrides = []): array
{
    $answers = [];
    foreach ($form->sections as $section) {
        if ($section->key === EvaluationSection::KEY_FACILITATORS) {
            continue;
        }
        foreach ($section->questions as $question) {
            $answers[$question->id] = match ($question->type) {
                EvaluationQuestion::TYPE_LIKERT5 => 5,
                EvaluationQuestion::TYPE_SCALE10 => 9,
                EvaluationQuestion::TYPE_CHECKBOX => [($question->options ?? ['ok'])[0]],
                EvaluationQuestion::TYPE_RADIO => ($question->options ?? ['ok'])[0],
                default => 'Sample answer text.',
            };
        }
    }

    return array_merge([
        'email' => 'respondent@example.com',
        'respondent_name' => 'Juan Dela Cruz',
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
        'answers' => $answers,
        'facilitator_answers' => [],
    ], $overrides);
}

test('a second submission with the same empcode is blocked with no duplicate row created', function () {
    $form = evalDupForm();

    $this->post(route('evaluate.submit', $form->slug), evalDupPayload($form, ['empcode' => 'EMP-EVDUP-01']))
        ->assertRedirect(route('evaluate.success', $form->slug));

    $response = $this->post(route('evaluate.submit', $form->slug), evalDupPayload($form, ['empcode' => 'EMP-EVDUP-01', 'respondent_name' => 'Second Attempt']));

    $response->assertSessionHas('error');
    expect(EvaluationResponse::where('evaluation_form_id', $form->id)->count())->toBe(1);
    expect(EvaluationResponse::first()->respondent_name)->toBe('Juan Dela Cruz');
});

test('two submissions without an empcode both succeed', function () {
    $form = evalDupForm();

    $this->post(route('evaluate.submit', $form->slug), evalDupPayload($form))
        ->assertRedirect(route('evaluate.success', $form->slug));

    $this->post(route('evaluate.submit', $form->slug), evalDupPayload($form, ['email' => 'another@example.com', 'respondent_name' => 'Second Respondent']))
        ->assertRedirect(route('evaluate.success', $form->slug));

    expect(EvaluationResponse::where('evaluation_form_id', $form->id)->count())->toBe(2);
});
