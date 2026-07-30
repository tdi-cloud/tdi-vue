<?php

namespace Database\Factories;

use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemReport>
 */
class ProblemReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => $this->faker->paragraph(),
            'page_url' => $this->faker->url(),
            'status' => ProblemReport::STATUS_OPEN,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => ProblemReport::STATUS_RESOLVED]);
    }
}
