<?php

use App\Models\ProblemReport;
use App\Models\User;
use App\Notifications\ProblemReported;
use Illuminate\Support\Facades\Notification;

test('any authenticated user can submit a problem report, and all super admins are notified', function () {
    Notification::fake();

    $reporter = User::factory()->create(['access' => 'guest', 'empcode' => 'EMP-PR-001']);
    $superAdminOne = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PR-002']);
    $superAdminTwo = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PR-003']);
    $regularAdmin = User::factory()->create(['access' => 'admin', 'empcode' => 'EMP-PR-004']);

    $response = $this->actingAs($reporter)->post(route('problem-reports.store'), [
        'description' => 'The delete button on the participants list does nothing.',
        'page_url' => 'https://example.test/programs/103',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect(ProblemReport::count())->toBe(1);

    $report = ProblemReport::first();
    expect($report->user_id)->toBe($reporter->id)
        ->and($report->description)->toBe('The delete button on the participants list does nothing.')
        ->and($report->page_url)->toBe('https://example.test/programs/103')
        ->and($report->status)->toBe(ProblemReport::STATUS_OPEN);

    Notification::assertSentTo($superAdminOne, ProblemReported::class);
    Notification::assertSentTo($superAdminTwo, ProblemReported::class);
    Notification::assertNotSentTo($regularAdmin, ProblemReported::class);
});

test('the description is required', function () {
    $user = User::factory()->create(['empcode' => 'EMP-PR-005']);

    $this->actingAs($user)
        ->post(route('problem-reports.store'), ['description' => ''])
        ->assertSessionHasErrors('description');

    expect(ProblemReport::count())->toBe(0);
});

test('superadmin can view the problem reports list', function () {
    $superadmin = User::factory()->create(['empcode' => 'EMP-PR-006', 'access' => 'superadmin']);
    $reporter = User::factory()->create(['empcode' => 'EMP-PR-007']);
    ProblemReport::factory()->for($reporter, 'user')->create(['description' => 'Something broke.']);

    $this->actingAs($superadmin)
        ->get(route('problem-reports.index'))
        ->assertSuccessful();
});

test('regular admin cannot view the problem reports list', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-PR-008', 'access' => 'admin']);

    $this->actingAs($admin)
        ->get(route('problem-reports.index'))
        ->assertForbidden();
});

test('superadmin can mark a report resolved and reopen it', function () {
    $superadmin = User::factory()->create(['empcode' => 'EMP-PR-009', 'access' => 'superadmin']);
    $reporter = User::factory()->create(['empcode' => 'EMP-PR-010']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();

    $this->actingAs($superadmin)
        ->put(route('problem-reports.update-status', $report), ['status' => 'resolved'])
        ->assertRedirect();

    expect($report->fresh()->status)->toBe(ProblemReport::STATUS_RESOLVED);

    $this->actingAs($superadmin)
        ->put(route('problem-reports.update-status', $report), ['status' => 'open'])
        ->assertRedirect();

    expect($report->fresh()->status)->toBe(ProblemReport::STATUS_OPEN);
});
