<?php

use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormEditor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function regEditorsUser(string $empcode, string $access = 'admin', ?string $name = null): User
{
    return User::factory()->create(array_filter(['empcode' => $empcode, 'access' => $access, 'name' => $name]));
}

/**
 * A form owned by Olivia Owner, who added Eddie Editor as its one editor.
 *
 * @return array{form: RegistrationForm, owner: User, editor: User}
 */
function regEditorsSetup(): array
{
    $owner = regEditorsUser('ADM-ED-OWNER', name: 'Olivia Owner');
    $editor = regEditorsUser('ADM-ED-EDITOR', name: 'Eddie Editor');
    $form = RegistrationForm::factory()->ownedBy($owner)->create();
    RegistrationFormEditor::factory()->forForm($form)->forUser($editor)->addedBy($owner)->create();

    return ['form' => $form, 'owner' => $owner, 'editor' => $editor];
}

function regEditorsSettings(): array
{
    return [
        'title' => 'Edited by a manager',
        'intro_text' => null,
        'theme' => 'teal',
        'is_open' => true,
        'closes_at' => null,
        'slot_limit' => null,
    ];
}

function regEditorsPreviewUrl(RegistrationForm $form): string
{
    return route('join.show', ['slug' => $form->slug, 'preview' => 1]);
}

beforeEach(function () {
    Mail::fake();
});

test('the owner, a superadmin and an added editor can change the form and approve registrations', function (string $who) {
    ['form' => $form, 'owner' => $owner, 'editor' => $editor] = regEditorsSetup();
    $user = match ($who) {
        'owner' => $owner,
        'editor' => $editor,
        'superadmin' => regEditorsUser('SUP-ED-01', 'superadmin'),
    };
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs($user)
        ->put(route('registration-forms.update', $form), regEditorsSettings())
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('registration-forms.questions.store', $form), ['type' => 'text', 'label' => 'Dietary needs?'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('program-registrations.approve', $registration))
        ->assertSessionHas('success');

    expect($form->refresh()->title)->toBe('Edited by a manager')
        ->and($form->questions()->where('label', 'Dietary needs?')->exists())->toBeTrue()
        ->and($registration->refresh()->status)->toBe(ProgramRegistration::STATUS_APPROVED);
})->with(['owner', 'editor', 'superadmin']);

test('any other admin gets 403, including the program creator when someone else owns the form', function (string $who) {
    ['form' => $form] = regEditorsSetup();
    $programCreator = regEditorsUser('ADM-ED-CREATOR');
    $form->batch->program->update(['added_by' => $programCreator->empcode]);
    $user = $who === 'program creator' ? $programCreator : regEditorsUser('ADM-ED-OTHER');
    $registration = ProgramRegistration::factory()->forForm($form)->create();

    $this->actingAs($user)->put(route('registration-forms.update', $form), regEditorsSettings())->assertForbidden();
    $this->actingAs($user)->post(route('registration-forms.questions.store', $form), ['type' => 'text', 'label' => 'Q?'])->assertForbidden();
    $this->actingAs($user)->post(route('program-registrations.approve', $registration))->assertForbidden();

    expect($form->refresh()->title)->toBe('Program Registration')
        ->and($form->questions()->count())->toBe(0)
        ->and($registration->refresh()->status)->toBe(ProgramRegistration::STATUS_PENDING);
})->with(['other admin', 'program creator']);

test('the owner and superadmins can add and remove editors, and who added each one is saved', function (string $who) {
    ['form' => $form, 'owner' => $owner] = regEditorsSetup();
    $manager = $who === 'owner' ? $owner : regEditorsUser('SUP-ED-01', 'superadmin', 'Sam Super');
    $newEditor = regEditorsUser('ADM-ED-NEW', name: 'Nina New');

    $this->actingAs($manager)
        ->post(route('registration-forms.editors.store', $form), ['user_id' => $newEditor->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $added = $form->editors()->where('user_id', $newEditor->id)->sole();

    expect($added)
        ->added_by_empcode->toBe($manager->empcode)
        ->added_by_name->toBe($manager->name)
        ->and($form->canBeEditedBy($newEditor))->toBeTrue();

    $this->actingAs($manager)
        ->delete(route('registration-form-editors.destroy', $added))
        ->assertSessionHas('success');

    expect(RegistrationFormEditor::whereKey($added->id)->exists())->toBeFalse()
        ->and($form->canBeEditedBy($newEditor))->toBeFalse();
})->with(['owner', 'superadmin']);

test('editors and other admins cannot add, remove or search for editors', function (string $who) {
    ['form' => $form, 'editor' => $editor] = regEditorsSetup();
    $user = $who === 'editor' ? $editor : regEditorsUser('ADM-ED-OTHER');
    $candidate = regEditorsUser('ADM-ED-NEW');

    $this->actingAs($user)
        ->post(route('registration-forms.editors.store', $form), ['user_id' => $candidate->id])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('registration-form-editors.destroy', $form->editors()->sole()))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('registration-forms.editors.search', ['registrationForm' => $form, 'q' => 'ADM']))
        ->assertForbidden();

    expect($form->editors()->pluck('user_id')->all())->toBe([$editor->id]);
})->with(['editor', 'other admin']);

test('only admins who cannot already edit the form can be added', function (string $case, string $message) {
    ['form' => $form, 'owner' => $owner, 'editor' => $editor] = regEditorsSetup();
    $userId = match ($case) {
        'user' => regEditorsUser('USR-ED-01', 'user')->id,
        'guest' => regEditorsUser('GST-ED-01', 'guest')->id,
        'owner' => $owner->id,
        'superadmin' => regEditorsUser('SUP-ED-01', 'superadmin')->id,
        'existing editor' => $editor->id,
        'unknown user' => 999999,
    };

    $this->actingAs($owner)
        ->post(route('registration-forms.editors.store', $form), ['user_id' => $userId])
        ->assertInvalid(['user_id' => $message]);

    expect($form->editors()->count())->toBe(1);
})->with([
    'user' => ['user', "doesn't have admin access"],
    'guest' => ['guest', "doesn't have admin access"],
    'owner' => ['owner', 'owns this form'],
    'superadmin' => ['superadmin', 'is a superadmin'],
    'existing editor' => ['existing editor', 'already an editor'],
    'unknown user' => ['unknown user', 'invalid'],
]);

test('a removed editor loses access', function () {
    ['form' => $form, 'owner' => $owner, 'editor' => $editor] = regEditorsSetup();

    $this->actingAs($editor)->put(route('registration-forms.update', $form), regEditorsSettings())->assertSessionHasNoErrors();

    $this->actingAs($owner)->delete(route('registration-form-editors.destroy', $form->editors()->sole()));

    $this->actingAs($editor)->put(route('registration-forms.update', $form), regEditorsSettings())->assertForbidden();
    $this->actingAs($editor)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertInertia(fn ($page) => $page->where('canEdit', false)->has('registrationForm.editors', 0));
});

test('an editor whose access drops to user loses edit rights until admin access is back', function () {
    ['form' => $form, 'owner' => $owner, 'editor' => $editor] = regEditorsSetup();
    $form->update(['is_open' => false]);

    $editor->update(['access' => 'user']);

    expect($form->canBeEditedBy($editor))->toBeFalse();
    $this->actingAs($editor)->put(route('registration-forms.update', $form), regEditorsSettings())->assertForbidden();
    $this->actingAs($editor)->get(regEditorsPreviewUrl($form))->assertInertia(fn ($page) => $page->component('Registration/Closed'));

    // The owner still sees them in the list, marked as having no admin access.
    $this->actingAs($owner)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertInertia(fn ($page) => $page->where('registrationForm.editors.0.has_admin_access', false));

    $editor->update(['access' => 'admin']);

    $this->actingAs($editor)->get(regEditorsPreviewUrl($form))->assertInertia(fn ($page) => $page->component('Registration/Form'));
    $this->actingAs($editor)->put(route('registration-forms.update', $form), regEditorsSettings())->assertSessionHasNoErrors();
});

test('editors can preview a closed form, but other admins cannot', function () {
    ['form' => $form, 'editor' => $editor] = regEditorsSetup();
    $form->update(['is_open' => false]);

    $this->actingAs($editor)
        ->get(regEditorsPreviewUrl($form))
        ->assertInertia(fn ($page) => $page
            ->component('Registration/Form')
            ->where('preview.closedReason', RegistrationForm::CLOSED_NOT_OPEN)
        );

    $this->actingAs(regEditorsUser('ADM-ED-OTHER'))
        ->get(regEditorsPreviewUrl($form))
        ->assertInertia(fn ($page) => $page->component('Registration/Closed'));
});

test('the registration page sends the owner, the editors and what the viewer can do', function (string $who, bool $canEdit, bool $canManageEditors) {
    ['form' => $form, 'owner' => $owner, 'editor' => $editor] = regEditorsSetup();
    $viewer = match ($who) {
        'owner' => $owner,
        'editor' => $editor,
        'superadmin' => regEditorsUser('SUP-ED-01', 'superadmin'),
        'other admin' => regEditorsUser('ADM-ED-OTHER'),
    };

    $this->actingAs($viewer)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canEdit', $canEdit)
            ->where('canManageEditors', $canManageEditors)
            ->where('canCreateForm', false)
            ->where('registrationForm.owner', ['name' => 'Olivia Owner', 'empcode' => 'ADM-ED-OWNER'])
            ->has('registrationForm.editors', 1)
            ->where('registrationForm.editors.0.name', 'Eddie Editor')
            ->where('registrationForm.editors.0.empcode', 'ADM-ED-EDITOR')
            ->where('registrationForm.editors.0.has_admin_access', true)
            ->where('registrationForm.editors.0.added_by_name', 'Olivia Owner')
            ->has('registrationForm.editors.0.added_at')
        );
})->with([
    'owner' => ['owner', true, true],
    'editor' => ['editor', true, false],
    'superadmin' => ['superadmin', true, true],
    'other admin' => ['other admin', false, false],
]);

test('a form with no recorded owner can only be changed by superadmins and editors', function () {
    $form = RegistrationForm::factory()->create();
    $editor = regEditorsUser('ADM-ED-EDITOR');
    RegistrationFormEditor::factory()->forForm($form)->forUser($editor)->create();

    expect($form->canBeEditedBy(regEditorsUser('ADM-ED-OTHER')))->toBeFalse()
        ->and($form->canBeEditedBy($editor))->toBeTrue()
        ->and($form->canManageEditorsBy($editor))->toBeFalse()
        ->and($form->canManageEditorsBy(regEditorsUser('SUP-ED-01', 'superadmin')))->toBeTrue();

    $this->actingAs($editor)
        ->get(route('batches.registration.edit', $form->batch_id))
        ->assertInertia(fn ($page) => $page->where('registrationForm.owner', null)->where('canEdit', true));
});

test('the editor search finds admins by name or employee code and says who cannot be added', function () {
    ['form' => $form, 'owner' => $owner] = regEditorsSetup();
    regEditorsUser('ADM-ED-ANA', name: 'Ana Reyes');
    regEditorsUser('SUP-ED-ANA', 'superadmin', 'Ana Santos');
    regEditorsUser('USR-ED-ANA', 'user', 'Ana Cruz');

    $search = fn (string $query) => collect(
        $this->actingAs($owner)
            ->getJson(route('registration-forms.editors.search', ['registrationForm' => $form, 'q' => $query]))
            ->assertOk()
            ->json()
    )->pluck('unavailable', 'empcode')->all();

    expect($search('Ana'))->toBe(['ADM-ED-ANA' => null, 'SUP-ED-ANA' => 'Superadmin'])
        ->and($search('ADM-ED'))->toBe(['ADM-ED-ANA' => null, 'ADM-ED-EDITOR' => 'Already an editor', 'ADM-ED-OWNER' => 'Owner'])
        ->and($search('A'))->toBe([]);
});
