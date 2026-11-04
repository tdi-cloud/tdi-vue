<?php

namespace Database\Factories;

use App\Models\ProvisionalEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProvisionalEmployee>
 */
class ProvisionalEmployeeFactory extends Factory
{
    /**
     * Define the model's default state. It only creates the holding record;
     * call insertEmployeeRow() to also add the employees row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_registration_id' => null,
            'user_id' => null,
            'empcode' => 'NEW-'.$this->faker->unique()->numerify('######'),
            'lastname' => $this->faker->lastName(),
            'firstname' => $this->faker->firstName(),
            'mi' => strtoupper($this->faker->randomLetter()),
            'office_division' => 'Training Division',
            'position' => 'Training Specialist I',
            'sg' => '11',
            'plantilla_status' => 'Permanent',
            'sex' => $this->faker->randomElement(['M', 'F']),
            'region' => 'NCR',
            'office' => 'TESDA Development Institute',
            'location' => null,
            'section' => null,
            'unit' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'contact_number' => '0917'.$this->faker->numerify('#######'),
            'status' => ProvisionalEmployee::STATUS_WAITING,
        ];
    }

    public function merged(): static
    {
        return $this->state(fn () => [
            'status' => ProvisionalEmployee::STATUS_MERGED,
            'merged_at' => now(),
            'deleted_at' => now(),
        ]);
    }
}
