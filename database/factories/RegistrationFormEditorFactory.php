<?php

namespace Database\Factories;

use App\Models\RegistrationForm;
use App\Models\RegistrationFormEditor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationFormEditor>
 */
class RegistrationFormEditorFactory extends Factory
{
    /**
     * Define the model's default state. The editor is a new admin unless one
     * is given with forUser().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_form_id' => RegistrationForm::factory(),
            'user_id' => User::factory()->state(fn () => [
                'empcode' => 'ADM-'.$this->faker->unique()->numerify('######'),
                'access' => 'admin',
            ]),
            'added_by_empcode' => null,
            'added_by_name' => 'System',
        ];
    }

    public function forForm(RegistrationForm $form): static
    {
        return $this->state(fn () => ['registration_form_id' => $form->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function addedBy(User $user): static
    {
        return $this->state(fn () => [
            'added_by_empcode' => $user->empcode,
            'added_by_name' => $user->name,
        ]);
    }
}
