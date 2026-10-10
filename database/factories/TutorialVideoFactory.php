<?php

namespace Database\Factories;

use App\Models\TutorialVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorialVideo>
 */
class TutorialVideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'How to '.fake()->words(3, true),
            'description' => fake()->sentence(),
            'video_path' => 'tutorial-videos/'.fake()->uuid().'.mp4',
            'thumbnail_path' => null,
            'uploaded_by' => null,
        ];
    }
}
