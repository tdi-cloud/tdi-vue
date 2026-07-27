<?php

use App\Models\Batch;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Program;
use App\Models\User;

function evalDashAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function evalDashProgram(): Program
{
    return Program::create([
        'title' => 'Evaluation Dashboard Test Program',
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

function evalDashBatch(Program $program, string $label): Batch
{
    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => $label,
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

function evalDashFormWithFacilitator(Batch $batch, string $facilitatorName): array
{
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();
    $facilitator = $form->facilitators()->create(['name' => $facilitatorName, 'sort_order' => 0]);

    return [$form->load('sections.questions'), $facilitator];
}

function evalDashRecordResponse(EvaluationForm $form, $facilitator, int $overallRating, int $facilitatorLikertRating): EvaluationResponse
{
    $response = EvaluationResponse::create([
        'evaluation_form_id' => $form->id,
        'email' => fake()->unique()->safeEmail(),
        'respondent_name' => fake()->name(),
        'name_source' => EvaluationResponse::SOURCE_MANUAL,
    ]);

    $overallQuestion = $form->sections->firstWhere('key', EvaluationSection::KEY_OVERALL)
        ->questions->firstWhere('type', EvaluationQuestion::TYPE_SCALE10);

    EvaluationAnswer::create([
        'evaluation_response_id' => $response->id,
        'evaluation_question_id' => $overallQuestion->id,
        'value_numeric' => $overallRating,
    ]);

    $facilitatorQuestion = $form->sections->firstWhere('key', EvaluationSection::KEY_FACILITATORS)
        ->questions->firstWhere('type', EvaluationQuestion::TYPE_LIKERT5);

    EvaluationAnswer::create([
        'evaluation_response_id' => $response->id,
        'evaluation_question_id' => $facilitatorQuestion->id,
        'evaluation_facilitator_id' => $facilitator->id,
        'value_numeric' => $facilitatorLikertRating,
    ]);

    return $response;
}

test('the dashboard aggregates ratings correctly across all batches and when filtered to one', function () {
    $admin = evalDashAdmin('EMP-EVDASH-01');
    $program = evalDashProgram();

    $batchA = evalDashBatch($program, 'Batch A');
    [$formA, $facilitatorA] = evalDashFormWithFacilitator($batchA, 'Facilitator A');
    evalDashRecordResponse($formA, $facilitatorA, 8, 5);
    evalDashRecordResponse($formA, $facilitatorA, 10, 5);

    $batchB = evalDashBatch($program, 'Batch B');
    [$formB, $facilitatorB] = evalDashFormWithFacilitator($batchB, 'Facilitator B');
    evalDashRecordResponse($formB, $facilitatorB, 6, 3);

    // ── Combined (all batches) ──────────────────────────────────────────────
    $combined = $this->actingAs($admin)->getJson(route('programs.evaluation-dashboard', $program))->json();

    expect($combined['total_responses'])->toBe(3);

    $overallSection = collect($combined['avg_by_section'])->firstWhere('section_key', EvaluationSection::KEY_OVERALL);
    expect((float) $overallSection['avg_rating'])->toBe(8.0); // (8+10+6)/3

    $facilitatorRatings = collect($combined['avg_by_facilitator'])->keyBy('name');
    expect((float) $facilitatorRatings['Facilitator A']['avg_rating'])->toBe(5.0);
    expect((float) $facilitatorRatings['Facilitator B']['avg_rating'])->toBe(3.0);

    $responsesPerBatch = collect($combined['responses_per_batch'])->keyBy('batch_label');
    expect($responsesPerBatch['Batch A']['total'])->toBe(2);
    expect($responsesPerBatch['Batch B']['total'])->toBe(1);

    // ── Filtered to Batch A only ────────────────────────────────────────────
    $filtered = $this->actingAs($admin)
        ->getJson(route('programs.evaluation-dashboard', $program).'?batch_id='.$batchA->id)
        ->json();

    expect($filtered['total_responses'])->toBe(2);
    $filteredOverall = collect($filtered['avg_by_section'])->firstWhere('section_key', EvaluationSection::KEY_OVERALL);
    expect((float) $filteredOverall['avg_rating'])->toBe(9.0); // (8+10)/2
});

test('non-admin users cannot view the evaluation dashboard data', function () {
    $user = User::factory()->create(['empcode' => 'EMP-EVDASH-02', 'access' => 'user']);
    $program = evalDashProgram();

    $this->actingAs($user)
        ->getJson(route('programs.evaluation-dashboard', $program))
        ->assertForbidden();
});
