<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Program;
use App\Models\RegistrationForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegistrationForm>
 */
class RegistrationFormFactory extends Factory
{
    /**
     * Define the model's default state. Without a batch, it creates a
     * program and a batch to attach to (they have no factories of their own).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => fn () => $this->createBatch()->id,
            'slug' => fn () => 'reg-'.Str::lower(Str::random(10)),
            'title' => 'Program Registration',
            'intro_text' => null,
            'theme' => 'blue',
            'is_open' => true,
            'closes_at' => null,
            'slot_limit' => null,
        ];
    }

    public function forBatch(Batch $batch): static
    {
        return $this->state(fn () => ['batch_id' => $batch->id]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by_empcode' => $user->empcode,
            'created_by_name' => $user->name,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }

    public function pastDeadline(): static
    {
        return $this->state(fn () => ['closes_at' => now()->subHour()]);
    }

    public function withSlotLimit(int $slots): static
    {
        return $this->state(fn () => ['slot_limit' => $slots]);
    }

    private function createBatch(): Batch
    {
        $program = Program::create([
            'title' => 'Registration Test Program '.$this->faker->unique()->numberBetween(1, 99999),
            'modality' => 'Onsite',
            'pax' => '20',
            'category' => 'Regional',
            'type' => 'ADMIN',
            'initiated' => 'NTTA',
            'cost' => '0',
            'fund' => 'Test',
            'origin' => 'Local',
        ]);

        return Batch::create([
            'program_code' => $program->program_code,
            'batch' => 'Batch 1',
            'status' => 'Upcoming',
            'modality' => 'Onsite',
            'date_start' => now()->addDays(10)->toDateString(),
            'date_end' => now()->addDays(11)->toDateString(),
            'time_start' => '08:00',
            'time_end' => '17:00',
            'days' => '2',
            'hours' => '16',
        ]);
    }
}
