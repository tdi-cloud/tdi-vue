<?php

use App\Models\Employee;
use App\Models\ForeignProgram;
use App\Models\User;

function creatorTrackingAdmin(string $empcode, string $name = 'Ana Admin'): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Admin',
        'FIRSTNAME' => 'Ana',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin', 'name' => $name]);
}

function creatorTrackingProgramPayload(): array
{
    return [
        'program_title' => 'Creator Tracking Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ];
}

test('creating a foreign program records the authenticated admin\'s empcode and name', function () {
    $admin = creatorTrackingAdmin('EMP-CT-01', 'Ana Admin');

    $this->actingAs($admin)
        ->post(route('foreign-programs.store'), creatorTrackingProgramPayload())
        ->assertRedirect();

    $program = ForeignProgram::where('program_title', 'Creator Tracking Test Program')->first();

    expect($program)->not->toBeNull();
    expect($program->created_by_empcode)->toBe('EMP-CT-01');
    expect($program->created_by_name)->toBe('Ana Admin');
});

test('the show page exposes who created the program', function () {
    $admin = creatorTrackingAdmin('EMP-CT-02', 'Bea Reyes');

    $this->actingAs($admin)->post(route('foreign-programs.store'), creatorTrackingProgramPayload());
    $program = ForeignProgram::where('program_title', 'Creator Tracking Test Program')->first();

    $response = $this->actingAs($admin)->get(route('foreign-programs.show', $program));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/show')
        ->where('program.created_by_empcode', 'EMP-CT-02')
        ->where('program.created_by_name', 'Bea Reyes')
    );
});

test('updating a program does not change who originally created it', function () {
    $admin = creatorTrackingAdmin('EMP-CT-03', 'Carlo Santos');
    $other = creatorTrackingAdmin('EMP-CT-04', 'Dina Cruz');

    $this->actingAs($admin)->post(route('foreign-programs.store'), creatorTrackingProgramPayload());
    $program = ForeignProgram::where('program_title', 'Creator Tracking Test Program')->first();

    $payload = creatorTrackingProgramPayload();
    $payload['program_title'] = 'Updated Title';

    $this->actingAs($other)
        ->put(route('foreign-programs.update', $program), $payload)
        ->assertRedirect();

    $program->refresh();
    expect($program->program_title)->toBe('Updated Title');
    expect($program->created_by_empcode)->toBe('EMP-CT-03');
    expect($program->created_by_name)->toBe('Carlo Santos');
});
