<?php

use App\Mail\ReminderEmail;
use App\Models\Batch;
use App\Models\EmailReminderLog;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function reminderTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function reminderTestSetup(): array
{
    $program = Program::create([
        'title' => 'Reminder Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Ongoing',
        'modality' => 'Onsite',
        'date_start' => now()->toDateString(),
        'date_end' => now()->addDays(2)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '3',
        'hours' => '24',
    ]);

    $requirement = Requirement::create([
        'batch_id' => $batch->id,
        'title' => 'TREAP',
        'name' => Requirement::nameFor('TREAP'),
        'due_date' => now()->addDays(10)->toDateString(),
        'is_required' => true,
    ]);

    return [$program, $batch, $requirement];
}

test('sending an email reminder creates a history log with sender, recipients, and context', function () {
    Mail::fake();
    $admin = reminderTestAdmin('EMP-REM-01');
    [$program, $batch, $requirement] = reminderTestSetup();

    $response = $this->actingAs($admin)->post(route('email-reminder.send'), [
        'to' => ['juan@example.com', 'maria@example.com'],
        'subject' => 'Reminder: Submit your TREAP',
        'body' => '<p>Please submit your requirement.</p>',
        'signature' => 'TDI',
        'program_id' => $program->id,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'recipients' => [
            ['empcode' => 'EMP-001', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
            ['empcode' => 'EMP-002', 'name' => 'Maria Santos', 'email' => 'maria@example.com'],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors();
    Mail::assertSent(ReminderEmail::class);

    $log = EmailReminderLog::first();
    expect($log)->not->toBeNull();
    expect($log->sent_by)->toBe('EMP-REM-01');
    expect($log->sent_by_name)->toBe($admin->name);
    expect($log->program_id)->toBe($program->id);
    expect($log->batch_id)->toBe($batch->id);
    expect($log->requirement_id)->toBe($requirement->id);
    expect($log->recipients_count)->toBe(2);
    expect($log->recipients)->toHaveCount(2);
    expect($log->recipients[0]['empcode'])->toBe('EMP-001');
});

test('sending a reminder without a recipients breakdown falls back to logging the raw email list', function () {
    Mail::fake();
    $admin = reminderTestAdmin('EMP-REM-02');

    $this->actingAs($admin)->post(route('email-reminder.send'), [
        'to' => ['someone@example.com'],
        'subject' => 'Reminder',
        'body' => '<p>Body</p>',
    ])->assertSessionDoesntHaveErrors();

    $log = EmailReminderLog::first();
    expect($log->recipients_count)->toBe(1);
    expect($log->recipients[0]['email'])->toBe('someone@example.com');
    expect($log->recipients[0]['empcode'])->toBeNull();
    expect($log->program_id)->toBeNull();
});

test('non-admin users cannot send email reminders', function () {
    $user = User::factory()->create(['empcode' => 'EMP-REM-03', 'access' => 'user']);

    $this->actingAs($user)->post(route('email-reminder.send'), [
        'to' => ['someone@example.com'],
        'subject' => 'Reminder',
        'body' => '<p>Body</p>',
    ])->assertForbidden();
});

test('the program show page exposes the reminder history for its batches', function () {
    Mail::fake();
    $admin = reminderTestAdmin('EMP-REM-04');
    [$program, $batch, $requirement] = reminderTestSetup();

    $this->actingAs($admin)->post(route('email-reminder.send'), [
        'to' => ['juan@example.com'],
        'subject' => 'Reminder: Submit your TREAP',
        'body' => '<p>Please submit.</p>',
        'program_id' => $program->id,
        'batch_id' => $batch->id,
        'requirement_id' => $requirement->id,
        'recipients' => [
            ['empcode' => 'EMP-001', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
        ],
    ]);

    $response = $this->actingAs($admin)->get(route('programs.show', $program));
    $response->assertOk();

    $logs = collect($response->inertiaProps('program')['email_reminder_logs']);
    expect($logs)->toHaveCount(1);
    expect($logs->first()['recipients_count'])->toBe(1);
});
