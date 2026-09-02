<?php

use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;
use App\Models\User;

function bySponsorAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function bySponsorProgram(array $overrides = []): ForeignProgram
{
    return ForeignProgram::create(array_merge([
        'program_title' => 'By Sponsor Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ], $overrides));
}

test('an already-selected program still appears even after its submission deadline has passed', function () {
    $admin = bySponsorAdmin('EMP-BS-01');
    $program = bySponsorProgram(['submission_date' => now()->subMonth()->toDateString()]);

    ForeignSponsorConfig::create([
        'organizing_sponsor' => 'JICA',
        'slug' => 'jica-by-sponsor-test',
        'form_title' => 'JICA Program',
        'is_active' => true,
        'selected_program_ids' => [$program->id],
    ]);

    $response = $this->actingAs($admin)->get(route('foreign-programs.by-sponsor', ['sponsor' => 'JICA']));

    $response->assertOk();
    $ids = collect($response->json('programs'))->pluck('id');
    expect($ids)->toContain($program->id);
});

test('a program that is not selected and whose deadline has passed is hidden from the by-sponsor list', function () {
    $admin = bySponsorAdmin('EMP-BS-03');
    $program = bySponsorProgram(['submission_date' => now()->subMonth()->toDateString()]);

    $response = $this->actingAs($admin)->get(route('foreign-programs.by-sponsor', ['sponsor' => 'JICA']));

    $response->assertOk();
    $ids = collect($response->json('programs'))->pluck('id');
    expect($ids)->not->toContain($program->id);
});

test('a program whose deadline has not passed appears whether selected or not', function () {
    $admin = bySponsorAdmin('EMP-BS-04');
    $program = bySponsorProgram(['submission_date' => now()->addMonth()->toDateString()]);

    $response = $this->actingAs($admin)->get(route('foreign-programs.by-sponsor', ['sponsor' => 'JICA']));

    $response->assertOk();
    $ids = collect($response->json('programs'))->pluck('id');
    expect($ids)->toContain($program->id);
});

test('the by-sponsor list only returns programs for the exact sponsor requested', function () {
    $admin = bySponsorAdmin('EMP-BS-02');
    $jica = bySponsorProgram(['organizing_sponsor' => 'JICA']);
    $koica = bySponsorProgram(['organizing_sponsor' => 'KOICA']);

    $response = $this->actingAs($admin)->get(route('foreign-programs.by-sponsor', ['sponsor' => 'JICA']));

    $ids = collect($response->json('programs'))->pluck('id');
    expect($ids)->toContain($jica->id)->not->toContain($koica->id);
});
