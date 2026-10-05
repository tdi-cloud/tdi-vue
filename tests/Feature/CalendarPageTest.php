<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from the calendar', function () {
    $this->get('/calendar')->assertRedirect('/login');
});

test('calendar page renders batches with the details the calendar UI relies on', function () {
    $program = Program::create([
        'title' => 'Leadership Development Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Technical',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    Batch::create([
        'program_code' => $program->program_code,
        'batch' => '03',
        'status' => 'Upcoming',
        'modality' => 'Face-to-Face',
        'venue' => 'TESDA Development Institute',
        'date_start' => '2026-10-12',
        'date_end' => '2026-10-14',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '3',
        'hours' => '24',
    ]);

    $this->actingAs(User::factory()->create(['empcode' => 'CAL-001', 'access' => 'admin']))
        ->get('/calendar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Calendar')
            ->has('events', 1)
            ->where('events.0.start', '2026-10-12')
            ->where('events.0.end', '2026-10-15')
            ->where('events.0.extendedProps.program_id', $program->id)
            ->where('events.0.extendedProps.program_title', 'Leadership Development Program')
            ->where('events.0.extendedProps.category', 'Technical')
            ->where('events.0.extendedProps.batch', '03')
            ->where('events.0.extendedProps.status', 'Upcoming')
            ->where('events.0.extendedProps.venue', 'TESDA Development Institute')
            ->where('events.0.extendedProps.time_start', '08:00')
            ->where('events.0.extendedProps.participants', 0)
        );
});
