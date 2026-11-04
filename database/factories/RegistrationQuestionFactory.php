<?php

namespace Database\Factories;

use App\Models\RegistrationForm;
use App\Models\RegistrationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationQuestion>
 */
class RegistrationQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_form_id' => RegistrationForm::factory(),
            'label' => rtrim($this->faker->sentence(5), '.').'?',
            'help_text' => null,
            'type' => RegistrationQuestion::TYPE_TEXT,
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function choice(string $type, array $options = ['Yes', 'No']): static
    {
        return $this->state(fn () => ['type' => $type, 'options' => $options]);
    }
}
