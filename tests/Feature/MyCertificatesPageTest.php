<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function myCertificatesUser(string $empcode): User
{
    if ($empcode !== '') {
        Employee::forceCreate([
            'EMPCODE' => $empcode,
            'OFFICE/DIVISION' => 'Test Office',
            'LASTNAME' => 'Dela Cruz',
            'FIRSTNAME' => 'Juan',
            'MI' => 'D',
            'POSITION' => 'Test Position',
            'SG' => '10',
            'PLANTILLA STATUS' => 'Permanent',
            'SEX' => 'M',
            'REGION' => 'NCR',
            'OFFICE' => 'Test Office',
            'LOCATION' => 'Main',
            'SECTION' => 'Test Section',
            'UNIT' => 'Test Unit',
        ]);
    }

    return User::factory()->create(['empcode' => $empcode, 'access' => 'user']);
}

/**
 * Enrolls the employee in a new program batch and returns the participant record.
 */
function myCertificatesEnroll(string $empcode, string $title, string $dateStart = '2026-03-04'): Participant
{
    $program = Program::create([
        'title' => $title,
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);
    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Closed',
        'modality' => 'Onsite',
        'venue' => 'TDI Training Hall',
        'date_start' => $dateStart,
        'date_end' => $dateStart,
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    return Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $empcode,
        'attendance' => 'Complete',
        'hours' => 16,
        'added_by' => 'system',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function myCertificatesIssue(Participant $participant, array $attributes = []): Certificate
{
    return Certificate::create(array_merge([
        'participant_id' => $participant->id,
        'batch_id' => $participant->batch_id,
        'program_code' => $participant->batch->program_code,
        'empcode' => $participant->empcode,
        'type' => 'Completion',
        'status' => 'Issued',
        'issued_date' => '2026-03-06',
        'hours' => 16,
        'file_path' => 'certificates/completion.pdf',
        'file_name' => 'completion.pdf',
        'issued_by' => 'TDI Director',
    ], $attributes));
}

test('guests are redirected to login from the my certificates page', function () {
    $this->get(route('certificates.mine'))->assertRedirect(route('login'));
});

test('the my certificates page lists only the signed-in user\'s certificates, newest first', function () {
    $user = myCertificatesUser('EMP-MYCERT-01');
    $older = myCertificatesIssue(myCertificatesEnroll('EMP-MYCERT-01', 'Older Program', '2025-05-10'), ['issued_date' => '2025-05-12']);
    $newer = myCertificatesIssue(myCertificatesEnroll('EMP-MYCERT-01', 'Newer Program'), ['type' => 'Participation']);

    $this->actingAs($user)
        ->get(route('certificates.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyCertificates/Index')
            ->has('certificates', 2)
            ->where('certificates.0.id', $newer->id)
            ->where('certificates.0.program_title', 'Newer Program')
            ->where('certificates.0.type', 'Participation')
            ->where('certificates.0.status', 'Issued')
            ->where('certificates.0.venue', 'TDI Training Hall')
            ->where('certificates.0.date_range', 'Mar 4, 2026')
            ->where('certificates.0.file_url', '/storage/certificates/completion.pdf')
            ->where('certificates.0.program_url', route('programs.my-progress', ['batch' => $newer->batch_id, 'tab' => 'certificates']))
            ->where('certificates.1.id', $older->id)
            ->where('stats.total', 2)
            ->where('stats.hours', 32)
            ->where('stats.programs', 2)
            ->where('stats.by_type.Participation', 1)
            ->where('stats.by_type.Completion', 1)
            ->where('stats.by_year.2026', 1)
            ->where('stats.by_year.2025', 1)
            ->where('stats.latest.id', $newer->id)
        );
});

test('certificates of other employees are never exposed', function () {
    $user = myCertificatesUser('EMP-MYCERT-02');
    myCertificatesUser('EMP-MYCERT-03');

    $own = myCertificatesIssue(myCertificatesEnroll('EMP-MYCERT-02', 'My Own Program'));
    myCertificatesIssue(myCertificatesEnroll('EMP-MYCERT-03', 'Someone Else Program'));

    $this->actingAs($user)
        ->get(route('certificates.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificates', 1)
            ->where('certificates.0.id', $own->id)
            ->where('certificates.0.program_title', 'My Own Program')
            ->where('stats.total', 1)
        );
});

test('a user without an empcode gets an empty certificate collection', function () {
    $user = myCertificatesUser('');

    // A stray certificate with a blank empcode must not be matched to them either.
    myCertificatesUser('EMP-MYCERT-04');
    myCertificatesIssue(myCertificatesEnroll('EMP-MYCERT-04', 'Another Program'), ['empcode' => '']);

    $this->actingAs($user)
        ->get(route('certificates.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyCertificates/Index')
            ->has('certificates', 0)
            ->where('stats.total', 0)
            ->where('stats.hours', 0)
            ->where('stats.latest', null)
        );
});

test('pending and revoked certificates are included with their status', function () {
    $user = myCertificatesUser('EMP-MYCERT-05');
    $participant = myCertificatesEnroll('EMP-MYCERT-05', 'Status Program');

    $issued = myCertificatesIssue($participant, ['type' => 'Participation', 'issued_date' => '2026-03-05']);
    $revoked = myCertificatesIssue($participant, [
        'type' => 'Recognition',
        'status' => 'Revoked',
        'issued_date' => '2026-03-06',
        'revoked_at' => '2026-04-01 09:00:00',
        'revoked_reason' => 'Issued in error',
    ]);
    $pending = myCertificatesIssue($participant, [
        'type' => 'Completion',
        'status' => 'Pending',
        'issued_date' => null,
        'file_path' => null,
        'file_name' => null,
    ]);

    $this->actingAs($user)
        ->get(route('certificates.mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificates', 3)
            ->where('certificates.0.id', $revoked->id)
            ->where('certificates.0.status', 'Revoked')
            ->where('certificates.0.revoked_reason', 'Issued in error')
            ->where('certificates.1.id', $issued->id)
            ->where('certificates.2.id', $pending->id)
            ->where('certificates.2.status', 'Pending')
            ->where('certificates.2.file_url', null)
            ->where('stats.by_status.Issued', 1)
            ->where('stats.by_status.Pending', 1)
            ->where('stats.by_status.Revoked', 1)
            // One batch: its hours count once, and the revoked certificate is never the latest.
            ->where('stats.hours', 16)
            ->where('stats.latest.id', $issued->id)
        );
});
