<?php

use App\Models\ProblemReport;
use App\Models\ProblemReportReply;
use App\Models\User;
use App\Notifications\ProblemReportReplied;
use App\Notifications\ProblemReportResolved;
use Illuminate\Support\Facades\Notification;

test('a superadmin can reply to any problem report and the reporter is notified', function () {
    Notification::fake();

    $superadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-001']);
    $otherSuperadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-002']);
    $reporter = User::factory()->create(['access' => 'guest', 'empcode' => 'EMP-PRR-003']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();

    $this->actingAs($superadmin)
        ->post(route('problem-reports.replies.store', $report), ['body' => 'Thanks! We are fixing it now.'])
        ->assertRedirect()
        ->assertSessionHas('success.title', 'Reply sent')
        ->assertSessionHas('success.next', 'The reporter has been notified.');

    $reply = $report->replies()->sole();
    expect($reply->user_id)->toBe($superadmin->id)
        ->and($reply->body)->toBe('Thanks! We are fixing it now.');

    Notification::assertSentTo($reporter, ProblemReportReplied::class, function (ProblemReportReplied $notification) use ($reporter, $report) {
        $data = $notification->toArray($reporter);

        return $data['title'] === 'Reply to your problem report'
            && $data['url'] === route('problem-reports.mine', ['report' => $report->id]);
    });
    Notification::assertNotSentTo($otherSuperadmin, ProblemReportReplied::class);
});

test('the reporter can write back, which notifies the super admins and reopens a resolved report', function () {
    Notification::fake();

    $superadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-004']);
    $admin = User::factory()->create(['access' => 'admin', 'empcode' => 'EMP-PRR-005']);
    $reporter = User::factory()->create(['access' => 'guest', 'empcode' => 'EMP-PRR-006']);
    $report = ProblemReport::factory()->for($reporter, 'user')->resolved()->create();

    $this->actingAs($reporter)
        ->post(route('problem-reports.replies.store', $report), ['body' => 'It still happens on my side.'])
        ->assertRedirect()
        ->assertSessionHas('success.next', 'The super admins have been notified.');

    expect($report->fresh()->status)->toBe(ProblemReport::STATUS_OPEN)
        ->and($report->replies()->sole()->user_id)->toBe($reporter->id);

    Notification::assertSentTo($superadmin, ProblemReportReplied::class, function (ProblemReportReplied $notification) use ($superadmin, $report) {
        $data = $notification->toArray($superadmin);

        return $data['title'] === 'New reply on a problem report'
            && $data['url'] === route('problem-reports.index', ['report' => $report->id]);
    });
    Notification::assertNotSentTo($admin, ProblemReportReplied::class);
    Notification::assertNotSentTo($reporter, ProblemReportReplied::class);
});

test('other users cannot reply to someone else\'s problem report', function (string $access) {
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-007']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();
    $outsider = User::factory()->create(['access' => $access, 'empcode' => 'EMP-PRR-008']);

    $this->actingAs($outsider)
        ->post(route('problem-reports.replies.store', $report), ['body' => 'Let me answer this.'])
        ->assertForbidden();

    expect(ProblemReportReply::count())->toBe(0);
})->with(['admin', 'user']);

test('a reply needs a message', function () {
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-009']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();

    $this->actingAs($reporter)
        ->post(route('problem-reports.replies.store', $report), ['body' => '   '])
        ->assertSessionHasErrors('body');

    expect(ProblemReportReply::count())->toBe(0);
});

test('my problem reports shows only the signed-in user\'s reports with their conversations', function () {
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-010']);
    $someoneElse = User::factory()->create(['empcode' => 'EMP-PRR-011']);
    $superadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-012']);

    $mine = ProblemReport::factory()->for($reporter, 'user')->create();
    ProblemReport::factory()->for($someoneElse, 'user')->create();
    ProblemReportReply::factory()->for($mine)->for($superadmin, 'user')->create(['body' => 'Fixed in the latest update.']);

    $this->actingAs($reporter)
        ->get(route('problem-reports.mine'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ProblemReports/Mine')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $mine->id)
            ->where('reports.data.0.replies.0.body', 'Fixed in the latest update.')
            ->where('reports.data.0.replies.0.user.name', $superadmin->name));
});

test('opening my problem reports from a notification shows just that report', function () {
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-013']);
    $reports = ProblemReport::factory()->for($reporter, 'user')->count(2)->create();

    $this->actingAs($reporter)
        ->get(route('problem-reports.mine', ['report' => $reports->last()->id]))
        ->assertInertia(fn ($page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $reports->last()->id)
            ->where('filters.report', (string) $reports->last()->id));
});

test('super admins see each conversation, and a notification link narrows the list to that report', function () {
    $superadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-014']);
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-015']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();
    ProblemReport::factory()->for($reporter, 'user')->create();
    ProblemReportReply::factory()->for($report)->for($reporter, 'user')->create(['body' => 'Any update?']);

    $this->actingAs($superadmin)
        ->get(route('problem-reports.index', ['report' => $report->id]))
        ->assertInertia(fn ($page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.user_id', $reporter->id)
            ->where('reports.data.0.replies.0.body', 'Any update?'));
});

test('marking a report resolved notifies the reporter, reopening it does not', function () {
    Notification::fake();

    $superadmin = User::factory()->create(['access' => 'superadmin', 'empcode' => 'EMP-PRR-016']);
    $reporter = User::factory()->create(['empcode' => 'EMP-PRR-017']);
    $report = ProblemReport::factory()->for($reporter, 'user')->create();

    $this->actingAs($superadmin)
        ->put(route('problem-reports.update-status', $report), ['status' => 'resolved'])
        ->assertSessionHas('success.message', 'The status change is saved and the reporter has been notified.');

    Notification::assertSentToTimes($reporter, ProblemReportResolved::class, 1);

    $this->actingAs($superadmin)
        ->put(route('problem-reports.update-status', $report), ['status' => 'open'])
        ->assertSessionHas('success.message', 'The status change is saved.');

    Notification::assertSentToTimes($reporter, ProblemReportResolved::class, 1);
});
