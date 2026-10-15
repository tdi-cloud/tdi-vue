<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\User;

/**
 * @return array{0: Program, 1: Requirement}
 */
function updateReqSetup(string $title = 'Update Requirement Program'): array
{
    $program = Program::create([
        'title' => $title,
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => 'EMP-UPDREQ-CREATOR',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Done',
        'modality' => 'Onsite',
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->subDays(20)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addWeek()->toDateString(),
        'is_required' => true,
        'note' => 'Old note',
    ]);

    return [$program, $requirement];
}

test('an admin can edit a requirement record', function () {
    [$program, $requirement] = updateReqSetup();
    $admin = User::factory()->create(['empcode' => 'EMP-UPDREQ-ADMIN-01', 'access' => 'admin']);

    $this->actingAs($admin)
        ->put(route('programs.requirements.update', [$program, $requirement]), [
            'due_date' => '2026-12-31',
            'is_required' => false,
            'note' => 'Submit via email.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $requirement->refresh();
    expect($requirement->due_date->toDateString())->toBe('2026-12-31');
    expect($requirement->is_required)->toBeFalse();
    expect($requirement->note)->toBe('Submit via email.');
    expect($requirement->title)->toBe('TREAP');
});

test('editing a requirement validates the due date', function () {
    [$program, $requirement] = updateReqSetup();
    $admin = User::factory()->create(['empcode' => 'EMP-UPDREQ-ADMIN-02', 'access' => 'admin']);

    $this->actingAs($admin)
        ->put(route('programs.requirements.update', [$program, $requirement]), [
            'due_date' => 'not-a-date',
            'is_required' => true,
        ])
        ->assertSessionHasErrors('due_date');
});

test('a requirement cannot be edited through another program', function () {
    [, $requirement] = updateReqSetup();
    [$otherProgram] = updateReqSetup('Other Program');
    $admin = User::factory()->create(['empcode' => 'EMP-UPDREQ-ADMIN-03', 'access' => 'admin']);

    $this->actingAs($admin)
        ->put(route('programs.requirements.update', [$otherProgram, $requirement]), [
            'due_date' => '2026-12-31',
            'is_required' => true,
        ])
        ->assertNotFound();
});
