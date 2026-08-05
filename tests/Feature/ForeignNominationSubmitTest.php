<?php

use App\Models\ForeignNominee;
use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;

function nominationSubmitConfig(): ForeignSponsorConfig
{
    return ForeignSponsorConfig::create([
        'organizing_sponsor' => 'Japan International Cooperation Agency',
        'slug' => 'jica-submit-test',
        'form_title' => 'JICA Program',
        'is_active' => true,
    ]);
}

function nominationSubmitProgram(ForeignSponsorConfig $config, array $overrides = []): ForeignProgram
{
    return ForeignProgram::create(array_merge([
        'program_title' => 'Leadership Training',
        'program_start' => now()->addMonths(2)->toDateString(),
        'program_end' => now()->addMonths(2)->addDays(5)->toDateString(),
        'slots' => 5,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => $config->organizing_sponsor,
    ], $overrides));
}

function nominationSubmitPayload(ForeignProgram $program, array $overrides = []): array
{
    return array_merge([
        'foreign_program_id' => $program->id,
        'firstname' => 'Juan',
        'middle_name' => null,
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Training Officer',
        'agency' => 'TESDA',
        'contact_number' => '09171234567',
        'email' => 'juan@example.com',
    ], $overrides);
}

test('submission is blocked once the program deadline has already passed', function () {
    $config = nominationSubmitConfig();
    $program = nominationSubmitProgram($config, ['submission_date' => now()->subDay()->toDateString()]);

    $response = $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($program));

    $response->assertSessionHasErrors('foreign_program_id');
    expect(ForeignNominee::count())->toBe(0);
});

test('submission is allowed when the deadline is today or in the future', function () {
    $config = nominationSubmitConfig();
    $program = nominationSubmitProgram($config, ['submission_date' => now()->toDateString()]);

    $response = $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($program));

    $response->assertRedirect(route('nominate.success', $config->slug));
    expect(ForeignNominee::count())->toBe(1);
});

test('the same email cannot submit a second nomination for the same program', function () {
    $config = nominationSubmitConfig();
    $program = nominationSubmitProgram($config);

    $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($program, ['email' => 'juan@example.com']))
        ->assertRedirect(route('nominate.success', $config->slug));

    $response = $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($program, [
        'email' => 'JUAN@EXAMPLE.COM',
        'firstname' => 'Different',
        'surname' => 'Person',
    ]));

    $response->assertSessionHasErrors('email');
    expect(ForeignNominee::count())->toBe(1);
});

test('the same email can submit to a different program', function () {
    $config = nominationSubmitConfig();
    $programOne = nominationSubmitProgram($config, ['program_title' => 'Program One']);
    $programTwo = nominationSubmitProgram($config, ['program_title' => 'Program Two']);

    $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($programOne, ['email' => 'juan@example.com']))
        ->assertRedirect(route('nominate.success', $config->slug));

    $this->post(route('nominate.submit', $config->slug), nominationSubmitPayload($programTwo, ['email' => 'juan@example.com']))
        ->assertRedirect(route('nominate.success', $config->slug));

    expect(ForeignNominee::count())->toBe(2);
});
