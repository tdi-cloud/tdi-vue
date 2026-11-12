<?php

use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormResponse;
use App\Models\PublicFormSection;
use App\Models\SiteImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

function publicFormAdmin(string $access = 'admin'): User
{
    return User::factory()->create(['access' => $access]);
}

/** The Learning and Development Survey, added by a migration. */
function surveyForm(): PublicForm
{
    return PublicForm::where('slug', 'ld-survey-2026')->firstOrFail();
}

test('guests are sent to the login page and regular users are refused', function () {
    $this->get(route('public-forms.admin.index'))->assertRedirect(route('login'));

    $user = User::factory()->create(['access' => 'user']);
    $slug = surveyForm()->slug;

    $this->actingAs($user)->get(route('public-forms.admin.index'))->assertForbidden();
    $this->actingAs($user)->post(route('public-forms.admin.store'), ['title' => 'Mine'])->assertForbidden();
    $this->actingAs($user)->get(route('public-forms.admin.show', $slug))->assertForbidden();
    $this->actingAs($user)->put(route('public-forms.admin.update', $slug), ['is_open' => false])->assertForbidden();
    $this->actingAs($user)->delete(route('public-forms.admin.destroy', $slug))->assertForbidden();
    $this->actingAs($user)->get(route('public-forms.admin.export', $slug))->assertForbidden();
    $this->actingAs($user)->put(route('public-forms.admin.details.update', $slug), ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($user)
        ->post(route('public-forms.admin.background.upload', $slug), ['image' => UploadedFile::fake()->image('bg.jpg')])
        ->assertForbidden();
    $this->actingAs($user)->put(route('public-forms.admin.background.update', $slug), ['hide_background' => true])->assertForbidden();
    $this->actingAs($user)->delete(route('public-forms.admin.background.destroy', $slug))->assertForbidden();

    expect(PublicForm::count())->toBe(1)
        ->and(surveyForm()->is_open)->toBeTrue()
        ->and(surveyForm()->title)->toBe('Learning and Development Survey');
});

test('admins see every public form with its link, status, question count and response count', function () {
    PublicFormResponse::factory()->count(3)->create();
    $draft = PublicForm::factory()->closed()->create(['title' => 'Draft Survey', 'created_by' => publicFormAdmin()->id]);

    $this->actingAs(publicFormAdmin())
        ->get(route('public-forms.admin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Admin/Index')
            ->has('forms', 2)
            ->where('forms.0.slug', $draft->slug)
            ->where('forms.0.closed_reason', PublicForm::CLOSED_NOT_OPEN)
            ->where('forms.0.question_count', 0)
            ->where('forms.1.slug', 'ld-survey-2026')
            ->where('forms.1.public_url', route('public-forms.show', 'ld-survey-2026'))
            ->where('forms.1.is_open', true)
            ->where('forms.1.question_count', 13)
            ->where('forms.1.responses_count', 3)
        );
});

test('admins can create a blank form; it starts closed with one empty section', function () {
    $admin = publicFormAdmin();

    $response = $this->actingAs($admin)->post(route('public-forms.admin.store'), ['title' => 'Training Needs Survey 2027']);

    $form = PublicForm::where('title', 'Training Needs Survey 2027')->sole();
    $response->assertRedirect(route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'questions']))->assertSessionHas('success');

    expect($form->slug)->toBe('training-needs-survey-2027')
        ->and($form->is_open)->toBeFalse()
        ->and($form->reference_prefix)->toBe('TNS')
        ->and($form->created_by)->toBe($admin->id)
        ->and($form->sections()->pluck('title')->all())->toBe(['Questions'])
        ->and($form->questions()->count())->toBe(0);

    // The same title again gets its own link.
    $this->actingAs($admin)->post(route('public-forms.admin.store'), ['title' => 'Training Needs Survey 2027']);
    expect(PublicForm::where('title', 'Training Needs Survey 2027')->pluck('slug')->sort()->values()->all())
        ->toBe(['training-needs-survey-2027', 'training-needs-survey-2027-2']);
});

test('admins can create a form as a copy of another, without its responses', function () {
    PublicFormResponse::factory()->create();

    $this->actingAs(publicFormAdmin())
        ->post(route('public-forms.admin.store'), ['title' => 'L&D Survey 2027', 'copy_from' => surveyForm()->id])
        ->assertSessionHasNoErrors();

    $copy = PublicForm::where('title', 'L&D Survey 2027')->sole();
    $source = surveyForm()->definition();
    $copied = $copy->definition();

    expect($copy->is_open)->toBeFalse()
        ->and($copy->kicker)->toBe(surveyForm()->kicker)
        ->and(array_column($copied->sections(), 'title'))->toBe(array_column($source->sections(), 'title'))
        ->and(array_column($copied->sections(), 'audience'))->toBe(array_column($source->sections(), 'audience'))
        ->and(array_column($copied->questions(), 'key'))->toBe(array_column($source->questions(), 'key'))
        ->and($copied->questions()[0]['options'])->toBe($source->questions()[0]['options'])
        ->and($copy->responses()->count())->toBe(0)
        ->and(surveyForm()->questions()->count())->toBe(13);
});

test('a new form needs a title, and a form to copy must exist', function () {
    $this->actingAs(publicFormAdmin())
        ->post(route('public-forms.admin.store'), ['title' => '', 'copy_from' => 999999])
        ->assertSessionHasErrors(['title', 'copy_from']);

    expect(PublicForm::count())->toBe(1);
});

test('the responses page lists the answers in words, newest first, and can be searched', function () {
    $older = PublicFormResponse::factory()->create(['full_name' => 'Maria Santos', 'created_at' => now()->subDay()]);
    $newer = PublicFormResponse::factory()->create([
        'full_name' => 'Pedro Reyes',
        'confirmation_sent_at' => null,
        'other_answers' => ['priority_areas' => 'Public speaking'],
        'answers' => array_merge(PublicFormResponse::factory()->raw()['answers'], [
            'priority_areas' => ['leadership', 'ethics', '__other__'],
        ]),
    ]);

    $admin = publicFormAdmin('superadmin');

    $this->actingAs($admin)
        ->get(route('public-forms.admin.show', 'ld-survey-2026'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Admin/Show')
            ->where('initialTab', 'responses')
            ->where('stats.total', 2)
            ->where('stats.emails_not_sent', 1)
            ->has('editor.sections', 3)
            ->has('editor.sections.0.questions', 3)
            ->has('responses.data', 2)
            ->where('responses.data.0.id', $newer->id)
            ->where('responses.data.0.reference', sprintf('LDS-%06d', $newer->id))
            ->where('responses.data.0.confirmation_sent', false)
            ->where('responses.data.0.answers.0.question', 'Among the following learning and development areas, which would you like TDI to prioritize for your professional development in 2027?')
            ->where('responses.data.0.answers.0.lines', [
                '1. Leadership, change management, coaching, and mentoring',
                '2. Ethics, integrity, and customer service',
                '3. Other: Public speaking',
            ])
            ->where('responses.data.1.id', $older->id)
        );

    $this->actingAs($admin)
        ->get(route('public-forms.admin.show', ['slug' => 'ld-survey-2026', 'search' => 'Santos']))
        ->assertInertia(fn ($page) => $page
            ->has('responses.data', 1)
            ->where('responses.data.0.name', 'Maria Santos')
            ->where('filters.search', 'Santos')
        );
});

test('a form without responses opens on its questions tab', function () {
    $form = PublicForm::factory()->withQuestions()->create();

    $this->actingAs(publicFormAdmin())
        ->get(route('public-forms.admin.show', $form->slug))
        ->assertInertia(fn ($page) => $page
            ->where('initialTab', 'questions')
            ->where('form.preview_url', route('public-forms.show', ['slug' => $form->slug, 'preview' => 1]))
            ->where('editor.sections.0.questions.0.options.0.label', 'Blue')
        );

    $this->actingAs(publicFormAdmin())
        ->get(route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'settings']))
        ->assertInertia(fn ($page) => $page->where('initialTab', 'settings'));
});

test('an unknown form returns 404 on the admin pages', function () {
    $admin = publicFormAdmin();

    $this->actingAs($admin)->get(route('public-forms.admin.show', 'does-not-exist'))->assertNotFound();
    $this->actingAs($admin)->put(route('public-forms.admin.update', 'does-not-exist'), ['is_open' => false])->assertNotFound();
    $this->actingAs($admin)->get(route('public-forms.admin.export', 'does-not-exist'))->assertNotFound();
    $this->actingAs($admin)->delete(route('public-forms.admin.destroy', 'does-not-exist'))->assertNotFound();
    $this->actingAs($admin)
        ->post(route('public-forms.admin.background.upload', 'does-not-exist'), ['image' => UploadedFile::fake()->image('bg.jpg')])
        ->assertNotFound();
    $this->actingAs($admin)->put(route('public-forms.admin.background.update', 'does-not-exist'), ['hide_background' => true])->assertNotFound();
    $this->actingAs($admin)->delete(route('public-forms.admin.background.destroy', 'does-not-exist'))->assertNotFound();
    $this->actingAs($admin)->put(route('public-forms.admin.details.update', 'does-not-exist'), ['title' => 'Title'])->assertNotFound();
});

test('admins can close the form and set a closing date', function () {
    $admin = publicFormAdmin();

    $this->actingAs($admin)
        ->from(route('public-forms.admin.show', 'ld-survey-2026'))
        ->put(route('public-forms.admin.update', 'ld-survey-2026'), ['is_open' => false, 'closes_at' => null])
        ->assertRedirect(route('public-forms.admin.show', 'ld-survey-2026'))
        ->assertSessionHas('success');

    $form = surveyForm();
    expect($form->is_open)->toBeFalse()
        ->and($form->closedReason())->toBe(PublicForm::CLOSED_NOT_OPEN)
        ->and($form->updated_by)->toBe($admin->id);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Closed'));

    $this->actingAs($admin)
        ->put(route('public-forms.admin.update', 'ld-survey-2026'), ['is_open' => true, 'closes_at' => '2026-12-15T17:00'])
        ->assertSessionHasNoErrors();

    $form->refresh();
    expect($form->is_open)->toBeTrue()
        ->and($form->closes_at->format('Y-m-d H:i'))->toBe('2026-12-15 17:00');
});

test('a form without questions cannot be opened', function () {
    $form = PublicForm::factory()->closed()->create();

    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.update', $form->slug), ['is_open' => true])
        ->assertSessionHasErrors(['is_open' => 'Add at least one question before opening the form.']);

    expect($form->refresh()->is_open)->toBeFalse();
});

test('the form closes by itself once the closing date passes', function () {
    Carbon::setTestNow('2026-12-01 09:00');
    surveyForm()->update(['closes_at' => '2026-12-15 17:00']);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Form')->where('form.closes_at_label', 'December 15, 2026 5:00 PM'));

    Carbon::setTestNow('2026-12-15 17:01');

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Closed')->where('form.closed_reason', PublicForm::CLOSED_DEADLINE));

    Carbon::setTestNow();
});

test('the settings are validated', function () {
    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.update', 'ld-survey-2026'), ['is_open' => 'maybe', 'closes_at' => 'next week-ish'])
        ->assertSessionHasErrors(['is_open', 'closes_at']);

    expect(surveyForm()->is_open)->toBeTrue();
});

test('admins can change the title, the line above it and the description, and every page shows them', function () {
    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.details.update', 'ld-survey-2026'), [
            'kicker' => 'For all TESDA employees',
            'title' => 'TDI Learning Needs Survey 2027',
            'description' => 'Help us plan the trainings for next year.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $form = surveyForm();
    expect($form->title)->toBe('TDI Learning Needs Survey 2027')
        ->and($form->kicker)->toBe('For all TESDA employees')
        ->and($form->description)->toBe('Help us plan the trainings for next year.');

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Form')
            ->where('form.title', 'TDI Learning Needs Survey 2027')
            ->where('form.kicker', 'For all TESDA employees')
            ->where('form.description', 'Help us plan the trainings for next year.')
        );

    $this->get(route('public-forms.index'))
        ->assertInertia(fn ($page) => $page->where('forms.0.title', 'TDI Learning Needs Survey 2027'));

    $this->get(route('public-forms.success', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->where('form.title', 'TDI Learning Needs Survey 2027'));

    $this->get(route('public-forms.admin.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->where('form.title', 'TDI Learning Needs Survey 2027'));
});

test('the title is required, while the line above it and the description can be removed', function () {
    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.details.update', 'ld-survey-2026'), ['title' => ' ', 'kicker' => '', 'description' => ''])
        ->assertSessionHasErrors('title');

    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.details.update', 'ld-survey-2026'), ['title' => 'Survey', 'kicker' => ' ', 'description' => ''])
        ->assertSessionHasNoErrors();

    $form = surveyForm();
    expect($form->title)->toBe('Survey')
        ->and($form->kicker)->toBeNull()
        ->and($form->description)->toBeNull();
});

test('changing the title keeps the open/closed and background settings', function () {
    surveyForm()->update(['is_open' => false, 'background_image' => 'public-form-backgrounds/survey.jpg']);

    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.details.update', 'ld-survey-2026'), ['title' => 'New title']);

    $form = surveyForm();
    expect($form->title)->toBe('New title')
        ->and($form->is_open)->toBeFalse()
        ->and($form->background_image)->toBe('public-form-backgrounds/survey.jpg');

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Closed')->where('form.title', 'New title'));
});

test('the title and description have length limits', function () {
    $this->actingAs(publicFormAdmin())
        ->put(route('public-forms.admin.details.update', 'ld-survey-2026'), [
            'kicker' => str_repeat('k', 101),
            'title' => str_repeat('t', 151),
            'description' => str_repeat('d', 501),
        ])
        ->assertSessionHasErrors(['kicker', 'title', 'description']);

    expect(surveyForm()->title)->toBe('Learning and Development Survey');
});

test('a public form uses the site-wide default background until it gets its own', function () {
    $this->actingAs(publicFormAdmin())
        ->get(route('public-forms.admin.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page
            ->where('defaultBackgroundUrl', SiteImage::urlFor(PublicForm::DEFAULT_BACKGROUND_KEY))
            ->where('form.background_image_url', null)
            ->where('form.hide_background', false)
        );

    foreach ([route('public-forms.index'), route('public-forms.show', 'ld-survey-2026')] as $url) {
        $this->get($url)->assertInertia(fn ($page) => $page->where('backgroundUrl', SiteImage::urlFor(PublicForm::DEFAULT_BACKGROUND_KEY)));
    }
});

test('admins can upload, replace and reset a form\'s own background picture', function () {
    Storage::fake('public');
    $admin = publicFormAdmin();

    $this->actingAs($admin)
        ->post(route('public-forms.admin.background.upload', 'ld-survey-2026'), ['image' => UploadedFile::fake()->image('first.jpg', 1600, 900)])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $first = surveyForm()->background_image;
    expect($first)->toStartWith(PublicForm::BACKGROUND_DIRECTORY.'/');
    Storage::disk('public')->assertExists($first);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', Storage::disk('public')->url($first)));

    // Replacing the picture deletes the old file.
    $this->actingAs($admin)
        ->post(route('public-forms.admin.background.upload', 'ld-survey-2026'), ['image' => UploadedFile::fake()->image('second.png', 1600, 900)]);

    $second = surveyForm()->background_image;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    // Back to the site default: the file is deleted.
    $this->actingAs($admin)
        ->delete(route('public-forms.admin.background.destroy', 'ld-survey-2026'))
        ->assertSessionHas('success');

    expect(surveyForm()->background_image)->toBeNull();
    Storage::disk('public')->assertMissing($second);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', SiteImage::urlFor(PublicForm::DEFAULT_BACKGROUND_KEY)));
});

test('"No background" shows a plain page and keeps the picture to switch back to', function () {
    Storage::fake('public');
    $admin = publicFormAdmin();

    $this->actingAs($admin)
        ->post(route('public-forms.admin.background.upload', 'ld-survey-2026'), ['image' => UploadedFile::fake()->image('bg.jpg')]);
    $path = surveyForm()->background_image;

    $this->actingAs($admin)
        ->put(route('public-forms.admin.background.update', 'ld-survey-2026'), ['hide_background' => true])
        ->assertSessionHasNoErrors();

    expect(surveyForm()->hide_background)->toBeTrue()
        ->and(surveyForm()->background_image)->toBe($path);
    Storage::disk('public')->assertExists($path);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Form')->where('backgroundUrl', null));

    $this->actingAs($admin)
        ->put(route('public-forms.admin.background.update', 'ld-survey-2026'), ['hide_background' => false]);

    $this->get(route('public-forms.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page->where('backgroundUrl', Storage::disk('public')->url($path)));
});

test('uploading a picture or going back to the site default turns "No background" off', function () {
    Storage::fake('public');
    $admin = publicFormAdmin();
    surveyForm()->update(['hide_background' => true]);

    $this->actingAs($admin)
        ->post(route('public-forms.admin.background.upload', 'ld-survey-2026'), ['image' => UploadedFile::fake()->image('bg.jpg')]);
    expect(surveyForm()->hide_background)->toBeFalse();

    surveyForm()->update(['hide_background' => true]);

    $this->actingAs($admin)->delete(route('public-forms.admin.background.destroy', 'ld-survey-2026'));
    expect(surveyForm()->hide_background)->toBeFalse()
        ->and(surveyForm()->background_image)->toBeNull();
});

test('only JPG, PNG or WebP pictures up to 5 MB are accepted as backgrounds', function (UploadedFile $file) {
    Storage::fake('public');

    $this->actingAs(publicFormAdmin())
        ->post(route('public-forms.admin.background.upload', 'ld-survey-2026'), ['image' => $file])
        ->assertSessionHasErrors('image');

    expect(surveyForm()->background_image)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'a PDF' => fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
    'a GIF' => fn () => UploadedFile::fake()->image('animation.gif'),
    'larger than 5 MB' => fn () => UploadedFile::fake()->image('huge.jpg')->size(6000),
]);

test('a form without responses can be deleted with its sections and questions', function () {
    Storage::fake('public');
    $form = PublicForm::factory()->withQuestions()->create(['background_image' => 'public-form-backgrounds/old.jpg']);
    Storage::disk('public')->put('public-form-backgrounds/old.jpg', 'picture');

    $this->actingAs(publicFormAdmin())
        ->delete(route('public-forms.admin.destroy', $form->slug))
        ->assertRedirect(route('public-forms.admin.index'))
        ->assertSessionHas('success');

    expect(PublicForm::whereKey($form->id)->exists())->toBeFalse()
        ->and(PublicFormSection::where('public_form_id', $form->id)->exists())->toBeFalse()
        ->and(PublicFormQuestion::withTrashed()->where('public_form_id', $form->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('public-form-backgrounds/old.jpg');

    $this->get(route('public-forms.show', $form->slug))->assertNotFound();
});

test('a form with responses cannot be deleted', function () {
    PublicFormResponse::factory()->create();

    $this->actingAs(publicFormAdmin())
        ->delete(route('public-forms.admin.destroy', 'ld-survey-2026'))
        ->assertSessionHas('error');

    expect(PublicForm::where('slug', 'ld-survey-2026')->exists())->toBeTrue()
        ->and(PublicFormResponse::count())->toBe(1);
});

test('admins can delete one response, and that employee can answer again', function () {
    $keep = PublicFormResponse::factory()->create();
    $test = PublicFormResponse::factory()->create(['full_name' => 'Test Answer']);

    $this->actingAs(publicFormAdmin())
        ->delete(route('public-forms.admin.responses.destroy', ['slug' => 'ld-survey-2026', 'publicFormResponse' => $test->id]))
        ->assertSessionHas('success');

    expect(PublicFormResponse::pluck('id')->all())->toBe([$keep->id]);

    // A response of another form can't be deleted through this form's link.
    $other = PublicForm::factory()->withQuestions()->create();
    $this->actingAs(publicFormAdmin())
        ->delete(route('public-forms.admin.responses.destroy', ['slug' => $other->slug, 'publicFormResponse' => $keep->id]))
        ->assertNotFound();

    expect(PublicFormResponse::count())->toBe(1);
});

test('answers to a removed question still show in the responses and the CSV', function () {
    $response = PublicFormResponse::factory()->create();
    PublicFormQuestion::where('public_form_id', surveyForm()->id)->where('answer_key', 'motivation')->firstOrFail()->delete();

    $this->actingAs(publicFormAdmin())
        ->get(route('public-forms.admin.show', 'ld-survey-2026'))
        ->assertInertia(fn ($page) => $page
            ->where('form.question_count', 12)
            ->where('editor.removed_count', 1)
            ->where('responses.data.0.answers', fn ($answers) => collect($answers)->contains(fn ($answer) => $answer['key'] === 'motivation'
                && $answer['removed'] === true
                && $answer['number'] === null
                && $answer['lines'] === ['Career advancement and professional growth']))
        );

    $csv = $this->actingAs(publicFormAdmin())->get(route('public-forms.admin.export', 'ld-survey-2026'))->streamedContent();
    [$headers, $row] = array_map('str_getcsv', preg_split('/\r?\n/', trim(ltrim($csv, "\xEF\xBB\xBF"))));
    $column = array_search('What primarily motivates you to participate in learning and development activities? (removed)', $headers, true);

    expect($column)->not->toBeFalse()
        ->and($row[$column])->toBe('Career advancement and professional growth')
        ->and($row[0])->toBe($response->reference);
});

test('the CSV export has the employee details and one column per question, with a column per rank', function () {
    PublicFormResponse::factory()->create([
        'empcode' => 'EMP-CSV-1',
        'full_name' => 'Maria Santos',
        'answers' => array_merge(PublicFormResponse::factory()->raw()['answers'], [
            'barriers' => ['workload', 'schedule_conflict'],
        ]),
    ]);

    $response = $this->actingAs(publicFormAdmin())->get(route('public-forms.admin.export', 'ld-survey-2026'));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $rows = array_map('str_getcsv', preg_split('/\r?\n/', trim(ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));
    [$headers, $row] = $rows;
    $cell = fn (string $header) => $row[array_search($header, $headers, true)];

    expect($rows)->toHaveCount(2)
        ->and($cell('EMPCODE'))->toBe('EMP-CSV-1')
        ->and($cell('Name'))->toBe('Maria Santos')
        ->and($cell('Salary Grade'))->toBe('15')
        ->and($cell('Q1 Among the following learning and development areas, which would you like TDI to prioritize for your professional development in 2027? (Rank 1)'))
        ->toBe('Leadership, change management, coaching, and mentoring')
        ->and($cell('Q1 Among the following learning and development areas, which would you like TDI to prioritize for your professional development in 2027? (Rank 3)'))
        ->toBe('Ethics, integrity, and customer service')
        ->and($cell('Q5 What are the biggest challenges that prevent you from participating in or completing L&D activities?'))
        ->toBe('Heavy workload and competing deadlines; Training schedules conflicting with work responsibilities')
        ->and($cell('Q4 How satisfied are you with the learning and development opportunities made available by TESDA during CY 2026?'))
        ->toBe('3 — Satisfied')
        ->and($cell('Q13 Would you recommend LinkedIn Learning to other TESDA personnel?'))->toBe('');
});
