<?php

use App\Http\Controllers\ParticipantNotificationController;
use App\Mail\ParticipantNotificationMail;
use App\Models\Batch;
use App\Models\EmailReminderLog;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function notifyTestProgram(string $title = 'Leadership Bootcamp'): Program
{
    return Program::create([
        'title' => $title,
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);
}

function notifyTestBatch(Program $program, string $status = 'Ongoing'): Batch
{
    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => $status,
        'modality' => 'Onsite',
        'venue' => 'TDI Training Hall, Taguig City',
        'date_start' => '2026-03-03',
        'date_end' => '2026-03-05',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '3',
        'hours' => '24',
    ]);
}

function notifyTestParticipant(Batch $batch, string $empcode, string $email, string $firstName): Participant
{
    User::factory()->create(['empcode' => $empcode, 'email' => $email, 'access' => 'user']);

    (new Employee)->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Cruz',
        'FIRSTNAME' => $firstName,
        'MI' => '',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $empcode,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);
}

/**
 * @return array<string, mixed>
 */
function notifyTestPayload(array $overrides = []): array
{
    return array_merge([
        'to' => ['juan@example.com', 'maria@example.com'],
        'subject' => 'Schedule update for Batch 1',
        'body' => '<p>Good day, participants!</p><p>The session now starts at <strong>9:00 AM</strong>.</p>',
        'signature' => '<p>Regards,</p><p><strong>TDI Team</strong></p>',
    ], $overrides);
}

test('admins and superadmins can notify participants with BCC recipients and the given subject', function (string $access) {
    $sender = User::factory()->create(['empcode' => 'EMP-NTF-'.$access, 'access' => $access]);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    Mail::fake();

    $this->actingAs($sender)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload())
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    Mail::assertSent(ParticipantNotificationMail::class, 1);
    Mail::assertSent(ParticipantNotificationMail::class, function (ParticipantNotificationMail $mail) use ($program) {
        return $mail->hasBcc('juan@example.com')
            && $mail->hasBcc('maria@example.com')
            && $mail->hasSubject('Schedule update for Batch 1')
            && $mail->programTitle === $program->title
            && $mail->batchName === 'Batch 1'
            && $mail->schedule === 'March 3–5, 2026'
            && $mail->venue === 'TDI Training Hall, Taguig City';
    });
})->with(['admin', 'superadmin']);

test('the notification is logged as a notification with sender, batch and matched recipients', function () {
    $admin = User::factory()->create(['empcode' => 'EMP-NTF-LOG', 'access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    notifyTestParticipant($batch, 'EMP-NTF-P1', 'juan@example.com', 'Juan');
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload([
            'to' => ['Juan@Example.com', 'guest@example.com'],
        ]))
        ->assertSessionDoesntHaveErrors();

    $log = EmailReminderLog::sole();

    expect($log->type)->toBe(EmailReminderLog::TYPE_NOTIFICATION)
        ->and($log->sent_by)->toBe('EMP-NTF-LOG')
        ->and($log->sent_by_name)->toBe($admin->name)
        ->and($log->program_id)->toBe($program->id)
        ->and($log->batch_id)->toBe($batch->id)
        ->and($log->requirement_id)->toBeNull()
        ->and($log->subject)->toBe('Schedule update for Batch 1')
        ->and($log->recipients_count)->toBe(2)
        ->and($log->recipients[0])->toBe(['empcode' => 'EMP-NTF-P1', 'name' => 'Juan Cruz', 'email' => 'Juan@Example.com'])
        ->and($log->recipients[1])->toBe(['empcode' => null, 'name' => null, 'email' => 'guest@example.com']);
});

test('large recipient lists are sent in BCC chunks of 50', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    $recipients = array_map(fn (int $i) => "participant{$i}@example.com", range(1, 120));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload(['to' => $recipients]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(ParticipantNotificationMail::class, 3);
    Mail::assertSent(ParticipantNotificationMail::class, fn ($mail) => count($mail->bcc) === ParticipantNotificationController::RECIPIENTS_PER_MESSAGE && $mail->hasBcc('participant1@example.com'));
    Mail::assertSent(ParticipantNotificationMail::class, fn ($mail) => count($mail->bcc) === 20 && $mail->hasBcc('participant120@example.com'));
    expect(EmailReminderLog::sole()->recipients_count)->toBe(120);
});

test('notifications are allowed for rescheduled batches', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program, 'Rescheduled');
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload())
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(ParticipantNotificationMail::class);
});

test('invalid notification input is rejected', function (array $overrides, string $errorKey) {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload($overrides))
        ->assertSessionHasErrors($errorKey);

    Mail::assertNothingSent();
    expect(EmailReminderLog::count())->toBe(0);
})->with([
    'missing subject' => [['subject' => ''], 'subject'],
    'subject too long' => [['subject' => str_repeat('a', 501)], 'subject'],
    'empty recipient list' => [['to' => []], 'to'],
    'invalid email' => [['to' => ['juan@example.com', 'not-an-email']], 'to.1'],
    'duplicate email' => [['to' => ['juan@example.com', 'JUAN@example.com']], 'to.1'],
    'missing body' => [['body' => ''], 'body'],
    'body with no readable text' => [['body' => '<p><br></p><script>alert(1)</script>'], 'body'],
]);

test('unsafe HTML is stripped from the sent body and signature', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload([
            'body' => '<p onclick="steal()">Hello <strong>team</strong></p><script>alert("x")</script><a href="javascript:alert(1)">bad link</a> <a href="https://tesda.gov.ph" onmouseover="x()">good link</a>',
            'signature' => '<p>TDI<img src="x" onerror="alert(1)"></p>',
        ]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(ParticipantNotificationMail::class, function (ParticipantNotificationMail $mail) {
        expect($mail->body)
            ->not->toContain('<script')
            ->not->toContain('alert(')
            ->not->toContain('onclick')
            ->not->toContain('onmouseover')
            ->not->toContain('javascript:')
            ->toContain('<p>Hello <strong>team</strong></p>')
            ->toContain('bad link')
            ->toContain('<a href="https://tesda.gov.ph" target="_blank" rel="noopener noreferrer">good link</a>');

        expect($mail->signature)->toBe('<p>TDI</p>');

        $html = $mail->render();
        expect($html)->not->toContain('<script')->not->toContain('onclick');

        return true;
    });
});

test('the email renders the program card with title, batch, dates and venue', function () {
    $html = (new ParticipantNotificationMail(
        emailSubject: 'Schedule update',
        body: '<p>Please note the new schedule.</p>',
        signature: '<p>TDI Team</p>',
        programTitle: 'Leadership Bootcamp',
        batchName: 'Batch 2',
        schedule: 'March 3–5, 2026',
        venue: 'TDI Training Hall',
    ))->render();

    expect($html)
        ->toContain('Participant Notification')
        ->toContain('Program Details')
        ->toContain('Leadership Bootcamp')
        ->toContain('Batch 2')
        ->toContain('March 3–5, 2026')
        ->toContain('TDI Training Hall')
        ->toContain('Please note the new schedule.')
        ->toContain('TDI Team');
});

test('a batch from another program returns 404', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $otherBatch = notifyTestBatch(notifyTestProgram('Another Program'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $otherBatch]), notifyTestPayload())
        ->assertNotFound();

    Mail::assertNothingSent();
});

test('regular users cannot notify participants', function () {
    $user = User::factory()->create(['access' => 'user']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    Mail::fake();

    $this->actingAs($user)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload())
        ->assertForbidden();

    Mail::assertNothingSent();
    expect(EmailReminderLog::count())->toBe(0);
});

test('the program page exposes each batch notification history and keeps it out of the reminder history', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = notifyTestProgram();
    $batch = notifyTestBatch($program);
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.notify-participants', [$program, $batch]), notifyTestPayload())
        ->assertSessionDoesntHaveErrors();

    $programProps = $this->actingAs($admin)->get(route('programs.show', $program))->assertOk()->inertiaProps('program');

    $history = collect($programProps['batches'][0]['participant_notification_logs']);
    expect($history)->toHaveCount(1)
        ->and($history->first()['subject'])->toBe('Schedule update for Batch 1')
        ->and($history->first()['recipients_count'])->toBe(2)
        ->and($programProps['email_reminder_logs'])->toBeEmpty();
});
