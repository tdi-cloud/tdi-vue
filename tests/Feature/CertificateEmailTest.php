<?php

use App\Mail\CertificateAvailableMail;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\EmailReminderLog;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function certEmailProgram(string $title = 'Leadership Bootcamp'): Program
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

function certEmailBatch(Program $program, string $name = 'Batch 1', string $status = 'Closed'): Batch
{
    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => $name,
        'status' => $status,
        'modality' => 'Onsite',
        'venue' => 'TDI Training Hall',
        'date_start' => '2026-03-03',
        'date_end' => '2026-03-05',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '3',
        'hours' => '24',
    ]);
}

function certEmailParticipant(Batch $batch, string $empcode, ?string $email, string $firstName, string $lastName = 'Cruz'): Participant
{
    if ($email !== null) {
        User::factory()->create(['empcode' => $empcode, 'email' => $email, 'access' => 'user']);
    }

    (new Employee)->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => $lastName,
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
        'attendance' => 'Complete',
        'hours' => 24,
        'added_by' => 'system',
    ]);
}

/**
 * An Issued certificate with its PDF stored on the (faked) public disk.
 *
 * @param  array<string, mixed>  $overrides
 */
function certEmailCertificate(Participant $participant, array $overrides = []): Certificate
{
    $path = UploadedFile::fake()
        ->create('cert.pdf', 100, 'application/pdf')
        ->storeAs('certificates', "{$participant->empcode}-".uniqid().'.pdf', 'public');

    return Certificate::create(array_merge([
        'participant_id' => $participant->id,
        'batch_id' => $participant->batch_id,
        'program_code' => $participant->batch->program_code,
        'empcode' => $participant->empcode,
        'type' => 'Completion',
        'status' => 'Issued',
        'issued_date' => '2026-03-06',
        'hours' => 24,
        'file_path' => $path,
        'file_name' => 'cert.pdf',
    ], $overrides));
}

/**
 * @param  list<int>  $certificateIds
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function certEmailPayload(array $certificateIds, array $overrides = []): array
{
    return array_merge([
        'type' => 'Completion',
        'certificate_ids' => $certificateIds,
        'subject' => 'Congratulations! Your Certificate of Completion for {program_title}',
        'body' => '<p>Dear {first_name},</p><p>Your certificate ({certificate_number}) for {program_title} ({batch}, {schedule}) is attached.</p>',
        'signature' => '<p>Regards,</p><p><strong>TDI Team</strong></p>',
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
});

test('admins and superadmins can email a certificate to its participant with the PDF attached', function (string $access) {
    $sender = User::factory()->create(['empcode' => 'EMP-CEM-'.$access, 'access' => $access]);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($sender)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success')
        ->assertSessionHas('certificateEmailResult', ['sent' => 1, 'failed' => []]);

    Mail::assertSent(CertificateAvailableMail::class, 1);
    Mail::assertSent(CertificateAvailableMail::class, function (CertificateAvailableMail $mail) use ($certificate) {
        $mail->assertHasAttachment(
            Attachment::fromStorageDisk('public', $certificate->file_path)->as('Certificate of Completion - Juan Cruz.pdf')->withMime('application/pdf'),
        );

        return $mail->hasTo('juan@example.com')
            && count($mail->to) === 1
            && $mail->bcc === []
            && $mail->hasSubject('Congratulations! Your Certificate of Completion for Leadership Bootcamp');
    });
})->with(['admin', 'superadmin']);

test('certificates can be emailed whatever the batch status', function (string $status) {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program, 'Batch 1', $status);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(CertificateAvailableMail::class, 1);
})->with(['Rescheduled', 'Closed', 'Ongoing']);

test('a certificate can be emailed again after it was already sent', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(
        certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'),
        ['emailed_at' => now()->subWeek(), 'emailed_by' => 'Earlier Admin'],
    );
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(CertificateAvailableMail::class, 1);
    expect($certificate->fresh()->emailed_by)->toBe($admin->name);
});

test('each participant receives only their own certificate', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $juan = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-J', 'juan@example.com', 'Juan'));
    $maria = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-M', 'maria@example.com', 'Maria'));
    // Distinct contents, so each attachment can be traced back to its own file.
    Storage::disk('public')->put($juan->file_path, '%PDF-1.4 certificate of Juan');
    Storage::disk('public')->put($maria->file_path, '%PDF-1.4 certificate of Maria');
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$juan->id, $maria->id]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(CertificateAvailableMail::class, 2);

    foreach ([[$juan, 'juan@example.com', 'Juan Cruz'], [$maria, 'maria@example.com', 'Maria Cruz']] as [$own, $email, $name]) {
        Mail::assertSent(CertificateAvailableMail::class, function (CertificateAvailableMail $mail) use ($own, $email, $name) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $mail->assertHasAttachment(
                Attachment::fromStorageDisk('public', $own->file_path)->as("Certificate of Completion - {$name}.pdf")->withMime('application/pdf'),
            );

            return count($mail->to) === 1
                && count($mail->rawAttachments) === 1
                && $mail->rawAttachments[0]['data'] === Storage::disk('public')->get($own->file_path);
        });
    }
});

test('placeholders are filled in per recipient and names with HTML are escaped', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-X', 'ana@example.com', '<b>Ana</b>', 'Reyes'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id], [
            'subject' => '{certificate_type} for {first_name} – {unknown}',
            'body' => '<p>Dear {first_name} ({full_name}), {certificate_type} {certificate_number} for {program_title}, {batch}, {schedule}. {unknown}</p>',
        ]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(CertificateAvailableMail::class, function (CertificateAvailableMail $mail) use ($certificate) {
        expect($mail->body)
            ->toContain('Dear &lt;b&gt;Ana&lt;/b&gt; (&lt;b&gt;Ana&lt;/b&gt; Reyes)')
            ->toContain("Completion {$certificate->certificate_number} for Leadership Bootcamp, Batch 1, March 3–5, 2026.")
            ->toContain('{unknown}')
            ->not->toContain('<b>Ana</b>');

        expect($mail->hasSubject('Completion for <b>Ana</b> – {unknown}'))->toBeTrue();

        $mail->assertDontSeeInHtml('<b>Ana</b>', false);

        return true;
    });
});

test('sending marks the certificates as emailed and logs the send as a certificate email', function () {
    $this->freezeSecond();
    $admin = User::factory()->create(['empcode' => 'EMP-CEM-LOG', 'access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertSessionDoesntHaveErrors();

    $certificate->refresh();
    expect($certificate->emailed_at->equalTo(now()))->toBeTrue()
        ->and($certificate->emailed_by)->toBe($admin->name);

    $log = EmailReminderLog::sole();

    expect($log->type)->toBe(EmailReminderLog::TYPE_CERTIFICATE)
        ->and($log->sent_by)->toBe('EMP-CEM-LOG')
        ->and($log->sent_by_name)->toBe($admin->name)
        ->and($log->program_id)->toBe($program->id)
        ->and($log->batch_id)->toBe($batch->id)
        ->and($log->subject)->toBe('Congratulations! Your Certificate of Completion for {program_title}')
        ->and($log->recipients_count)->toBe(1)
        ->and($log->recipients)->toBe([[
            'empcode' => 'EMP-CEM-P1',
            'name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'certificate_type' => 'Completion',
            'status' => 'sent',
        ]]);
});

test('a failed email does not stop the rest and stays not yet emailed', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $juan = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-J', 'juan@example.com', 'Juan'));
    $maria = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-M', 'maria@example.com', 'Maria'));

    Log::spy();
    Event::listen(MessageSending::class, function (MessageSending $event) {
        if ($event->message->getTo()[0]->getAddress() === 'maria@example.com') {
            throw new RuntimeException('Mailbox unavailable');
        }
    });

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$maria->id, $juan->id]))
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success')
        ->assertSessionHas('certificateEmailResult', [
            'sent' => 1,
            'failed' => [['name' => 'Maria Cruz', 'reason' => 'Mailbox unavailable']],
        ]);

    expect($juan->fresh()->emailed_at)->not->toBeNull()
        ->and($maria->fresh()->emailed_at)->toBeNull();

    Log::shouldHaveReceived('error')->once()->with('Failed to email certificate', ['certificate_id' => $maria->id, 'error' => 'Mailbox unavailable']);

    $log = EmailReminderLog::sole();
    expect($log->recipients_count)->toBe(1)
        ->and(array_column($log->recipients, 'status', 'email'))->toBe(['maria@example.com' => 'failed', 'juan@example.com' => 'sent']);
});

test('certificates that cannot be emailed are rejected', function (Closure $arrange, string $errorKey, string $message) {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    $payload = certEmailPayload([$certificate->id], $arrange($certificate, $program));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), $payload)
        ->assertInvalid([$errorKey => $message]);

    Mail::assertNothingSent();
    expect(EmailReminderLog::count())->toBe(0)
        ->and($certificate->fresh()->emailed_at)->toBeNull();
})->with([
    'missing subject' => [fn () => ['subject' => ''], 'subject', 'Enter a subject'],
    'empty certificate list' => [fn () => ['certificate_ids' => []], 'certificate_ids', 'Choose at least one participant'],
    'body with no readable text' => [fn () => ['body' => '<p><br></p><script>alert(1)</script>'], 'body', 'Write a message'],
    'certificate from another batch' => [function (Certificate $certificate, Program $program) {
        $certificate->update(['batch_id' => certEmailBatch($program, 'Batch 2')->id]);

        return [];
    }, 'certificate_ids.0', "Juan Cruz's certificate belongs to another batch"],
    'wrong type' => [fn () => ['type' => 'Participation'], 'certificate_ids.0', "Juan Cruz's certificate is a Completion certificate, not Participation"],
    'pending' => [function (Certificate $certificate) {
        $certificate->update(['status' => 'Pending']);

        return [];
    }, 'certificate_ids.0', "Juan Cruz's Completion certificate is still Pending"],
    'revoked' => [function (Certificate $certificate) {
        $certificate->update(['status' => 'Revoked']);

        return [];
    }, 'certificate_ids.0', "Juan Cruz's Completion certificate was revoked"],
    'file missing on disk' => [function (Certificate $certificate) {
        Storage::disk('public')->delete($certificate->file_path);

        return [];
    }, 'certificate_ids.0', "Juan Cruz's Completion certificate has no PDF on file"],
    'participant without email' => [function (Certificate $certificate) {
        User::where('empcode', $certificate->empcode)->delete();

        return [];
    }, 'certificate_ids.0', 'Juan Cruz has no email address'],
]);

test('unsafe HTML is stripped from the sent body and signature', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id], [
            'body' => '<p onclick="steal()">Hello <strong>{first_name}</strong></p><script>alert("x")</script><a href="javascript:alert(1)">bad link</a>',
            'signature' => '<p>TDI<img src="x" onerror="alert(1)"></p>',
        ]))
        ->assertSessionDoesntHaveErrors();

    Mail::assertSent(CertificateAvailableMail::class, function (CertificateAvailableMail $mail) {
        expect($mail->body)
            ->toContain('<p>Hello <strong>Juan</strong></p>')
            ->toContain('bad link')
            ->not->toContain('<script')
            ->not->toContain('onclick')
            ->not->toContain('javascript:');

        expect($mail->signature)->toBe('<p>TDI</p>');

        return true;
    });
});

test('the email shows the certificate card, the attachment note and the My Certificates button', function () {
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'), ['type' => 'Achievement']);

    $mail = new CertificateAvailableMail(
        certificate: $certificate,
        emailSubject: 'Congratulations on your achievement',
        body: '<p>Well done, Juan!</p>',
        signature: '<p>TDI Team</p>',
        recipientName: 'Juan Cruz',
        programTitle: 'Leadership Bootcamp',
        batchName: 'Batch 1',
        schedule: 'March 3–5, 2026',
    );

    $mail->assertSeeInOrderInHtml([
        '🏅',
        'Certificate of Achievement',
        'Well done, Juan!',
        'Your Certificate',
        $certificate->certificate_number,
        'Leadership Bootcamp',
        'Batch 1',
        'March 3–5, 2026',
        'March 6, 2026',
        '24 hours',
        'Your certificate is attached to this email as a PDF.',
        'View in My Certificates',
        'TDI Team',
    ]);
    $mail->assertSeeInHtml(route('certificates.mine'));
});

test('the hours row is left out when the certificate has no hours', function () {
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'), ['hours' => 0]);

    (new CertificateAvailableMail($certificate, 'Subject', '<p>Hi</p>', '', 'Juan Cruz', 'Leadership Bootcamp', 'Batch 1'))
        ->assertDontSeeInHtml('>Hours<', false);
});

test('a batch from another program returns 404', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $otherBatch = certEmailBatch(certEmailProgram('Another Program'));
    $certificate = certEmailCertificate(certEmailParticipant($otherBatch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $otherBatch]), certEmailPayload([$certificate->id]))
        ->assertNotFound();

    Mail::assertNothingSent();
});

test('regular users cannot email certificates', function () {
    $user = User::factory()->create(['access' => 'user']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($user)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertForbidden();

    Mail::assertNothingSent();
    expect(EmailReminderLog::count())->toBe(0);
});

test('replacing a certificate PDF clears its emailed status', function (Closure $replace) {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $participant = certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan');
    $certificate = certEmailCertificate($participant, ['emailed_at' => now(), 'emailed_by' => 'Ana Admin']);

    $replace($this, $admin, $participant, $batch)->assertSessionDoesntHaveErrors();

    $certificate->refresh();
    expect($certificate->emailed_at)->toBeNull()
        ->and($certificate->emailed_by)->toBeNull();
})->with([
    'single upload' => function ($test, User $admin, Participant $participant, Batch $batch) {
        return $test->actingAs($admin)->post(route('certificates.store'), [
            'participant_id' => $participant->id,
            'batch_id' => $batch->id,
            'program_code' => $batch->program_code,
            'type' => 'Completion',
            'status' => 'Issued',
            'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ]);
    },
    'bulk upload' => function ($test, User $admin, Participant $participant, Batch $batch) {
        return $test->actingAs($admin)->post(route('certificates.bulk-store'), [
            'batch_id' => $batch->id,
            'program_code' => $batch->program_code,
            'type' => 'Completion',
            'status' => 'Issued',
            'files' => [['participant_id' => $participant->id, 'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')]],
        ]);
    },
    'participant upload' => function ($test, User $admin, Participant $participant, Batch $batch) {
        return $test->actingAs(User::where('empcode', $participant->empcode)->firstOrFail())
            ->post(route('certificates.upload-by-user', $batch), [
                'type' => 'Completion',
                'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
            ]);
    },
]);

test('saving a certificate without a new PDF keeps its emailed status', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $participant = certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan');
    $certificate = certEmailCertificate($participant, ['emailed_at' => now(), 'emailed_by' => 'Ana Admin']);

    $this->actingAs($admin)->post(route('certificates.store'), [
        'participant_id' => $participant->id,
        'batch_id' => $batch->id,
        'program_code' => $batch->program_code,
        'type' => 'Completion',
        'status' => 'Issued',
        'remarks' => 'Updated remarks',
    ])->assertSessionDoesntHaveErrors();

    expect($certificate->fresh()->emailed_by)->toBe('Ana Admin');
});

test('the program page exposes templates, history and emailed status, and keeps certificate logs out of other histories', function () {
    $admin = User::factory()->create(['access' => 'admin']);
    $program = certEmailProgram();
    $batch = certEmailBatch($program);
    $certificate = certEmailCertificate(certEmailParticipant($batch, 'EMP-CEM-P1', 'juan@example.com', 'Juan'));
    Mail::fake();

    $this->actingAs($admin)
        ->post(route('programs.email-certificates', [$program, $batch]), certEmailPayload([$certificate->id]))
        ->assertSessionDoesntHaveErrors();

    $response = $this->actingAs($admin)->get(route('programs.show', $program))->assertOk();

    $templates = $response->inertiaProps('certificateEmailTemplates');
    expect(array_keys($templates))->toBe(Certificate::TYPES)
        ->and($templates['Completion']['subject'])->toBe('Congratulations! Your Certificate of Completion for {program_title}');

    $history = $response->inertiaProps('certificateEmailHistory');
    expect($history)->toHaveCount(1)
        ->and($history[0])->toMatchArray([
            'sent_by' => $admin->name,
            'batch_id' => $batch->id,
            'batch' => 'Batch 1',
            'certificate_type' => 'Completion',
            'subject' => 'Congratulations! Your Certificate of Completion for {program_title}',
            'sent_count' => 1,
            'failed_count' => 0,
        ]);

    $programProps = $response->inertiaProps('program');
    $loadedCertificate = $programProps['batches'][0]['participants'][0]['certificates'][0];

    expect($loadedCertificate['emailed_at'])->not->toBeNull()
        ->and($loadedCertificate['emailed_by'])->toBe($admin->name)
        ->and($loadedCertificate['has_file'])->toBeTrue()
        ->and($programProps['email_reminder_logs'])->toBeEmpty()
        ->and($programProps['batches'][0]['participant_notification_logs'])->toBeEmpty();
});
