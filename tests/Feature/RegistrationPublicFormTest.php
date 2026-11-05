<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Models\RegistrationQuestion;
use App\Models\SiteImage;
use App\Models\User;

function regPublicEmployee(array $overrides = []): Employee
{
    return Employee::forceCreate(array_merge([
        'EMPCODE' => 'EMP-REG-0001',
        'OFFICE/DIVISION' => 'Training Division',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'P',
        'POSITION' => 'Training Specialist II',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'TDI',
        'LOCATION' => 'Taguig',
        'SECTION' => 'Curriculum',
        'UNIT' => 'Unit A',
    ], $overrides));
}

function regPublicListedPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'listed',
        'empcode' => 'EMP-REG-0001',
        'email' => 'Juan.DelaCruz@Example.com',
        'contact_number' => '0917 123 4567',
        'data_privacy_consent' => true,
        'answers' => [],
        'website' => '',
    ], $overrides);
}

function regPublicManualPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'manual',
        'empcode' => 'NEW-0001',
        'lastname' => 'Santos',
        'firstname' => 'Maria',
        'mi' => 'L',
        'position' => 'Administrative Aide',
        'sg' => '4',
        'plantilla_status' => 'Permanent',
        'sex' => 'M',
        'region' => 'NCR',
        'office_division' => 'Training Division',
        'email' => 'maria.santos@example.com',
        'contact_number' => '09181234567',
        'data_privacy_consent' => true,
        'answers' => [],
        'website' => '',
    ], $overrides);
}

function regPublicSecondBatch(RegistrationForm $form): Batch
{
    return Batch::create([
        'program_code' => $form->batch->program_code,
        'batch' => 'Batch 2',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->addDays(30)->toDateString(),
        'date_end' => now()->addDays(31)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

test('an unknown slug returns 404', function () {
    $this->get(route('join.show', 'does-not-exist'))->assertNotFound();
});

test('an open form renders publicly without internal settings', function () {
    $form = RegistrationForm::factory()->create(['intro_text' => 'Bring a laptop.']);
    RegistrationQuestion::factory()->for($form, 'form')->create(['label' => 'Shirt size?']);
    regPublicEmployee();

    $this->get(route('join.show', $form->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registration/Form')
            ->where('form.slug', $form->slug)
            ->where('form.theme', 'blue')
            ->where('form.intro_text', 'Bring a laptop.')
            ->where('form.batch.batch', 'Batch 1')
            ->has('questions', 1)
            ->where('questions.0.label', 'Shirt size?')
            ->where('fieldOptions.region', ['NCR'])
            ->where('fieldOptions.office_division', ['NCR' => ['Training Division']])
            ->where('backgroundUrl', SiteImage::urlFor('registration_background'))
            ->where('preview', null)
            ->missing('form.slot_limit')
            ->missing('form.created_by_empcode')
        );
});

test('a form that is not open, past its deadline, or full shows the closed page with the reason', function (string $state, string $reason) {
    $factory = RegistrationForm::factory();
    $form = match ($state) {
        'closed' => $factory->closed()->create(),
        'deadline' => $factory->pastDeadline()->create(),
        'full' => $factory->withSlotLimit(1)->create(),
    };

    if ($state === 'full') {
        ProgramRegistration::factory()->forForm($form)->create();
    }

    $this->get(route('join.show', $form->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registration/Closed')
            ->where('reason', $reason)
        );
})->with([
    'not open' => ['closed', RegistrationForm::CLOSED_NOT_OPEN],
    'past the closing date' => ['deadline', RegistrationForm::CLOSED_DEADLINE],
    'all slots taken' => ['full', RegistrationForm::CLOSED_FULL],
]);

test('rejected registrations free up their slot', function () {
    $form = RegistrationForm::factory()->withSlotLimit(1)->create();
    ProgramRegistration::factory()->forForm($form)->rejected()->create();

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->component('Registration/Form'));
});

test('the form\'s owner can preview a closed form, but the public and other admins cannot', function () {
    $creator = User::factory()->create(['empcode' => 'ADM-REG-CREATOR', 'access' => 'admin']);
    $otherAdmin = User::factory()->create(['empcode' => 'ADM-REG-OTHER', 'access' => 'admin']);
    $form = RegistrationForm::factory()->closed()->ownedBy($creator)->create();

    $previewUrl = route('join.show', ['slug' => $form->slug, 'preview' => 1]);

    $this->actingAs($creator)->get($previewUrl)
        ->assertInertia(fn ($page) => $page
            ->component('Registration/Form')
            ->where('preview.closedReason', RegistrationForm::CLOSED_NOT_OPEN)
        );

    $this->actingAs($otherAdmin)->get($previewUrl)
        ->assertInertia(fn ($page) => $page->component('Registration/Closed'));

    auth()->logout();

    $this->get($previewUrl)
        ->assertInertia(fn ($page) => $page->component('Registration/Closed'));
});

test('the employee search returns only the code, name and office/division', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0002', 'LASTNAME' => 'Reyes', 'FIRSTNAME' => 'Ana']);

    $response = $this->getJson(route('join.search', ['slug' => $form->slug, 'q' => 'juan dela']))
        ->assertOk()
        ->assertJsonCount(1);

    expect($response->json('0'))->toBe([
        'empcode' => 'EMP-REG-0001',
        'name' => 'Juan P. Dela Cruz',
        'office_division' => 'Training Division',
    ]);
});

test('the employee search needs at least 3 characters', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->getJson(route('join.search', ['slug' => $form->slug, 'q' => 'ju']))
        ->assertOk()
        ->assertExactJson([]);
});

test('the employee search is not available while the form is closed', function () {
    $form = RegistrationForm::factory()->closed()->create();
    regPublicEmployee();

    $this->getJson(route('join.search', ['slug' => $form->slug, 'q' => 'juan']))->assertNotFound();
});

test('the employee search is rate limited', function () {
    $form = RegistrationForm::factory()->create();
    $url = route('join.search', ['slug' => $form->slug, 'q' => 'juan']);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertTooManyRequests();

    // Searching doesn't use up the separate allowance for submitting.
    $this->post(route('join.submit', $form->slug), regPublicListedPayload())->assertRedirect();
});

test('a listed employee can register, using their record from the employee list', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())
        ->assertRedirect(route('join.success', $form->slug));

    $registration = ProgramRegistration::sole();

    expect($registration)
        ->registration_form_id->toBe($form->id)
        ->batch_id->toBe($form->batch_id)
        ->source->toBe(ProgramRegistration::SOURCE_LISTED)
        ->status->toBe(ProgramRegistration::STATUS_PENDING)
        ->empcode->toBe('EMP-REG-0001')
        ->lastname->toBe('Dela Cruz')
        ->position->toBe('Training Specialist II')
        ->office_division->toBe('Training Division')
        ->unit->toBe('Unit A')
        ->email->toBe('juan.delacruz@example.com')
        ->contact_number->toBe('0917 123 4567')
        ->and($registration->data_privacy_consent_at)->not->toBeNull();
});

test('a listed registration with an unknown employee code is rejected', function () {
    $form = RegistrationForm::factory()->create();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload(['empcode' => 'NOT-IN-LIST']))
        ->assertSessionHasErrors('empcode');

    expect(ProgramRegistration::count())->toBe(0);
});

test('someone who is not in the employee list can register by entering their details', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicManualPayload())
        ->assertRedirect(route('join.success', $form->slug));

    expect(ProgramRegistration::sole())
        ->source->toBe(ProgramRegistration::SOURCE_MANUAL)
        ->empcode->toBe('NEW-0001')
        ->firstname->toBe('Maria')
        ->region->toBe('NCR')
        ->office_division->toBe('Training Division');
});

test('office, location, section and unit are filled in from the most common combination for the chosen region and office/division', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0001']);
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0002']);
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0003', 'OFFICE' => 'Other Office', 'LOCATION' => 'Elsewhere', 'SECTION' => '', 'UNIT' => '']);
    // Same division name in another region must not count.
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0004', 'REGION' => 'Region IV-A', 'OFFICE' => 'Provincial Office', 'LOCATION' => 'Calamba']);
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0005', 'REGION' => 'Region IV-A', 'OFFICE' => 'Provincial Office', 'LOCATION' => 'Calamba']);
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0006', 'REGION' => 'Region IV-A', 'OFFICE' => 'Provincial Office', 'LOCATION' => 'Calamba']);

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['region' => 'NCR', 'office_division' => 'Training Division']))
        ->assertSessionHasNoErrors();

    expect(ProgramRegistration::sole())
        ->office->toBe('TDI')
        ->location->toBe('Taguig')
        ->section->toBe('Curriculum')
        ->unit->toBe('Unit A');
});

test('the office/division must belong to the chosen region', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    regPublicEmployee(['EMPCODE' => 'EMP-REG-0002', 'REGION' => 'Region IV-A', 'OFFICE/DIVISION' => 'Provincial Training Center']);

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['region' => 'NCR', 'office_division' => 'Provincial Training Center']))
        ->assertSessionHasErrors('office_division');

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['region' => 'Region IV-A', 'office_division' => 'Provincial Training Center']))
        ->assertSessionHasNoErrors();
});

test('manual entry must use values that already exist in the employee list for region, office/division, sex and plantilla status', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicManualPayload([
        'region' => 'Atlantis',
        'office_division' => 'Nowhere',
        'sex' => 'X',
        'plantilla_status' => 'Unknown',
    ]))->assertSessionHasErrors(['region', 'office_division', 'sex', 'plantilla_status']);

    expect(ProgramRegistration::count())->toBe(0);
});

test('manual entry requires the core employee fields', function () {
    $form = RegistrationForm::factory()->create();

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['lastname' => '', 'position' => '', 'office_division' => '']))
        ->assertSessionHasErrors(['lastname', 'position', 'office_division']);
});

test('manual entry is blocked when the employee code is already in the employee list', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['empcode' => 'emp-reg-0001']))
        ->assertSessionHasErrors('empcode');

    expect(ProgramRegistration::count())->toBe(0);
});

test('a person cannot register twice for the same program, even in another batch', function () {
    $form = RegistrationForm::factory()->create();
    $otherBatchForm = RegistrationForm::factory()->forBatch(regPublicSecondBatch($form))->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())->assertSessionHasNoErrors();

    $this->post(route('join.submit', $otherBatchForm->slug), regPublicListedPayload())
        ->assertSessionHasErrors('empcode');

    expect(ProgramRegistration::count())->toBe(1);
});

test('a rejected registration does not stop the person from registering again', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    ProgramRegistration::factory()->forForm($form)->listed()->rejected()->create(['empcode' => 'EMP-REG-0001']);

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())->assertSessionHasNoErrors();

    expect(ProgramRegistration::where('status', ProgramRegistration::STATUS_PENDING)->count())->toBe(1);
});

test('someone who is already a participant of the program cannot register', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    Participant::create([
        'batch_id' => regPublicSecondBatch($form)->id,
        'empcode' => 'EMP-REG-0001',
        'attendance' => 'Pending',
        'added_by' => 'test',
    ]);

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())
        ->assertSessionHasErrors('empcode');

    expect(ProgramRegistration::count())->toBe(0);
});

test('nothing is saved once the slot limit is reached', function () {
    $form = RegistrationForm::factory()->withSlotLimit(1)->create();
    regPublicEmployee();
    ProgramRegistration::factory()->forForm($form)->create();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())
        ->assertRedirect(route('join.show', $form->slug));

    expect(ProgramRegistration::count())->toBe(1);
});

test('a closed form does not accept submissions', function () {
    $form = RegistrationForm::factory()->closed()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload())
        ->assertRedirect(route('join.show', $form->slug));

    expect(ProgramRegistration::count())->toBe(0);
});

test('a filled honeypot field is treated as a bot and nothing is saved', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload(['website' => 'https://spam.example']))
        ->assertRedirect(route('join.success', $form->slug));

    expect(ProgramRegistration::count())->toBe(0);
});

test('the data privacy consent, email and contact number are required', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload([
        'data_privacy_consent' => false,
        'email' => 'not-an-email',
        'contact_number' => 'call me',
    ]))->assertSessionHasErrors(['data_privacy_consent', 'email', 'contact_number']);
});

test('required custom questions are enforced and choices must be one of the options', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    $required = RegistrationQuestion::factory()->for($form, 'form')->required()->create(['label' => 'Dietary needs?']);
    $choice = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_SELECT, ['Small', 'Medium', 'Large'])
        ->create(['label' => 'Shirt size?']);

    $this->post(route('join.submit', $form->slug), regPublicListedPayload([
        'answers' => [$choice->id => 'Huge'],
    ]))->assertSessionHasErrors(["answers.{$required->id}", "answers.{$choice->id}"]);

    expect(ProgramRegistration::count())->toBe(0);
});

test('custom answers are saved together with their question labels', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    $text = RegistrationQuestion::factory()->for($form, 'form')->required()->create(['label' => 'Dietary needs?', 'sort_order' => 0]);
    $checkbox = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_CHECKBOX, ['Laptop', 'Tablet', 'None'])
        ->create(['label' => 'Devices you will bring', 'sort_order' => 1]);

    $this->post(route('join.submit', $form->slug), regPublicListedPayload([
        'answers' => [$text->id => 'Vegetarian', $checkbox->id => ['Laptop', 'Tablet']],
    ]))->assertSessionHasNoErrors();

    $text->delete();

    expect(ProgramRegistration::sole()->custom_answers)->toBe([
        ['question_id' => $text->id, 'label' => 'Dietary needs?', 'type' => 'text', 'value' => 'Vegetarian'],
        ['question_id' => $checkbox->id, 'label' => 'Devices you will bring', 'type' => 'checkbox', 'value' => ['Laptop', 'Tablet']],
    ]);
});

test('an "Other" answer is saved with what the person typed', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    $radio = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_RADIO, ['Yes', 'No'])
        ->create(['label' => 'Are you a Person With Disability?', 'allow_other' => true, 'sort_order' => 0]);
    $checkbox = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_CHECKBOX, ['Laptop', 'Tablet'])
        ->create(['label' => 'Devices', 'allow_other' => true, 'sort_order' => 1]);

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('questions.0.allow_other', true));

    $this->post(route('join.submit', $form->slug), regPublicListedPayload([
        'answers' => [$radio->id => '__other__', $checkbox->id => ['Laptop', '__other__']],
        'answers_other' => [$radio->id => 'Prefer to discuss privately', $checkbox->id => 'Smartphone'],
    ]))->assertSessionHasNoErrors();

    $answers = collect(ProgramRegistration::sole()->custom_answers)->pluck('value', 'question_id');

    expect($answers[$radio->id])->toBe('Other: Prefer to discuss privately')
        ->and($answers[$checkbox->id])->toBe(['Laptop', 'Other: Smartphone']);
});

test('picking "Other" requires typing an answer, and "Other" only works where it is offered', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();
    $withOther = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_RADIO, ['Yes', 'No'])
        ->create(['allow_other' => true]);
    $withoutOther = RegistrationQuestion::factory()->for($form, 'form')
        ->choice(RegistrationQuestion::TYPE_RADIO, ['Yes', 'No'])
        ->create();

    $this->post(route('join.submit', $form->slug), regPublicListedPayload([
        'answers' => [$withOther->id => '__other__', $withoutOther->id => '__other__'],
        'answers_other' => [$withOther->id => ''],
    ]))->assertSessionHasErrors(["answers_other.{$withOther->id}", "answers.{$withoutOther->id}"]);

    expect(ProgramRegistration::count())->toBe(0);
});

test('"Prefer not to say" is offered for sex and accepted', function () {
    $form = RegistrationForm::factory()->create();
    regPublicEmployee();

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('fieldOptions.sex', ['M', 'Prefer not to say']));

    $this->post(route('join.submit', $form->slug), regPublicManualPayload(['sex' => 'Prefer not to say']))
        ->assertSessionHasNoErrors();

    expect(ProgramRegistration::sole()->sex)->toBe('Prefer not to say');
});

test('the success page renders for the form', function () {
    $form = RegistrationForm::factory()->create();

    $this->get(route('join.success', $form->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Registration/Success')
            ->where('form.slug', $form->slug)
        );
});
