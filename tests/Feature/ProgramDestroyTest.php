<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\User;

function destroyTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

test('an admin can delete a program and its batches are removed too', function () {
    $admin = destroyTestAdmin('EMP-DEL-01');

    $program = Program::create([
        'title' => 'Program To Delete',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->toDateString(),
        'date_end' => now()->addDay()->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '1',
        'hours' => '8',
    ]);

    $response = $this->actingAs($admin)->delete(route('programs.destroy', $program));

    $response->assertRedirect(route('programs.index'));
    expect(Program::find($program->id))->toBeNull();
    expect(Batch::find($batch->id))->toBeNull();
});

test('non-admin users cannot delete a program', function () {
    $user = User::factory()->create(['empcode' => 'EMP-DEL-02', 'access' => 'user']);

    $program = Program::create([
        'title' => 'Protected Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $this->actingAs($user)->delete(route('programs.destroy', $program))->assertForbidden();
    expect(Program::find($program->id))->not->toBeNull();
});
