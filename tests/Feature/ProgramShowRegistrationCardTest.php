<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function regCardUser(string $empcode, string $access = 'admin'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

function regCardProgram(?string $addedBy): Program
{
    return Program::create([
        'title' => 'Registration Card Program',
        'modality' => 'Onsite',
        'pax' => '30',
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => $addedBy,
    ]);
}

function regCardBatch(Program $program, string $label, int $startsInDays): Batch
{
    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => $label,
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->addDays($startsInDays)->toDateString(),
        'date_end' => now()->addDays($startsInDays + 1)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

test('the program page sends what the registration card needs for each batch form', function () {
    $creator = regCardUser('ADM-CARD-01');
    $program = regCardProgram($creator->empcode);
    $openBatch = regCardBatch($program, 'Batch 1', 10);
    $closedBatch = regCardBatch($program, 'Batch 2', 20);
    $closesAt = now()->addDays(5)->startOfMinute();

    $openForm = RegistrationForm::factory()->forBatch($openBatch)->withSlotLimit(30)->create([
        'slug' => 'card-batch-1',
        'title' => 'Join Batch 1',
        'theme' => 'teal',
        'closes_at' => $closesAt,
    ]);
    ProgramRegistration::factory()->count(2)->forForm($openForm)->create();
    ProgramRegistration::factory()->forForm($openForm)->approved()->create();
    ProgramRegistration::factory()->forForm($openForm)->rejected()->create();

    RegistrationForm::factory()->forBatch($closedBatch)->closed()->create([
        'slug' => 'card-batch-2',
        'theme' => 'navy',
    ]);

    $this->actingAs($creator)->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('programs/show')
            ->where('canManageRegistrations', true)
            ->where('program.batches.0.registration_form.id', $openForm->id)
            ->where('program.batches.0.registration_form.slug', 'card-batch-1')
            ->where('program.batches.0.registration_form.title', 'Join Batch 1')
            ->where('program.batches.0.registration_form.theme', 'teal')
            ->where('program.batches.0.registration_form.is_open', true)
            ->where('program.batches.0.registration_form.closes_at', fn (string $value) => Carbon::parse($value)->equalTo($closesAt))
            ->where('program.batches.0.registration_form.slot_limit', 30)
            ->where('program.batches.0.registration_form.pending_registrations_count', 2)
            ->where('program.batches.0.registration_form.slots_taken_count', 3)
            ->has('program.batches.0.registration_form.updated_at')
            ->where('program.batches.1.registration_form.slug', 'card-batch-2')
            ->where('program.batches.1.registration_form.theme', 'navy')
            ->where('program.batches.1.registration_form.is_open', false)
            ->where('program.batches.1.registration_form.closes_at', null)
            ->where('program.batches.1.registration_form.slot_limit', null)
            ->where('program.batches.1.registration_form.pending_registrations_count', 0)
            ->where('program.batches.1.registration_form.slots_taken_count', 0)
        );
});

test('a batch without a registration form sends null for it', function () {
    $creator = regCardUser('ADM-CARD-02');
    $program = regCardProgram($creator->empcode);
    regCardBatch($program, 'Batch 1', 10);

    $this->actingAs($creator)->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('program.batches', 1)
            ->where('program.batches.0.registration_form', null)
        );
});

test('only the program creator or a superadmin gets the manage permission flag', function () {
    $creator = regCardUser('ADM-CARD-03');
    $otherAdmin = regCardUser('ADM-CARD-04');
    $superAdmin = regCardUser('SUP-CARD-01', 'superadmin');
    $program = regCardProgram($creator->empcode);

    $this->actingAs($creator)->get(route('programs.show', $program))
        ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', true));

    $this->actingAs($otherAdmin)->get(route('programs.show', $program))
        ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', false));

    $this->actingAs($superAdmin)->get(route('programs.show', $program))
        ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', true));
});
