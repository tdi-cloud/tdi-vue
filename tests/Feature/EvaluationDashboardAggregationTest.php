<?php

use App\Models\Batch;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Participant;
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

    foreach (['EMP-A1', 'EMP-A2', 'EMP-A3', 'EMP-A4'] as $index => $empcode) {
        Participant::create([
            'sort_order' => $index,
            'batch_id' => $batchA->id,
            'empcode' => $empcode,
            'attendance' => $empcode === 'EMP-A4' ? 'Absent' : 'Complete',
            'hours' => 0,
            'added_by' => 'system',
        ]);
    }

    // A batch with a form but no submissions yet should still be listed.
    evalDashFormWithFacilitator(evalDashBatch($program, 'Batch C'), 'Facilitator C');

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
    expect($responsesPerBatch['Batch A']['participants'])->toBe(3); // absent participant excluded
    expect($responsesPerBatch['Batch C']['total'])->toBe(0);

    // ── Filtered to Batch A only ────────────────────────────────────────────
    $filtered = $this->actingAs($admin)
        ->getJson(route('programs.evaluation-dashboard', $program).'?batch_id='.$batchA->id)
        ->json();

    expect($filtered['total_responses'])->toBe(2);
    $filteredOverall = collect($filtered['avg_by_section'])->firstWhere('section_key', EvaluationSection::KEY_OVERALL);
    expect((float) $filteredOverall['avg_rating'])->toBe(9.0); // (8+10)/2

    // Responses per Batch always reflects every batch, even while filtered.
    $filteredResponsesPerBatch = collect($filtered['responses_per_batch'])->keyBy('batch_label');
    expect($filteredResponsesPerBatch['Batch A']['total'])->toBe(2);
    expect($filteredResponsesPerBatch['Batch B']['total'])->toBe(1);
});

test('non-admin users cannot view the evaluation dashboard data', function () {
    $user = User::factory()->create(['empcode' => 'EMP-EVDASH-02', 'access' => 'user']);
    $program = evalDashProgram();

    $this->actingAs($user)
        ->getJson(route('programs.evaluation-dashboard', $program))
        ->assertForbidden();
});

test('the findings say so when there are no responses yet', function () {
    $admin = evalDashAdmin('EMP-EVDASH-04');
    $program = evalDashProgram();
    evalDashFormWithFacilitator(evalDashBatch($program, 'Batch 1'), 'Facilitator A');

    $findings = $this->actingAs($admin)
        ->getJson(route('programs.evaluation-dashboard', $program))
        ->json('findings');

    expect($findings['intro'])->toEndWith('No responses have been received yet.')
        ->and($findings['items'])->toBe([]);
});

test('the dashboard includes the program evaluation findings and observations', function () {
    $admin = evalDashAdmin('EMP-EVDASH-05');
    $program = evalDashProgram();
    $batch = evalDashBatch($program, 'Batch 1');
    [$form, $facilitator] = evalDashFormWithFacilitator($batch, 'Juan Dela Cruz');

    foreach (['EMP-P1', 'EMP-P2', 'EMP-P3', 'EMP-P4'] as $index => $empcode) {
        Participant::create([
            'sort_order' => $index,
            'batch_id' => $batch->id,
            'empcode' => $empcode,
            'attendance' => $empcode === 'EMP-P4' ? 'Absent' : 'Complete',
            'hours' => 0,
            'added_by' => 'system',
        ]);
    }

    $content = $form->sections->firstWhere('key', EvaluationSection::KEY_CONTENT)->questions;
    $methodology = $form->sections->firstWhere('key', EvaluationSection::KEY_METHODOLOGY)->questions;
    $pacing = $methodology->firstWhere('type', EvaluationQuestion::TYPE_RADIO);

    foreach ([[10, 'just right'], [8, 'just right'], [6, 'too fast']] as [$overallRating, $pacingAnswer]) {
        $response = evalDashRecordResponse($form, $facilitator, $overallRating, 5);

        EvaluationAnswer::create(['evaluation_response_id' => $response->id, 'evaluation_question_id' => $content[0]->id, 'value_numeric' => 5]);
        EvaluationAnswer::create(['evaluation_response_id' => $response->id, 'evaluation_question_id' => $content[3]->id, 'value_numeric' => 4]);
        EvaluationAnswer::create(['evaluation_response_id' => $response->id, 'evaluation_question_id' => $pacing->id, 'value_text' => $pacingAnswer]);
        EvaluationAnswer::create(['evaluation_response_id' => $response->id, 'evaluation_question_id' => $methodology[0]->id, 'value_numeric' => 4]);
    }

    $findings = $this->actingAs($admin)->getJson(route('programs.evaluation-dashboard', $program))->json('findings');

    expect($findings['intro'])->toBe(
        'The training program was evaluated using feedback forms completed by the participants. '
        .'A total of three (3) responses were received out of the three (3) participants, reflecting a 100.0% response rate. '
        .'The overall rating given by the participants is broken down as follows:'
    );

    $items = collect($findings['items'])->pluck('text', 'label');

    expect($items['Content'])->toBe(
        '4.50 (out of 5) — Participants strongly agreed with the statements in this area. '
        .'“Objectives were clearly explained” received the highest rating at 5.00, while “Content is relevant to my job” (4.00) was rated the lowest.'
    );
    expect($items['Methodology'])->toContain('Most respondents indicated that the pacing of the program is just right (2 of 3).');
    expect($items['Facilitator'])->toStartWith('5.00 (out of 5) — The facilitator, Juan Dela Cruz, was rated very highly');
    expect($items['Overall Program Rating'])->toBe(
        'One (1) of 3 respondents (33.3%) rated the program “10 = Very Exceptional” and one (1) respondent (33.3%) rated it “8–9 = Very Good”. '
        .'The remaining one (1) respondent rated it “6–7 = Satisfactory” (1).'
    );
});
