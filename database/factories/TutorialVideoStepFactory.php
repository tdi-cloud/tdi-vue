<?php

namespace Database\Factories;

use App\Models\TutorialVideo;
use App\Models\TutorialVideoStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorialVideoStep>
 */
class TutorialVideoStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tutorial_video_id' => TutorialVideo::factory(),
            'sort_order' => 0,
            'title' => 'Click '.fake()->words(2, true),
            'body' => fake()->sentence(),
            'image_path' => null,
        ];
    }
}
