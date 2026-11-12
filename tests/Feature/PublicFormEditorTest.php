<?php

use App\Models\Employee;
use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormResponse;
use App\Models\PublicFormSection;
use App\Models\User;
use App\Support\PublicForms\PublicFormDefinition;

function editorAdmin(): User
{
    return User::factory()->create(['access' => 'admin']);
}

/**
 * A new form with one empty section, as "New Form" makes it.
 */
function editorForm(): PublicForm
{
    $form = PublicForm::factory()->closed()->create(['title' => 'Training Needs Survey']);
    PublicFormSection::factory()->for($form, 'form')->create(['title' => 'Questions', 'sort_order' => 0]);

    return $form->refresh();
}

/**
 * @return array<string, mixed>
 */
function editorQuestion(PublicForm $form, array $overrides = []): array
{
    return array_merge([
        'section_id' => $form->sections()->first()->id,
        'type' => PublicFormDefinition::TYPE_MULTIPLE,
        'question' => 'Which topics interest you?',
        'question_fil' => 'Aling mga paksa ang interesado kayo?',
        'instruction' => 'Select up to 2.',
        'instruction_fil' => 'Pumili ng hanggang 2.',
        'required' => true,
        'options' => [
            ['value' => null, 'label' => 'Leadership', 'label_fil' => 'Pamumuno', 'score' => null, 'exclusive' => false],
            ['value' => null, 'label' => 'Data Analysis', 'label_fil' => null, 'score' => null, 'exclusive' => false],
            ['value' => null, 'label' => 'None of these', 'label_fil' => 'Wala', 'score' => null, 'exclusive' => true],
        ],
        'allow_other' => true,
        'other_label' => 'Something else',
        'other_label_fil' => '',
        'rank' => null,
        'max' => 2,
    ], $overrides);
}

test('only admins can use the editor', function () {
    $form = editorForm();
    $section = $form->sections()->first();
    $question = PublicFormQuestion::factory()->for($form, 'form')->for($section, 'section')->create();
    $user = User::factory()->create(['access' => 'user']);

    $this->actingAs($user)->post(route('public-forms.admin.sections.store', $form->slug), ['title' => 'More'])->assertForbidden();
    $this->actingAs($user)->put(route('public-form-sections.update', $section), ['title' => 'Renamed'])->assertForbidden();
    $this->actingAs($user)->delete(route('public-form-sections.destroy', $section))->assertForbidden();
    $this->actingAs($user)->post(route('public-form-sections.move', $section), ['direction' => 'up'])->assertForbidden();
    $this->actingAs($user)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form))->assertForbidden();
    $this->actingAs($user)->put(route('public-form-questions.update', $question), editorQuestion($form))->assertForbidden();
    $this->actingAs($user)->delete(route('public-form-questions.destroy', $question))->assertForbidden();
    $this->actingAs($user)->post(route('public-form-questions.move', $question), ['direction' => 'up'])->assertForbidden();

    expect($form->sections()->count())->toBe(1)
        ->and($form->questions()->count())->toBe(1);
});

test('admins can add a question; its answer key and choice values come from its words', function () {
    $form = editorForm();

    $this->actingAs(editorAdmin())
        ->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $question = PublicFormQuestion::where('public_form_id', $form->id)->sole();

    expect($question->answer_key)->toBe('which_topics_interest_you')
        ->and($question->type)->toBe('multiple')
        ->and($question->question_fil)->toBe('Aling mga paksa ang interesado kayo?')
        ->and($question->max_choices)->toBe(2)
        ->and($question->rank_count)->toBeNull()
        ->and($question->allow_other)->toBeTrue()
        ->and($question->other_label)->toBe('Something else')
        ->and($question->other_label_fil)->toBe('Iba pa')
        ->and($question->normalizedOptions())->toBe([
            ['value' => 'leadership', 'label' => 'Leadership', 'label_fil' => 'Pamumuno', 'score' => null, 'exclusive' => false],
            ['value' => 'data_analysis', 'label' => 'Data Analysis', 'label_fil' => null, 'score' => null, 'exclusive' => false],
            ['value' => 'none_of_these', 'label' => 'None of these', 'label_fil' => 'Wala', 'score' => null, 'exclusive' => true],
        ]);

    // The same question again gets its own key.
    $this->actingAs(editorAdmin())->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form));
    expect(PublicFormQuestion::latest('id')->first()->answer_key)->toBe('which_topics_interest_you_2');
});

test('the public form shows a new question right away', function () {
    $form = editorForm();
    $form->update(['is_open' => true]);

    $this->actingAs(editorAdmin())->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form));

    $this->get(route('public-forms.show', $form->slug))
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Form')
            ->has('questions', 1)
            ->where('questions.0.key', 'which_topics_interest_you')
            ->where('questions.0.max', 2)
            ->where('questions.0.options.2.exclusive', true)
            ->where('questions.0.other_label', 'Something else')
        );
});

test('editing a question keeps the values of existing choices and gives new ones their own', function () {
    $form = editorForm();
    $admin = editorAdmin();
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form));
    $question = PublicFormQuestion::where('public_form_id', $form->id)->sole();

    $this->actingAs($admin)->put(route('public-form-questions.update', $question), editorQuestion($form, [
        'question' => 'Which topics would you like to learn?',
        'options' => [
            // Renamed, but still the same choice.
            ['value' => 'leadership', 'label' => 'Leadership and coaching', 'label_fil' => 'Pamumuno', 'exclusive' => false],
            ['value' => null, 'label' => 'Writing', 'label_fil' => null, 'exclusive' => false],
            // "Data Analysis" was removed; a new choice with the same words must not take its value.
            ['value' => null, 'label' => 'Data analysis', 'label_fil' => null, 'exclusive' => false],
        ],
    ]))->assertSessionHasNoErrors();

    $question->refresh();

    expect($question->answer_key)->toBe('which_topics_interest_you')
        ->and($question->question)->toBe('Which topics would you like to learn?')
        ->and(array_column($question->normalizedOptions(), 'value'))->toBe(['leadership', 'writing', 'data_analysis_2'])
        ->and($question->normalizedOptions()[0]['label'])->toBe('Leadership and coaching');
});

test('rating numbers apply only to pick-one questions, and "only answer" only to pick-several', function () {
    $form = editorForm();
    $choices = [
        ['value' => null, 'label' => 'Poor', 'score' => 1, 'exclusive' => true],
        ['value' => null, 'label' => 'Good', 'score' => 2, 'exclusive' => false],
        ['value' => null, 'label' => 'Not applicable', 'score' => '', 'exclusive' => false],
    ];

    $this->actingAs(editorAdmin())->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, [
        'type' => PublicFormDefinition::TYPE_SINGLE,
        'question' => 'How was it?',
        'options' => $choices,
        'max' => 2,
    ]))->assertSessionHasNoErrors();

    $single = PublicFormQuestion::latest('id')->first();
    expect(array_column($single->normalizedOptions(), 'score'))->toBe([1, 2, null])
        ->and(array_column($single->normalizedOptions(), 'exclusive'))->toBe([false, false, false])
        ->and($single->max_choices)->toBeNull();

    $this->actingAs(editorAdmin())->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, [
        'question' => 'Which ones?',
        'options' => $choices,
        'max' => null,
    ]))->assertSessionHasNoErrors();

    $multiple = PublicFormQuestion::latest('id')->first();
    expect(array_column($multiple->normalizedOptions(), 'score'))->toBe([null, null, null])
        ->and(array_column($multiple->normalizedOptions(), 'exclusive'))->toBe([true, false, false])
        ->and($multiple->max_choices)->toBeNull();
});

test('a written-answer question keeps no choices', function () {
    $form = editorForm();

    $this->actingAs(editorAdmin())->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, [
        'type' => PublicFormDefinition::TYPE_TEXT,
        'question' => 'Any suggestions?',
        'required' => false,
    ]))->assertSessionHasNoErrors();

    $question = PublicFormQuestion::where('public_form_id', $form->id)->sole();
    expect($question->options)->toBeNull()
        ->and($question->allow_other)->toBeFalse()
        ->and($question->other_label)->toBeNull()
        ->and($question->max_choices)->toBeNull()
        ->and($question->is_required)->toBeFalse();
});

test('questions are checked before they are saved', function (array $overrides, string $errorKey) {
    $form = editorForm();
    $otherForm = PublicForm::factory()->withQuestions()->create();
    $overrides = array_map(fn ($value) => $value === 'OTHER_FORM_SECTION' ? $otherForm->sections()->first()->id : $value, $overrides);

    $this->actingAs(editorAdmin())
        ->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, $overrides))
        ->assertSessionHasErrors($errorKey);

    expect(PublicFormQuestion::where('public_form_id', $form->id)->count())->toBe(0);
})->with([
    'no question' => [['question' => ''], 'question'],
    'an unknown type' => [['type' => 'dropdown'], 'type'],
    'a section of another form' => [['section_id' => 'OTHER_FORM_SECTION'], 'section_id'],
    'only one choice' => [['options' => [['label' => 'Yes']]], 'options'],
    'a blank choice' => [['options' => [['label' => 'Yes'], ['label' => '']]], 'options.1.label'],
    'the same choice twice' => [['options' => [['label' => 'Yes'], ['label' => 'yes']]], 'options.1.label'],
    'a ranking without how many' => [['type' => 'ranking', 'rank' => null], 'rank'],
    'ranking more than the choices' => [['type' => 'ranking', 'rank' => 5, 'allow_other' => false], 'rank'],
    'a limit above the choices' => [['max' => 9], 'max'],
]);

test('a question can be moved within its section, or to another section where it goes last', function () {
    $form = editorForm();
    $admin = editorAdmin();
    $first = $form->sections()->first();
    $second = PublicFormSection::factory()->for($form, 'form')->create(['title' => 'More', 'sort_order' => 1]);

    foreach (['One?', 'Two?', 'Three?'] as $text) {
        $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, ['question' => $text]));
    }
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, ['question' => 'Later?', 'section_id' => $second->id]));

    $three = PublicFormQuestion::where('question', 'Three?')->sole();
    $this->actingAs($admin)->post(route('public-form-questions.move', $three), ['direction' => 'up']);

    expect($first->questions()->pluck('question')->all())->toBe(['One?', 'Three?', 'Two?']);

    $one = PublicFormQuestion::where('question', 'One?')->sole();
    $this->actingAs($admin)->put(route('public-form-questions.update', $one), editorQuestion($form, ['question' => 'One?', 'section_id' => $second->id]));

    expect($first->questions()->pluck('question')->all())->toBe(['Three?', 'Two?'])
        ->and($second->questions()->pluck('question')->all())->toBe(['Later?', 'One?']);

    $this->actingAs($admin)->post(route('public-form-questions.move', $one), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
});

test('removing a question takes it off the form but keeps the answers already sent', function () {
    $form = editorForm();
    $form->update(['is_open' => true]);
    $admin = editorAdmin();
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form));
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, [
        'type' => 'text',
        'question' => 'Anything else?',
        'required' => false,
    ]));

    $question = PublicFormQuestion::where('answer_key', 'which_topics_interest_you')->sole();
    $response = PublicFormResponse::factory()->create([
        'form_slug' => $form->slug,
        'answers' => ['which_topics_interest_you' => ['leadership'], 'anything_else' => null],
    ]);

    $this->actingAs($admin)->delete(route('public-form-questions.destroy', $question))->assertSessionHas('success');

    expect($question->fresh()->trashed())->toBeTrue();

    $this->get(route('public-forms.show', $form->slug))
        ->assertInertia(fn ($page) => $page->has('questions', 1)->where('questions.0.key', 'anything_else'));

    $readable = $form->refresh()->definition()->readableAnswers($response->answers, []);
    expect($readable)->toHaveCount(2)
        ->and($readable[1]['key'])->toBe('which_topics_interest_you')
        ->and($readable[1]['removed'])->toBeTrue()
        ->and($readable[1]['lines'])->toBe(['Leadership']);

    // A new question with the same words doesn't take the removed one's answers.
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form));
    expect(PublicFormQuestion::latest('id')->first()->answer_key)->toBe('which_topics_interest_you_2');
});

test('admins can add, edit, move and delete sections', function () {
    $form = editorForm();
    $admin = editorAdmin();

    $this->actingAs($admin)
        ->post(route('public-forms.admin.sections.store', $form->slug), [
            'title' => 'For Supervisors',
            'step_label' => 'Supervisors',
            'description' => 'Only for SG 18 and above.',
            'min_salary_grade' => 18,
        ])
        ->assertSessionHasNoErrors();

    $section = PublicFormSection::where('title', 'For Supervisors')->sole();
    expect($section->step_label)->toBe('Supervisors')
        ->and($section->min_salary_grade)->toBe(18)
        ->and($section->audienceLabel())->toBe('For salary grade 18 and above')
        ->and($form->sections()->pluck('title')->all())->toBe(['Questions', 'For Supervisors']);

    $this->actingAs($admin)->post(route('public-form-sections.move', $section), ['direction' => 'up']);
    expect($form->sections()->pluck('title')->all())->toBe(['For Supervisors', 'Questions']);

    $this->actingAs($admin)
        ->put(route('public-form-sections.update', $section), ['title' => 'Supervisors', 'step_label' => '', 'description' => '', 'min_salary_grade' => ''])
        ->assertSessionHasNoErrors();

    $section->refresh();
    expect($section->title)->toBe('Supervisors')
        ->and($section->step_label)->toBeNull()
        ->and($section->min_salary_grade)->toBeNull()
        ->and($section->audienceLabel())->toBeNull();

    $this->actingAs($admin)->delete(route('public-form-sections.destroy', $section))->assertSessionHas('success');
    expect($form->sections()->pluck('title')->all())->toBe(['Questions']);
});

test('a section with questions, or the only section, cannot be deleted', function () {
    $form = editorForm();
    $admin = editorAdmin();
    $only = $form->sections()->first();

    $this->actingAs($admin)->delete(route('public-form-sections.destroy', $only))->assertSessionHasErrors('section');

    $second = PublicFormSection::factory()->for($form, 'form')->create(['title' => 'More', 'sort_order' => 1]);
    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, ['section_id' => $second->id]));

    $this->actingAs($admin)->delete(route('public-form-sections.destroy', $second))->assertSessionHasErrors('section');

    expect($form->sections()->count())->toBe(2);
});

test('sections are checked before they are saved', function () {
    $form = editorForm();

    $this->actingAs(editorAdmin())
        ->post(route('public-forms.admin.sections.store', $form->slug), ['title' => '', 'step_label' => str_repeat('s', 31), 'min_salary_grade' => 40])
        ->assertSessionHasErrors(['title', 'step_label', 'min_salary_grade']);

    expect($form->sections()->count())->toBe(1);
});

test('a new form can be built, opened and answered from start to finish', function () {
    $admin = editorAdmin();
    $this->actingAs($admin)->post(route('public-forms.admin.store'), ['title' => 'Coaching Survey']);
    $form = PublicForm::where('title', 'Coaching Survey')->sole();

    $this->actingAs($admin)->post(route('public-forms.admin.questions.store', $form->slug), editorQuestion($form, [
        'type' => PublicFormDefinition::TYPE_RANKING,
        'question' => 'Rank these topics',
        'rank' => 2,
        'max' => null,
    ]))->assertSessionHasNoErrors();

    $this->actingAs($admin)->put(route('public-forms.admin.update', $form->slug), ['is_open' => true])->assertSessionHasNoErrors();

    Employee::forceCreate([
        'EMPCODE' => 'EMP-COACH-1', 'OFFICE/DIVISION' => 'Training Division', 'LASTNAME' => 'Reyes', 'FIRSTNAME' => 'Ana', 'MI' => '',
        'POSITION' => 'Trainer', 'SG' => '11', 'PLANTILLA STATUS' => 'Permanent', 'SEX' => 'F', 'REGION' => 'NCR', 'OFFICE' => 'TDI',
        'LOCATION' => 'Taguig', 'SECTION' => '', 'UNIT' => '',
    ]);

    $this->post(route('public-forms.submit', $form->slug), [
        'empcode' => 'EMP-COACH-1',
        'email' => 'ana@example.com',
        'data_privacy_consent' => true,
        'answers' => ['rank_these_topics' => ['data_analysis', PublicFormDefinition::OTHER_VALUE]],
        'answers_other' => ['rank_these_topics' => 'Public speaking'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('public-forms.success', $form->slug));

    $response = PublicFormResponse::where('form_slug', $form->slug)->sole();
    expect($form->refresh()->definition()->readableAnswers($response->answers, $response->other_answers)[0]['lines'])
        ->toBe(['1. Data Analysis', '2. Other: Public speaking'])
        ->and($response->reference)->toBe(sprintf('CS-%06d', $response->id));
});
