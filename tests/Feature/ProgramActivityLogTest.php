<?php

use App\Models\Program;
use App\Models\ProgramActivityLog;
use App\Models\User;

function activityLogTestAdmin(string $empcode, string $name = 'Ana Admin'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin', 'name' => $name]);
}

function activityLogProgramPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Activity Log Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ], $overrides);
}

test('creating a program records exactly one "created" activity log entry', function () {
    $admin = activityLogTestAdmin('EMP-ACT-01', 'Ana Admin');

    $this->actingAs($admin)
        ->post(route('programs.store'), activityLogProgramPayload())
        ->assertSessionDoesntHaveErrors();

    $program = Program::where('title', 'Activity Log Test Program')->firstOrFail();

    // Isa lang na "created" na entry — hindi dapat naka-log bilang "updated"
    // ang auto program_code assignment na nangyayari kaagad pagkatapos gawin.
    expect(ProgramActivityLog::where('program_code', $program->program_code)->count())->toBe(1);

    $log = ProgramActivityLog::where('program_code', $program->program_code)->first();
    expect($log->action)->toBe('created')
        ->and($log->title)->toBe('Activity Log Test Program')
        ->and($log->performed_by)->toBe('Ana Admin');
});

test('updating a program records an "updated" activity log entry with the changed fields', function () {
    $admin = activityLogTestAdmin('EMP-ACT-02', 'Ben Editor');
    $program = Program::create(activityLogProgramPayload(['added_by' => 'EMP-ACT-02']));

    $this->actingAs($admin)
        ->put(route('programs.update', $program), activityLogProgramPayload(['title' => 'Renamed Program']))
        ->assertSessionDoesntHaveErrors();

    $log = ProgramActivityLog::where('program_code', $program->program_code)
        ->where('action', 'updated')->first();

    expect($log)->not->toBeNull()
        ->and($log->title)->toBe('Renamed Program')
        ->and($log->performed_by)->toBe('Ben Editor')
        ->and($log->meta['changed_fields'])->toContain('title');
});

test('deleting a program records a "deleted" activity log entry', function () {
    $admin = activityLogTestAdmin('EMP-ACT-03', 'Cara Deleter');
    $program = Program::create(activityLogProgramPayload(['added_by' => 'EMP-ACT-03']));
    $code = $program->program_code;

    $this->actingAs($admin)
        ->delete(route('programs.destroy', $program))
        ->assertSessionDoesntHaveErrors();

    $log = ProgramActivityLog::where('program_code', $code)->where('action', 'deleted')->first();
    expect($log)->not->toBeNull()
        ->and($log->performed_by)->toBe('Cara Deleter');
});

test('the hidden activity log page shows correct counts and entries for the selected date', function () {
    $admin = activityLogTestAdmin('EMP-ACT-04', 'Dana Viewer');

    ProgramActivityLog::create(['program_code' => 'TDI-1', 'title' => 'A', 'action' => 'created', 'performed_by' => 'Someone']);
    ProgramActivityLog::create(['program_code' => 'TDI-2', 'title' => 'B', 'action' => 'updated', 'performed_by' => 'Someone']);
    ProgramActivityLog::create(['program_code' => 'TDI-2', 'title' => 'B', 'action' => 'deleted', 'performed_by' => 'Someone']);

    // Ibang araw — hindi dapat mabilang sa "today". `created_at` ay hindi
    // fillable (sinadya), kaya i-set ito nang direkta pagkatapos gawin.
    $old = ProgramActivityLog::create(['program_code' => 'TDI-3', 'title' => 'C', 'action' => 'created', 'performed_by' => 'Someone']);
    $old->created_at = now()->subDays(3);
    $old->save();

    $response = $this->actingAs($admin)->get(route('programs.activity-log'));
    $response->assertOk();

    expect($response->inertiaProps('stats.created'))->toBe(1);
    expect($response->inertiaProps('stats.updated'))->toBe(1);
    expect($response->inertiaProps('stats.deleted'))->toBe(1);
    expect($response->inertiaProps('logs.data'))->toHaveCount(3);
});

test('non-admin users cannot access the hidden activity log page', function () {
    $user = User::factory()->create(['empcode' => 'EMP-ACT-05', 'access' => 'user']);

    $this->actingAs($user)->get(route('programs.activity-log'))->assertForbidden();
});
