<?php

namespace Database\Factories;

use App\Models\ProblemReport;
use App\Models\ProblemReportReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemReportReply>
 */
class ProblemReportReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'problem_report_id' => ProblemReport::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->sentence(),
        ];
    }
}
