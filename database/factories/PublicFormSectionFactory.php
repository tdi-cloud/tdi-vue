<?php

namespace Database\Factories;

use App\Models\PublicForm;
use App\Models\PublicFormSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicFormSection>
 */
class PublicFormSectionFactory extends Factory
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
            'title' => ucfirst(fake()->words(2, true)),
            'step_label' => null,
            'description' => null,
            'min_salary_grade' => null,
            'sort_order' => 0,
        ];
    }
}
