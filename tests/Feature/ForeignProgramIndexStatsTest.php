<?php

use App\Models\ForeignProgram;
use App\Models\User;

function statsTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function statsTestProgram(array $overrides = []): ForeignProgram
{
    return ForeignProgram::create(array_merge([
        'program_title' => 'Stats Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ], $overrides));
}

test('the index page reports program totals grouped by status from real data', function () {
    $admin = statsTestAdmin('EMP-STATS-01');

    statsTestProgram(['status' => 'waiting_for_nominees']);
    statsTestProgram(['status' => 'waiting_for_nominees']);
    statsTestProgram(['status' => 'concluded']);
    statsTestProgram(['status' => 'ongoing']);
    statsTestProgram(['status' => 'for_interview']);

    $response = $this->actingAs($admin)->get(route('foreign-programs.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/index')
        ->where('stats.total', 5)
        ->where('stats.for_nomination', 2)
        ->where('stats.completed', 1)
        ->where('stats.active', 2)
    );
});
