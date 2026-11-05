<?php

use App\Models\Batch;
use App\Models\Program;
use App\Models\RegistrationForm;
use App\Models\RegistrationQuestion;
use App\Models\SiteImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function regCustomizeUser(string $empcode, string $access = 'admin'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

function regCustomizeBatch(?string $addedBy, string $pax = '25'): Batch
{
    $program = Program::create([
        'title' => 'Registration Customize Program',
        'modality' => 'Onsite',
        'pax' => $pax,
        'category' => 'Regional',
        'type' => 'ADMIN',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
        'added_by' => $addedBy,
    ]);

    return Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Upcoming',
        'modality' => 'Onsite',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(11)->toDateString(),
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
}

function regCustomizeSettings(array $overrides = []): array
{
    return array_merge([
        'title' => 'Join the Leadership Course',
        'intro_text' => 'Slots are limited.',
        'theme' => 'navy',
        'is_open' => true,
        'closes_at' => '2026-12-01T17:00',
        'slot_limit' => 30,
    ], $overrides);
}

test('any admin can view the registration page, but only the program creator can set up a form', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $otherAdmin = regCustomizeUser('ADM-RC-OTHER');
    $batch = regCustomizeBatch($creator->empcode);

    $this->actingAs($creator)->get(route('batches.registration.edit', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('programs/RegistrationConfig')
            ->where('canCreateForm', true)
            ->where('canEdit', false)
            ->where('canManageEditors', false)
            ->where('registrationForm', null)
            ->where('defaultBackgroundUrl', SiteImage::urlFor('registration_background'))
        );

    $this->actingAs($otherAdmin)->get(route('batches.registration.edit', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canCreateForm', false));
});

test('a super admin can set up a form on any program, including ones with no recorded creator', function () {
    $superAdmin = regCustomizeUser('SUP-RC-01', 'superadmin');
    $admin = regCustomizeUser('ADM-RC-01');
    $batch = regCustomizeBatch(null);

    $this->actingAs($admin)->get(route('batches.registration.edit', $batch))
        ->assertInertia(fn ($page) => $page->where('canCreateForm', false));
    $this->actingAs($admin)->post(route('batches.registration-form.store', $batch))->assertForbidden();

    $this->actingAs($superAdmin)->get(route('batches.registration.edit', $batch))
        ->assertInertia(fn ($page) => $page->where('canCreateForm', true));
    $this->actingAs($superAdmin)->post(route('batches.registration-form.store', $batch))->assertRedirect();

    expect($batch->registrationForm()->exists())->toBeTrue();
});

test('non-admins and guests cannot reach the registration page', function () {
    $batch = regCustomizeBatch('ADM-RC-CREATOR');

    $this->actingAs(regCustomizeUser('USR-RC-01', 'user'))
        ->get(route('batches.registration.edit', $batch))
        ->assertForbidden();

    auth()->logout();

    $this->get(route('batches.registration.edit', $batch))->assertRedirect(route('login'));
});

test('setting up a form starts closed, with slots taken from the program pax', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $batch = regCustomizeBatch($creator->empcode, '25');

    $this->actingAs($creator)->post(route('batches.registration-form.store', $batch))->assertRedirect();

    $form = $batch->registrationForm()->sole();

    expect($form)
        ->is_open->toBeFalse()
        ->slot_limit->toBe(25)
        ->theme->toBe('blue')
        ->title->toBe('Program Registration')
        ->created_by_empcode->toBe('ADM-RC-CREATOR')
        ->and($form->slug)->not->toBeEmpty();

    $this->actingAs($creator)->post(route('batches.registration-form.store', $batch))->assertStatus(422);
});

test('a new form starts with the Person With Disability questions', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $batch = regCustomizeBatch($creator->empcode);

    $this->actingAs($creator)->post(route('batches.registration-form.store', $batch));
    $this->actingAs($creator)->post(route('batches.registration-form.store', $batch))->assertStatus(422);

    [$pwd, $type] = $batch->registrationForm()->sole()->questions()->get()->all();

    expect($pwd)
        ->label->toBe('Are you a Person With Disability?')
        ->type->toBe(RegistrationQuestion::TYPE_RADIO)
        ->options->toBe(['Yes', 'No'])
        ->is_required->toBeTrue()
        ->and($type)
        ->label->toBe('Type of Disability')
        ->type->toBe(RegistrationQuestion::TYPE_TEXT)
        ->options->toBeNull()
        ->is_required->toBeFalse()
        ->and(RegistrationQuestion::count())->toBe(2);
});

test('a pax that is not a plain number leaves the slots unlimited', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $batch = regCustomizeBatch($creator->empcode, '20-25');

    $this->actingAs($creator)->post(route('batches.registration-form.store', $batch));

    expect($batch->registrationForm()->sole()->slot_limit)->toBeNull();
});

test('the creator can change the title, instructions, theme, status, closing date and slots', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->closed()->create();

    $this->actingAs($creator)
        ->put(route('registration-forms.update', $form), regCustomizeSettings())
        ->assertSessionHasNoErrors();

    expect($form->refresh())
        ->title->toBe('Join the Leadership Course')
        ->intro_text->toBe('Slots are limited.')
        ->theme->toBe('navy')
        ->is_open->toBeTrue()
        ->slot_limit->toBe(30)
        ->and($form->closes_at->format('Y-m-d H:i'))->toBe('2026-12-01 17:00');
});

test('an unknown theme is rejected', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->put(route('registration-forms.update', $form), regCustomizeSettings(['theme' => 'neon-pink']))
        ->assertSessionHasErrors('theme');

    expect($form->refresh()->theme)->toBe('blue');
});

test('the creator can upload and reset a custom background, and the public form falls back to the site default', function () {
    Storage::fake('public');
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.background.upload', $form), ['image' => UploadedFile::fake()->image('bg.jpg')])
        ->assertSessionHasNoErrors();

    $path = $form->refresh()->background_image;
    Storage::disk('public')->assertExists($path);

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', Storage::disk('public')->url($path)));

    $this->actingAs($creator)->delete(route('registration-forms.background.destroy', $form));

    Storage::disk('public')->assertMissing($path);
    expect($form->refresh()->background_image)->toBeNull();

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', SiteImage::urlFor('registration_background')));
});

test('"No background" shows the public form without any image, and keeps the custom image to switch back to', function () {
    Storage::fake('public');
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.background.upload', $form), ['image' => UploadedFile::fake()->image('bg.jpg')]);
    $path = $form->refresh()->background_image;

    $this->actingAs($creator)
        ->put(route('registration-forms.update', $form), regCustomizeSettings(['hide_background' => true]))
        ->assertSessionHasNoErrors();

    expect($form->refresh()->hide_background)->toBeTrue()
        ->and($form->background_image)->toBe($path);
    Storage::disk('public')->assertExists($path);

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->component('Registration/Form')->where('backgroundUrl', null));

    $this->actingAs($creator)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertInertia(fn ($page) => $page->where('registrationForm.hide_background', true));

    // Showing the custom image again doesn't need a new upload.
    $this->actingAs($creator)->put(route('registration-forms.update', $form), regCustomizeSettings(['hide_background' => false]));

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', Storage::disk('public')->url($path)));
});

test('uploading a new image or going back to the site default turns "No background" off', function () {
    Storage::fake('public');
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create(['hide_background' => true]);

    $this->actingAs($creator)
        ->post(route('registration-forms.background.upload', $form), ['image' => UploadedFile::fake()->image('bg.jpg')]);
    expect($form->refresh()->hide_background)->toBeFalse();

    $form->update(['hide_background' => true]);

    $this->actingAs($creator)->delete(route('registration-forms.background.destroy', $form));
    expect($form->refresh())->hide_background->toBeFalse()->background_image->toBeNull();

    $this->get(route('join.show', $form->slug))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', SiteImage::urlFor('registration_background')));
});

test('a background must be an image', function () {
    Storage::fake('public');
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.background.upload', $form), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('image');
});

test('the creator can add, edit, reorder and delete additional questions', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)->post(route('registration-forms.questions.store', $form), [
        'type' => 'text',
        'label' => 'Dietary needs?',
        'is_required' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($creator)->post(route('registration-forms.questions.store', $form), [
        'type' => 'select',
        'label' => 'Shirt size?',
        'help_text' => 'Pick the closest size.',
        'options' => ['Small', 'Medium', 'Large'],
    ])->assertSessionHasNoErrors();

    [$dietary, $shirt] = $form->questions()->get()->all();

    expect($dietary)->label->toBe('Dietary needs?')->is_required->toBeTrue()->options->toBeNull()
        ->and($shirt)->options->toBe(['Small', 'Medium', 'Large'])->help_text->toBe('Pick the closest size.');

    $this->actingAs($creator)->post(route('registration-questions.move-up', $shirt));
    expect($form->questions()->pluck('id')->all())->toBe([$shirt->id, $dietary->id]);

    $this->actingAs($creator)->put(route('registration-questions.update', $dietary), [
        'type' => 'radio',
        'label' => 'Any dietary needs?',
        'options' => ['None', 'Vegetarian', 'Halal'],
        'is_required' => false,
    ])->assertSessionHasNoErrors();

    expect($dietary->refresh())->type->toBe('radio')->is_required->toBeFalse();

    $this->actingAs($creator)->delete(route('registration-questions.destroy', $shirt));
    expect($form->questions()->count())->toBe(1);
});

test('single choice and checkbox questions can add an "Other" choice, other types cannot', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)->post(route('registration-forms.questions.store', $form), [
        'type' => 'radio',
        'label' => 'Are you a Person With Disability?',
        'options' => ['Yes', 'No'],
        'allow_other' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($creator)->post(route('registration-forms.questions.store', $form), [
        'type' => 'select',
        'label' => 'Shirt size?',
        'options' => ['Small', 'Large'],
        'allow_other' => true,
    ])->assertSessionHasNoErrors();

    [$radio, $select] = $form->questions()->get()->all();

    expect($radio->allow_other)->toBeTrue()
        ->and($radio->options)->toBe(['Yes', 'No'])
        ->and($select->allow_other)->toBeFalse();
});

test('each option must be filled in and different from the others', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.questions.store', $form), ['type' => 'radio', 'label' => 'Pick one', 'options' => ['Yes', 'yes']])
        ->assertSessionHasErrors('options.1');

    $this->actingAs($creator)
        ->post(route('registration-forms.questions.store', $form), ['type' => 'radio', 'label' => 'Pick one', 'options' => ['Yes', '']])
        ->assertSessionHasErrors('options.1');

    expect(RegistrationQuestion::count())->toBe(0);
});

test('choice questions need options and plain text questions drop them', function () {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $form = RegistrationForm::factory()->forBatch(regCustomizeBatch($creator->empcode))->ownedBy($creator)->create();

    $this->actingAs($creator)
        ->post(route('registration-forms.questions.store', $form), ['type' => 'checkbox', 'label' => 'Devices?'])
        ->assertSessionHasErrors('options');

    $this->actingAs($creator)
        ->post(route('registration-forms.questions.store', $form), ['type' => 'textarea', 'label' => 'Comments', 'options' => ['ignored']])
        ->assertSessionHasNoErrors();

    expect(RegistrationQuestion::sole()->options)->toBeNull();
});

test('an admin who is not the owner or an editor cannot change the registration form', function (string $action) {
    $creator = regCustomizeUser('ADM-RC-CREATOR');
    $otherAdmin = regCustomizeUser('ADM-RC-OTHER');
    $batch = regCustomizeBatch($creator->empcode);
    $form = RegistrationForm::factory()->forBatch($batch)->ownedBy($creator)->create();
    $question = RegistrationQuestion::factory()->for($form, 'form')->create();

    $this->actingAs($otherAdmin);

    $response = match ($action) {
        'create form' => $this->post(route('batches.registration-form.store', regCustomizeBatch('ADM-RC-CREATOR'))),
        'update settings' => $this->put(route('registration-forms.update', $form), regCustomizeSettings()),
        'upload background' => $this->post(route('registration-forms.background.upload', $form), ['image' => UploadedFile::fake()->image('bg.jpg')]),
        'reset background' => $this->delete(route('registration-forms.background.destroy', $form)),
        'add question' => $this->post(route('registration-forms.questions.store', $form), ['type' => 'text', 'label' => 'Q?']),
        'edit question' => $this->put(route('registration-questions.update', $question), ['type' => 'text', 'label' => 'Changed?']),
        'delete question' => $this->delete(route('registration-questions.destroy', $question)),
        'move question up' => $this->post(route('registration-questions.move-up', $question)),
        'move question down' => $this->post(route('registration-questions.move-down', $question)),
    };

    $response->assertForbidden();

    expect($form->refresh()->title)->toBe('Program Registration')
        ->and(RegistrationQuestion::whereKey($question->id)->exists())->toBeTrue();
})->with([
    'create form',
    'update settings',
    'upload background',
    'reset background',
    'add question',
    'edit question',
    'delete question',
    'move question up',
    'move question down',
]);
