<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Program;

function certNumberTestParticipant(string $empcode): Participant
{
    $program = Program::create([
        'title' => 'Certificate Number Test Program',
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
        'date_start' => now()->subMonth()->toDateString(),
        'date_end' => now()->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);

    return Participant::create([
        'sort_order' => 1, 'batch_id' => $batch->id, 'empcode' => $empcode,
        'attendance' => 'Complete', 'hours' => 16, 'added_by' => 'system',
    ]);
}

function certNumberTestCertificate(Participant $participant, string $type): Certificate
{
    return Certificate::create([
        'participant_id' => $participant->id,
        'batch_id' => $participant->batch_id,
        'program_code' => $participant->batch->program_code,
        'empcode' => $participant->empcode,
        'type' => $type,
        'status' => 'Pending',
    ]);
}

test('certificate numbers continue in sequence for the current year', function () {
    $participant = certNumberTestParticipant('EMP-CERTNO-01');
    $prefix = 'TDI-'.now()->year.'-CERT-';

    expect(certNumberTestCertificate($participant, 'Completion')->certificate_number)->toBe($prefix.'0001');
    expect(certNumberTestCertificate($participant, 'Appearance')->certificate_number)->toBe($prefix.'0002');
});

test('deleting a certificate does not cause the next number to repeat an existing one', function () {
    $participant = certNumberTestParticipant('EMP-CERTNO-02');
    $prefix = 'TDI-'.now()->year.'-CERT-';

    $first = certNumberTestCertificate($participant, 'Completion');
    certNumberTestCertificate($participant, 'Appearance');
    $first->delete();

    expect(certNumberTestCertificate($participant, 'Participation')->certificate_number)->toBe($prefix.'0003');
});

test('certificate numbers keep counting past 9999', function () {
    $participant = certNumberTestParticipant('EMP-CERTNO-03');
    $prefix = 'TDI-'.now()->year.'-CERT-';

    Certificate::create([
        'participant_id' => $participant->id,
        'batch_id' => $participant->batch_id,
        'program_code' => $participant->batch->program_code,
        'empcode' => $participant->empcode,
        'type' => 'Completion',
        'status' => 'Pending',
        'certificate_number' => $prefix.'9999',
    ]);
    certNumberTestCertificate($participant, 'Appearance');

    expect(certNumberTestCertificate($participant, 'Participation')->certificate_number)->toBe($prefix.'10001');
});
