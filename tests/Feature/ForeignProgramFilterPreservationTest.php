<?php

use App\Models\Employee;
use App\Models\ForeignProgram;
use App\Models\User;

function filterPreservationAdmin(string $empcode): User
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

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function filterPreservationProgramPayload(array $overrides = []): array
{
    return array_merge([
        'program_title' => 'Filter Preservation Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(3)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ], $overrides);
}

test('deleting a foreign program redirects back to the referring filtered URL', function () {
    $admin = filterPreservationAdmin('EMP-FP-01');
    $program = ForeignProgram::create(filterPreservationProgramPayload());

    $referer = route('foreign-programs.index', ['status' => 'for_dissemination', 'search' => 'Filter']);

    $this->actingAs($admin)
        ->withHeaders(['referer' => $referer])
        ->delete(route('foreign-programs.destroy', $program))
        ->assertRedirect($referer);
});

test('updating a foreign program redirects back to the referring filtered URL', function () {
    $admin = filterPreservationAdmin('EMP-FP-02');
    $program = ForeignProgram::create(filterPreservationProgramPayload());

    $referer = route('foreign-programs.index', ['status' => 'for_dissemination', 'year' => '2026']);

    $this->actingAs($admin)
        ->withHeaders(['referer' => $referer])
        ->put(route('foreign-programs.update', $program), filterPreservationProgramPayload(['program_title' => 'Updated Title']))
        ->assertRedirect($referer);
});

test('creating a foreign program redirects back to the referring filtered URL', function () {
    $admin = filterPreservationAdmin('EMP-FP-03');

    $referer = route('foreign-programs.index', ['organization' => 'JICA']);

    $this->actingAs($admin)
        ->withHeaders(['referer' => $referer])
        ->post(route('foreign-programs.store'), filterPreservationProgramPayload())
        ->assertRedirect($referer);
});

test('deleting without a referer falls back to the plain index route', function () {
    $admin = filterPreservationAdmin('EMP-FP-04');
    $program = ForeignProgram::create(filterPreservationProgramPayload());

    $this->actingAs($admin)
        ->delete(route('foreign-programs.destroy', $program))
        ->assertRedirect(route('foreign-programs.index'));
});

test('admin can bulk delete multiple foreign programs at once, preserving the referring filtered URL', function () {
    $admin = filterPreservationAdmin('EMP-FP-05');
    $one = ForeignProgram::create(filterPreservationProgramPayload(['program_title' => 'Bulk Delete One']));
    $two = ForeignProgram::create(filterPreservationProgramPayload(['program_title' => 'Bulk Delete Two']));
    $untouched = ForeignProgram::create(filterPreservationProgramPayload(['program_title' => 'Bulk Delete Untouched']));

    $referer = route('foreign-programs.index', ['status' => 'for_dissemination']);

    $this->actingAs($admin)
        ->withHeaders(['referer' => $referer])
        ->delete(route('foreign-programs.bulk-destroy'), ['ids' => [$one->id, $two->id]])
        ->assertRedirect($referer);

    expect(ForeignProgram::find($one->id))->toBeNull();
    expect(ForeignProgram::find($two->id))->toBeNull();
    expect(ForeignProgram::find($untouched->id))->not->toBeNull();
});

test('bulk delete requires at least one valid id', function () {
    $admin = filterPreservationAdmin('EMP-FP-06');

    $this->actingAs($admin)
        ->delete(route('foreign-programs.bulk-destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');

    $this->actingAs($admin)
        ->delete(route('foreign-programs.bulk-destroy'), ['ids' => [999999]])
        ->assertSessionHasErrors('ids.0');
});
