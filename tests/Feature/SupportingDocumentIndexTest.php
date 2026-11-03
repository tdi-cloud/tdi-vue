<?php

use App\Models\Program;
use App\Models\User;

test('a misspelled supporting document search with no results suggests the closest known word', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-SDI-01', 'access' => 'admin']);
    $program = Program::create([
        'title' => 'Supporting Docs Program', 'modality' => 'Onsite', 'pax' => '20', 'category' => 'Regional',
        'type' => 'TECHNICAL', 'initiated' => 'TDI', 'cost' => '0', 'fund' => 'Test', 'origin' => 'Local',
    ]);
    $program->supportingDocuments()->create([
        'program_code' => $program->program_code,
        'document_type' => 'Memorandum',
        'subject' => 'Orientation of trainers',
        'document_series' => 2026,
        'document_number' => '015',
    ]);

    $typo = $this->actingAs($admin)->get(route('supporting-documents.index', ['search' => 'Memorandom']));
    expect($typo->inertiaProps('documents')['data'])->toBeEmpty()
        ->and($typo->inertiaProps('suggestion'))->toBe('memorandum');

    $exact = $this->actingAs($admin)->get(route('supporting-documents.index', ['search' => 'Memorandum']));
    expect($exact->inertiaProps('suggestion'))->toBeNull();
});
