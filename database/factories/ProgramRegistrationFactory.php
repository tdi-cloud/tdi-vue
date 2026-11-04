<?php

namespace Database\Factories;

use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramRegistration>
 */
class ProgramRegistrationFactory extends Factory
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
            'batch_id' => fn (array $attributes) => RegistrationForm::find($attributes['registration_form_id'])->batch_id,
            'source' => ProgramRegistration::SOURCE_MANUAL,
            'empcode' => 'EMP-'.$this->faker->unique()->numerify('######'),
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
            'custom_answers' => [],
            'data_privacy_consent_at' => now(),
            'status' => ProgramRegistration::STATUS_PENDING,
        ];
    }

    public function forForm(RegistrationForm $form): static
    {
        return $this->state(fn () => [
            'registration_form_id' => $form->id,
            'batch_id' => $form->batch_id,
        ]);
    }

    public function listed(): static
    {
        return $this->state(fn () => ['source' => ProgramRegistration::SOURCE_LISTED]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ProgramRegistration::STATUS_APPROVED, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ProgramRegistration::STATUS_REJECTED,
            'remarks' => 'Not eligible for this batch.',
            'reviewed_at' => now(),
        ]);
    }
}
