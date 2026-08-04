<?php

use App\Models\Batch;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSection;
use App\Models\Program;

function evalSeedProgram(): Program
{
    return Program::create([
        'title' => 'Evaluation Seed Test Program',
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

function evalSeedBatch(Program $program): Batch
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

/**
 * Regression guard for the client-approved default question bank (based on
 * the registered TESDA-OP-AS-01-F05 form) so it never silently drifts.
 */
test('seeding defaults reproduces the approved question bank exactly', function () {
    $batch = evalSeedBatch(evalSeedProgram());
    $form = EvaluationForm::create(['batch_id' => $batch->id, 'slug' => EvaluationForm::generateSlugFor($batch)]);
    $form->seedDefaults();

    $form->load('sections.questions');

    expect($form->sections)->toHaveCount(6);
    expect($form->sections->pluck('key')->all())->toBe([
        EvaluationSection::KEY_CONTENT,
        EvaluationSection::KEY_METHODOLOGY,
        EvaluationSection::KEY_ENVIRONMENT,
        EvaluationSection::KEY_FACILITATORS,
        EvaluationSection::KEY_PLANNED_ACTIONS,
        EvaluationSection::KEY_OVERALL,
    ]);

    $content = $form->sections->firstWhere('key', EvaluationSection::KEY_CONTENT);
    expect($content->title)->toBe('I. Content');
    expect($content->questions->pluck('label')->all())->toBe([
        '1. Objectives were clearly explained',
        '2. Objectives stated were met',
        '3. I understand the materials and topics in this program',
        '4. Content is relevant to my job',
        'a. What topics were clearly explained?',
        'b. What issues remain confusing / unclear?',
        'c. Very important topics that I wished shall have been discussed',
    ]);
    expect($content->questions->where('type', EvaluationQuestion::TYPE_LIKERT5)->count())->toBe(4);
    expect($content->questions->where('type', EvaluationQuestion::TYPE_TEXT)->count())->toBe(3);

    $methodology = $form->sections->firstWhere('key', EvaluationSection::KEY_METHODOLOGY);
    expect($methodology->title)->toBe('II. Methodology');
    expect($methodology->description)->toBe('The following activities/materials helped me to understand the content and achieve the stated objectives.');
    expect($methodology->questions->pluck('label')->all())->toBe([
        '5. Pre-work received prior to program',
        "6. Participant's workbook / worksheets",
        '7. Class discussions',
        '8. Exercises and/or readings/activities',
        '9. Audio/Visuals (flip charts, videos, etc.)',
        'a. It would help me if',
        'b. The pacing of the program is:',
        'c. The degree of involvement of the participants is:',
    ]);
    $pacing = $methodology->questions->firstWhere('label', 'b. The pacing of the program is:');
    expect($pacing->type)->toBe(EvaluationQuestion::TYPE_RADIO);
    expect($pacing->options)->toBe(['just right', 'too slow', 'too fast']);
    $involvement = $methodology->questions->firstWhere('label', 'c. The degree of involvement of the participants is:');
    expect($involvement->type)->toBe(EvaluationQuestion::TYPE_RADIO);
    expect($involvement->options)->toBe(['not enough', 'too much', 'just right']);

    $environment = $form->sections->firstWhere('key', EvaluationSection::KEY_ENVIRONMENT);
    expect($environment->title)->toBe('III. Environment Administration');
    expect($environment->questions->pluck('label')->all())->toBe([
        '10. Are Exercises and/or readings/activities relevant to the training?',
        '11. Is the training room and facilities conducive to learning?',
        '12. Is the training room well ventilated (A/C and Electric Fan)',
        '13. Is the sound system working properly?',
        '14. Are meals served nutritious/healthy and worth with its cost?',
        '15. Is the comfort room clean and sanitized?',
        '16. Is the dormitory room/s clean and comfortable to stay/sleep in?',
    ]);
    expect($environment->questions->pluck('type')->unique()->all())->toBe([EvaluationQuestion::TYPE_LIKERT5]);

    $facilitators = $form->sections->firstWhere('key', EvaluationSection::KEY_FACILITATORS);
    expect($facilitators->title)->toBe('IV. Facilitator');
    expect($facilitators->questions->pluck('label')->all())->toBe([
        '17. Appeared knowledgeable of the subject matter',
        '18. Presented clearly to assist my understanding',
        '19. Promoted discussion and involvement',
        '20. Responded appropriately to questions',
        '21. Effectively managed group dynamics',
        '22. Kept the discussion/activities focused on stated objectives',
        'Your comments, please:',
    ]);
    $facilitatorComment = $facilitators->questions->firstWhere('label', 'Your comments, please:');
    expect($facilitatorComment->type)->toBe(EvaluationQuestion::TYPE_TEXT);

    $plannedActions = $form->sections->firstWhere('key', EvaluationSection::KEY_PLANNED_ACTIONS);
    expect($plannedActions->title)->toBe('V. Planned Actions');
    expect($plannedActions->questions->pluck('label')->all())->toBe([
        '23. As a result of this program, what will you do differently?',
    ]);

    $overall = $form->sections->firstWhere('key', EvaluationSection::KEY_OVERALL);
    expect($overall->title)->toBe('VI. Overall Program Rating');
    expect($overall->questions->pluck('label')->all())->toBe([
        '18. My overall rating for this program',
        'Your comments, please:',
        'What may keep you from applying what you have learned in this program?',
        'Which target group is best suited for this program? Could you recommend specific individuals to attend?',
        'Please share any information you believe would help us to improve this program.',
    ]);
    $ratingQuestion = $overall->questions->firstWhere('type', EvaluationQuestion::TYPE_SCALE10);
    expect($ratingQuestion->label)->toBe('18. My overall rating for this program');

    // Every seeded question is required (client asked that all questions require an answer).
    $form->sections->each(function ($section) {
        $section->questions->each(function ($question) use ($section) {
            expect($question->is_required)->toBeTrue("Expected '{$question->label}' in section '{$section->title}' to be required.");
        });
    });
});

test('the fixed rating scale legends match the registered form', function () {
    expect(EvaluationQuestion::LIKERT5_OPTIONS[5])->toContain('Strongly Agree');
    expect(EvaluationQuestion::LIKERT5_OPTIONS[4])->toContain('Agree');
    expect(EvaluationQuestion::LIKERT5_OPTIONS[3])->toContain('Disagree');
    expect(EvaluationQuestion::LIKERT5_OPTIONS[2])->toContain('Strongly Disagree');
    expect(EvaluationQuestion::LIKERT5_OPTIONS[1])->toContain('Not Applicable');

    expect(EvaluationQuestion::SCALE10_LEGEND)->toBe([
        '10 = Very Exceptional',
        '8-9 = Very Good',
        '6-7 = Satisfactory',
        '5 = Passing',
        '3-4 = Fair',
        '2 = Poor',
        '1 = Completely Unacceptable',
    ]);
});
