<?php

namespace App\Support;

use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSection;

/**
 * TDI's registered Program Evaluation Form (TESDA-OP-AS-01-F05, Rev. No. 00 –
 * 03/01/17), transcribed verbatim, so a newly set-up batch evaluation works
 * out of the box and matches the official document — and can still be
 * edited from there.
 */
final class EvaluationDefaults
{
    /**
     * @return array<int, array{key: string, title: string, description: ?string, questions: array<int, array{type: string, label: string, options?: array<int, string>, is_required: bool}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => EvaluationSection::KEY_CONTENT,
                'title' => 'I. Content',
                'description' => null,
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '1. Objectives were clearly explained', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '2. Objectives stated were met', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '3. I understand the materials and topics in this program', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '4. Content is relevant to my job', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'a. What topics were clearly explained?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'b. What issues remain confusing / unclear?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'c. Very important topics that I wished shall have been discussed', 'is_required' => true],
                ],
            ],
            [
                'key' => EvaluationSection::KEY_METHODOLOGY,
                'title' => 'II. Methodology',
                'description' => 'The following activities/materials helped me to understand the content and achieve the stated objectives.',
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '5. Pre-work received prior to program', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => "6. Participant's workbook / worksheets", 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '7. Class discussions', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '8. Exercises and/or readings/activities', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '9. Audio/Visuals (flip charts, videos, etc.)', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'a. It would help me if', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_RADIO, 'label' => 'b. The pacing of the program is:', 'options' => ['just right', 'too slow', 'too fast'], 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_RADIO, 'label' => 'c. The degree of involvement of the participants is:', 'options' => ['not enough', 'too much', 'just right'], 'is_required' => true],
                ],
            ],
            [
                'key' => EvaluationSection::KEY_ENVIRONMENT,
                'title' => 'III. Environment Administration',
                'description' => null,
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '10. Are Exercises and/or readings/activities relevant to the training?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '11. Is the training room and facilities conducive to learning?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '12. Is the training room well ventilated (A/C and Electric Fan)', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '13. Is the sound system working properly?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '14. Are meals served nutritious/healthy and worth with its cost?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '15. Is the comfort room clean and sanitized?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '16. Is the dormitory room/s clean and comfortable to stay/sleep in?', 'is_required' => true],
                ],
            ],
            [
                'key' => EvaluationSection::KEY_FACILITATORS,
                'title' => 'IV. Facilitator',
                'description' => null,
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '17. Appeared knowledgeable of the subject matter', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '18. Presented clearly to assist my understanding', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '19. Promoted discussion and involvement', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '20. Responded appropriately to questions', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '21. Effectively managed group dynamics', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_LIKERT5, 'label' => '22. Kept the discussion/activities focused on stated objectives', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'Your comments, please:', 'is_required' => true],
                ],
            ],
            [
                'key' => EvaluationSection::KEY_PLANNED_ACTIONS,
                'title' => 'V. Planned Actions',
                'description' => null,
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => '23. As a result of this program, what will you do differently?', 'is_required' => true],
                ],
            ],
            [
                'key' => EvaluationSection::KEY_OVERALL,
                'title' => 'VI. Overall Program Rating',
                'description' => null,
                'questions' => [
                    ['type' => EvaluationQuestion::TYPE_SCALE10, 'label' => '18. My overall rating for this program', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'Your comments, please:', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'What may keep you from applying what you have learned in this program?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'Which target group is best suited for this program? Could you recommend specific individuals to attend?', 'is_required' => true],
                    ['type' => EvaluationQuestion::TYPE_TEXT, 'label' => 'Please share any information you believe would help us to improve this program.', 'is_required' => true],
                ],
            ],
        ];
    }

    public static function seedInto(EvaluationForm $form): void
    {
        foreach (self::sections() as $sortIndex => $sectionData) {
            $section = $form->sections()->create([
                'key' => $sectionData['key'],
                'title' => $sectionData['title'],
                'description' => $sectionData['description'],
                'sort_order' => $sortIndex,
            ]);

            foreach ($sectionData['questions'] as $questionSortIndex => $question) {
                $section->questions()->create([
                    'type' => $question['type'],
                    'label' => $question['label'],
                    'options' => $question['options'] ?? null,
                    'is_required' => $question['is_required'],
                    'sort_order' => $questionSortIndex,
                ]);
            }
        }
    }
}
