<?php

namespace Database\Factories;

use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormSection;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open form without questions; use withQuestions() for a small one to answer.
 *
 * @extends Factory<PublicForm>
 */
class PublicFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true).' survey';

        return [
            'slug' => PublicForm::uniqueSlugFor($title),
            'title' => ucfirst($title),
            'kicker' => 'TESDA Development Institute',
            'description' => fake()->sentence(),
            'reference_prefix' => 'TST',
            'is_open' => true,
            'closes_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }

    public function pastDeadline(): static
    {
        return $this->state(fn () => ['closes_at' => now()->subDay()]);
    }

    public function withBackground(string $path = 'public-form-backgrounds/survey.jpg'): static
    {
        return $this->state(fn () => ['background_image' => $path]);
    }

    public function withoutBackground(): static
    {
        return $this->state(fn () => ['hide_background' => true]);
    }

    /**
     * One section with a single-choice question ("colour") and an optional text question ("comments").
     */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (PublicForm $form) {
            $section = PublicFormSection::factory()->for($form, 'form')->create(['title' => 'About you', 'sort_order' => 0]);

            PublicFormQuestion::factory()->for($form, 'form')->for($section, 'section')->create([
                'answer_key' => 'colour',
                'type' => PublicFormDefinition::TYPE_SINGLE,
                'question' => 'Which colour do you like best?',
                'options' => [
                    ['value' => 'blue', 'label' => 'Blue', 'label_fil' => 'Asul', 'score' => null, 'exclusive' => false],
                    ['value' => 'gold', 'label' => 'Gold', 'label_fil' => 'Ginto', 'score' => null, 'exclusive' => false],
                ],
                'sort_order' => 0,
            ]);

            PublicFormQuestion::factory()->for($form, 'form')->for($section, 'section')->create([
                'answer_key' => 'comments',
                'type' => PublicFormDefinition::TYPE_TEXT,
                'question' => 'Anything else?',
                'options' => null,
                'is_required' => false,
                'sort_order' => 1,
            ]);
        });
    }
}
