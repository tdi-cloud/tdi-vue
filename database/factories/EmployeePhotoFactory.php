<?php

namespace Database\Factories;

use App\Models\EmployeePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeePhoto>
 */
class EmployeePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empcode' => fake()->unique()->numerify('####-####'),
            'path' => 'employee-profiles/'.Str::uuid().'.webp',
            'uploaded_by' => null,
        ];
    }
}
