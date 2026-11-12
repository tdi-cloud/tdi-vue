<?php

namespace Database\Factories;

use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormSection;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A required single-choice question with two choices.
 *
 * @extends Factory<PublicFormQuestion>
 */
class PublicFormQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_form_id' => PublicForm::factory(),
            'public_form_section_id' => fn (array $attributes) => PublicFormSection::factory()->create(['public_form_id' => $attributes['public_form_id']]),
            'answer_key' => fake()->unique()->slug(2, false),
            'type' => PublicFormDefinition::TYPE_SINGLE,
            'question' => fake()->sentence().'?',
            'question_fil' => null,
            'options' => [
                ['value' => 'yes', 'label' => 'Yes', 'label_fil' => 'Oo', 'score' => null, 'exclusive' => false],
                ['value' => 'no', 'label' => 'No', 'label_fil' => 'Hindi', 'score' => null, 'exclusive' => false],
            ],
            'allow_other' => false,
            'is_required' => true,
            'sort_order' => 0,
        ];
    }
}
