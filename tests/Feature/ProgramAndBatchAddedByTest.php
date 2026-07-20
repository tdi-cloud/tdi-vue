<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\User;

function addedByTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function addedByProgramPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Added By Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ], $overrides);
}

test('creating a program records the empcode of the admin who added it', function () {
    $admin = addedByTestAdmin('EMP-ADDEDBY-01');

    $this->actingAs($admin)
        ->post(route('programs.store'), addedByProgramPayload())
        ->assertSessionDoesntHaveErrors();

    $program = Program::where('title', 'Added By Test Program')->firstOrFail();
    expect($program->added_by)->toBe('EMP-ADDEDBY-01');
});

test('creating a batch records the empcode of the admin who added it', function () {
    $admin = addedByTestAdmin('EMP-ADDEDBY-02');
    $program = Program::create(addedByProgramPayload(['added_by' => 'EMP-ADDEDBY-02']));

    $this->actingAs($admin)
        ->post(route('batches.store'), [
            'program_code' => $program->program_code,
            'batch' => 'Batch 1',
            'status' => 'Upcoming',
            'modality' => 'Onsite',
            'date_start' => now()->addDays(5)->toDateString(),
            'date_end' => now()->addDays(6)->toDateString(),
            'time_start' => '08:00',
            'time_end' => '17:00',
            'days' => '2',
            'hours' => '16',
        ])
        ->assertSessionDoesntHaveErrors();

    $batch = Batch::where('program_code', $program->program_code)->firstOrFail();
    expect($batch->added_by)->toBe('EMP-ADDEDBY-02');
});
