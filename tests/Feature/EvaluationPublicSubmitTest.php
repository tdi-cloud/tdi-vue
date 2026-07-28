<?php

use App\Models\Batch;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Program;

function evalSubmitProgram(): Program
{
    return Program::create([
        'title' => 'Evaluation Submit Test Program',
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

function evalSubmitBatch(Program $program): Batch
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

function evalSubmitForm(): EvaluationForm
{
    $batch = evalSubmitBatch(evalSubmitProgram());
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();
    $form->facilitators()->create(['name' => 'Facilitator A', 'sort_order' => 0]);
    $form->facilitators()->create(['name' => 'Facilitator B', 'sort_order' => 1]);

    return $form->load('sections.questions', 'facilitators');
}

/**
 * Builds a fully-answered valid payload for the given form, so individual
 * tests only need to override the piece they're testing.
 */
function evalSubmitValidPayload(EvaluationForm $form): array
{
    $answers = [];
    $facilitatorAnswers = [];

    foreach ($form->sections as $section) {
        foreach ($section->questions as $question) {
            $value = match ($question->type) {
                EvaluationQuestion::TYPE_LIKERT5 => 5,
                EvaluationQuestion::TYPE_SCALE10 => 9,
                EvaluationQuestion::TYPE_CHECKBOX => [($question->options ?? ['ok'])[0]],
                default => 'Sample answer text.',
            };

            if ($section->key === EvaluationSection::KEY_FACILITATORS) {
                foreach ($form->facilitators as $facilitator) {
                    $facilitatorAnswers[$facilitator->id][$question->id] = $value;
                }
            } else {
                $answers[$question->id] = $value;
            }
        }
    }

    return [
        'email' => 'respondent@example.com',
        'empcode' => 'EMP-EVSUB-01',
        'respondent_name' => 'Juan Dela Cruz',
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
        'answers' => $answers,
        'facilitator_answers' => $facilitatorAnswers,
    ];
}

test('a fully-answered submission creates a response with the correct answer count', function () {
    $form = evalSubmitForm();
    $payload = evalSubmitValidPayload($form);

    $response = $this->post(route('evaluate.submit', $form->slug), $payload);

    $response->assertRedirect(route('evaluate.success', $form->slug));
    expect(EvaluationResponse::count())->toBe(1);

    $totalQuestions = $form->sections->flatMap->questions->count();
    $facilitatorSectionQuestions = $form->sections->firstWhere('key', EvaluationSection::KEY_FACILITATORS)->questions->count();
    $expectedAnswers = ($totalQuestions - $facilitatorSectionQuestions) + ($facilitatorSectionQuestions * $form->facilitators->count());

    expect(EvaluationAnswer::count())->toBe($expectedAnswers);
});

test('missing a required question fails validation and creates no rows', function () {
    $form = evalSubmitForm();
    $payload = evalSubmitValidPayload($form);

    $requiredQuestion = $form->sections
        ->firstWhere('key', EvaluationSection::KEY_CONTENT)
        ->questions
        ->firstWhere('is_required', true);
    unset($payload['answers'][$requiredQuestion->id]);

    $response = $this->post(route('evaluate.submit', $form->slug), $payload);

    $response->assertSessionHasErrors("answers.{$requiredQuestion->id}");
    expect(EvaluationResponse::count())->toBe(0);
    expect(EvaluationAnswer::count())->toBe(0);
});

test('checkbox answers persist as the selected subset', function () {
    $form = evalSubmitForm();
    $payload = evalSubmitValidPayload($form);

    $checkboxQuestion = $form->sections
        ->firstWhere('key', EvaluationSection::KEY_METHODOLOGY)
        ->questions
        ->firstWhere('type', EvaluationQuestion::TYPE_CHECKBOX);
    $payload['answers'][$checkboxQuestion->id] = ['too slow', 'too fast'];

    $this->post(route('evaluate.submit', $form->slug), $payload)->assertSessionDoesntHaveErrors();

    $answer = EvaluationAnswer::where('evaluation_question_id', $checkboxQuestion->id)->firstOrFail();
    expect(json_decode($answer->value_text, true))->toBe(['too slow', 'too fast']);
});

test('likert5 and scale10 answers reject out-of-range values', function () {
    $form = evalSubmitForm();

    $likertQuestion = $form->sections->firstWhere('key', EvaluationSection::KEY_CONTENT)->questions->firstWhere('type', EvaluationQuestion::TYPE_LIKERT5);
    $payload = evalSubmitValidPayload($form);
    $payload['answers'][$likertQuestion->id] = 99;
    $this->post(route('evaluate.submit', $form->slug), $payload)->assertSessionHasErrors("answers.{$likertQuestion->id}");

    $scaleQuestion = $form->sections->firstWhere('key', EvaluationSection::KEY_OVERALL)->questions->firstWhere('type', EvaluationQuestion::TYPE_SCALE10);
    $payload = evalSubmitValidPayload($form);
    $payload['answers'][$scaleQuestion->id] = 0;
    $this->post(route('evaluate.submit', $form->slug), $payload)->assertSessionHasErrors("answers.{$scaleQuestion->id}");

    expect(EvaluationResponse::count())->toBe(0);
});

test('submitting to an inactive form is rejected', function () {
    $form = evalSubmitForm();
    $form->update(['is_active' => false]);
    $payload = evalSubmitValidPayload($form);

    $this->post(route('evaluate.submit', $form->slug), $payload)->assertRedirect(route('evaluate.show', $form->slug));

    expect(EvaluationResponse::count())->toBe(0);
});
