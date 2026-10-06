<?php

use App\Models\RegionalReport;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the tpmr dashboard with the props the page relies on', function () {
    $year = now()->year;

    RegionalReport::create([
        'region' => 'NCR',
        'month' => 'January',
        'year' => $year,
        'file_name' => 'TPMR_NCR_January.pdf',
        'file_path' => 'tpmr/TPMR_NCR_January.pdf',
        'submitted_at' => now(),
        'notes' => null,
        'added_by' => 'Test Admin',
    ]);

    $this->actingAs(User::factory()->create(['empcode' => 'TPMR-001', 'access' => 'admin']))
        ->get(route('tpmr.index', ['year' => $year]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tpmr/index')
            ->has('matrix.NCR')
            ->where('matrix.NCR.1.file_name', 'TPMR_NCR_January.pdf')
            ->has('regions')
            ->has('directors')
            ->has('months', 12)
            ->where('year', $year)
            ->where('currentMonth', now()->month)
            ->where('stats.submitted', 1)
            ->has('stats', fn (Assert $stats) => $stats->hasAll(['totalRequired', 'submitted', 'pending', 'rate']))
            ->has('recentSubmissions.data', 1)
            ->has('recentSubmissions.links')
            ->has('availableYears')
            ->where('filters.search', '')
        );
});

test('the tpmr dashboard filters recent submissions by search term', function () {
    $year = now()->year;

    foreach (['NCR', 'R1'] as $region) {
        RegionalReport::create([
            'region' => $region,
            'month' => 'January',
            'year' => $year,
            'file_name' => "TPMR_{$region}_January.pdf",
            'file_path' => "tpmr/TPMR_{$region}_January.pdf",
            'submitted_at' => now(),
            'added_by' => 'Test Admin',
        ]);
    }

    $this->actingAs(User::factory()->create(['empcode' => 'TPMR-001', 'access' => 'admin']))
        ->get(route('tpmr.index', ['year' => $year, 'search' => 'NCR']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentSubmissions.data', 1)
            ->where('recentSubmissions.data.0.region', 'NCR')
            ->where('stats.submitted', 2)
        );
});
