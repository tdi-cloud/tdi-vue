<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function destroyTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

test('the admin who added a program can delete it and its batches are removed too', function () {
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
        'added_by' => 'EMP-DEL-01',
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

function destroyTestProgram(?string $addedBy): Program
{
    return Program::create([
        'title' => 'Owned Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => $addedBy,
    ]);
}

test('an admin cannot delete a program added by another admin', function () {
    destroyTestAdmin('EMP-DEL-OWNER');
    $otherAdmin = destroyTestAdmin('EMP-DEL-OTHER');
    $program = destroyTestProgram('EMP-DEL-OWNER');

    $this->actingAs($otherAdmin)->delete(route('programs.destroy', $program))->assertForbidden();
    expect(Program::find($program->id))->not->toBeNull();
});

test('an admin cannot delete a program with no recorded creator', function () {
    $admin = destroyTestAdmin('EMP-DEL-03');
    $program = destroyTestProgram(null);

    $this->actingAs($admin)->delete(route('programs.destroy', $program))->assertForbidden();
    expect(Program::find($program->id))->not->toBeNull();
});

test('a superadmin can delete any program', function (?string $addedBy) {
    $superadmin = User::factory()->create(['empcode' => 'EMP-DEL-SUPER', 'access' => 'superadmin']);
    $program = destroyTestProgram($addedBy);

    $this->actingAs($superadmin)->delete(route('programs.destroy', $program))->assertRedirect(route('programs.index'));
    expect(Program::find($program->id))->toBeNull();
})->with([
    'added by another admin' => 'EMP-DEL-OWNER',
    'no recorded creator' => null,
]);

test('the programs list only flags programs the admin is allowed to delete', function () {
    $admin = destroyTestAdmin('EMP-DEL-OWNER');
    $ownProgram = destroyTestProgram('EMP-DEL-OWNER');
    $othersProgram = destroyTestProgram('EMP-DEL-OTHER');

    $this->actingAs($admin)->get(route('programs.index'))->assertInertia(fn (Assert $page) => $page
        ->component('programs/index')
        ->where('programs', fn ($programs) => collect($programs)->firstWhere('id', $ownProgram->id)['can_delete'] === true
            && collect($programs)->firstWhere('id', $othersProgram->id)['can_delete'] === false)
    );
});

test('the program page only shows delete to the creator or a superadmin', function () {
    $owner = destroyTestAdmin('EMP-DEL-OWNER');
    $otherAdmin = destroyTestAdmin('EMP-DEL-OTHER');
    $superadmin = User::factory()->create(['empcode' => 'EMP-DEL-SUPER', 'access' => 'superadmin']);
    $program = destroyTestProgram('EMP-DEL-OWNER');

    $this->actingAs($owner)->get(route('programs.show', $program))->assertInertia(fn (Assert $page) => $page->where('canDelete', true));
    $this->actingAs($otherAdmin)->get(route('programs.show', $program))->assertInertia(fn (Assert $page) => $page->where('canDelete', false));
    $this->actingAs($superadmin)->get(route('programs.show', $program))->assertInertia(fn (Assert $page) => $page->where('canDelete', true));
});
