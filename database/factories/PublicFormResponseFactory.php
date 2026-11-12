<?php

namespace Database\Factories;

use App\Models\PublicFormResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Learning and Development Survey responses by default (the survey is added
 * by a migration; an SG 15 employee, so without the LinkedIn Learning
 * questions).
 *
 * @extends Factory<PublicFormResponse>
 */
class PublicFormResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empcode = fake()->unique()->numerify('EMP-####');
        $firstname = fake()->firstName();
        $lastname = fake()->lastName();

        return [
            'form_slug' => 'ld-survey-2026',
            'empcode' => $empcode,
            'full_name' => "{$firstname} {$lastname}",
            'email' => fake()->unique()->safeEmail(),
            'region' => 'NCR',
            'office_division' => 'Training Division',
            'employee' => [
                'empcode' => $empcode,
                'lastname' => $lastname,
                'firstname' => $firstname,
                'mi' => null,
                'office_division' => 'Training Division',
                'position' => 'Training Specialist II',
                'sg' => '15',
                'plantilla_status' => 'Permanent',
                'sex' => 'F',
                'region' => 'NCR',
                'office' => 'TDI',
                'location' => 'Taguig',
                'section' => null,
                'unit' => null,
            ],
            'answers' => [
                'priority_areas' => ['leadership', 'digital_data_research', 'ethics'],
                'learning_approaches' => ['face_to_face', 'blended'],
                'motivation' => 'career_growth',
                'satisfaction' => '3',
                'barriers' => ['workload'],
                'supervisor_support' => '4',
                'improvement_area' => 'schedule_duration',
                'suggestion' => null,
                'overall_rating' => '3',
            ],
            'other_answers' => [],
            'confirmation_sent_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
        ];
    }
}
