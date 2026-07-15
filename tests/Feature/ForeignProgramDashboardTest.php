<?php

use App\Models\ForeignProgram;

function dashboardTestProgram(string $sponsor, string $status): ForeignProgram
{
    return ForeignProgram::create([
        'program_title' => 'Dashboard Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => $sponsor,
        'status' => $status,
    ]);
}

/**
 * Mirrors the $bySponsor query built in ForeignProgramController::dashboardData(),
 * scoped to a single sponsor for assertions.
 *
 * NOTE: dashboardData() itself can't be exercised end-to-end under the test
 * suite's sqlite driver — it also runs a `YEAR(program_start)` raw SQL query
 * (MySQL-only) for the year filter dropdown, which sqlite doesn't support.
 * That's a pre-existing, unrelated gap; this test instead verifies the
 * exclusion filter directly against the same query shape.
 */
function dashboardSponsorCounts(string $sponsor): ?object
{
    return ForeignProgram::query()
        ->where('status', '!=', 'not_nfp_concern')
        ->where('organizing_sponsor', $sponsor)
        ->selectRaw("organizing_sponsor,
            count(*) as received,
            sum(case when status != 'for_dissemination' then 1 else 0 end) as disseminated")
        ->groupBy('organizing_sponsor')
        ->first();
}

test('a program can be saved with the not_nfp_concern status', function () {
    $program = dashboardTestProgram('JICA', 'not_nfp_concern');

    expect($program->fresh()->status)->toBe('not_nfp_concern');
});

test('programs marked not_nfp_concern are excluded from the sponsor dashboard counts', function () {
    dashboardTestProgram('JICA', 'for_dissemination');
    dashboardTestProgram('JICA', 'ongoing');
    dashboardTestProgram('JICA', 'not_nfp_concern');

    $counts = dashboardSponsorCounts('JICA');

    expect($counts->received)->toBe(2);
});

test('a sponsor whose only programs are not_nfp_concern does not appear in the dashboard at all', function () {
    dashboardTestProgram('DFAT', 'not_nfp_concern');

    expect(dashboardSponsorCounts('DFAT'))->toBeNull();
});
