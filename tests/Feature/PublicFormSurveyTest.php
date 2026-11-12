<?php

use App\Http\Controllers\PublicFormController;
use App\Mail\PublicFormResponseReceivedMail;
use App\Models\Employee;
use App\Models\PublicForm;
use App\Models\PublicFormResponse;
use App\Models\PublicFormSection;
use App\Models\User;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Support\Facades\Mail;

/** The Learning and Development Survey, added by a migration. */
const LD_SLUG = 'ld-survey-2026';

function ldForm(): PublicForm
{
    return PublicForm::where('slug', LD_SLUG)->firstOrFail();
}

/**
 * The key the pages use for one of the survey's sections (its id).
 */
function ldSectionKey(string $title): string
{
    return (string) PublicFormSection::where('public_form_id', ldForm()->id)->where('title', $title)->value('id');
}

function ldEmployee(array $overrides = []): Employee
{
    return Employee::forceCreate(array_merge([
        'EMPCODE' => 'EMP-LDS-0001',
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

/**
 * Answers to every question everyone is asked (SG below 18).
 *
 * @return array<string, mixed>
 */
function ldAnswers(array $overrides = []): array
{
    return array_merge([
        'priority_areas' => ['leadership', 'digital_data_research', PublicFormDefinition::OTHER_VALUE],
        'learning_approaches' => ['face_to_face', 'blended'],
        'motivation' => 'career_growth',
        'satisfaction' => '3',
        'barriers' => ['workload', 'schedule_conflict'],
        'supervisor_support' => '4',
        'improvement_area' => 'schedule_duration',
        'suggestion' => '  More regional sessions.  ',
        'overall_rating' => '3',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function ldLinkedInAnswers(array $overrides = []): array
{
    return array_merge([
        'linkedin_appreciation' => ['flexibility', 'certificates'],
        'linkedin_difficulties' => ['limited_time'],
        'linkedin_rating' => '4',
        'linkedin_recommend' => 'yes',
    ], $overrides);
}

function ldPayload(array $overrides = []): array
{
    return array_merge([
        'empcode' => 'EMP-LDS-0001',
        'email' => 'Juan.DelaCruz@Example.com',
        'data_privacy_consent' => true,
        'answers' => ldAnswers(),
        'answers_other' => ['priority_areas' => 'Public speaking'],
        'website' => '',
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();
});

test('the survey is moved into the database with its sections, questions and reference prefix', function () {
    $form = ldForm();
    $definition = $form->definition();

    expect($form->title)->toBe('Learning and Development Survey')
        ->and($form->kicker)->toBe('CY 2026 · TESDA Development Institute')
        ->and($form->referencePrefix())->toBe('LDS')
        ->and(array_column($definition->sections(), 'title'))->toBe(['Learning Priorities', 'Your CY 2026 Experience', 'LinkedIn Learning'])
        ->and(array_column($definition->sections(), 'audience'))->toBe([null, null, 'For salary grade 18 and above'])
        ->and(count($definition->questions()))->toBe(13)
        ->and($definition->questions()[0]['key'])->toBe('priority_areas')
        ->and($definition->questions()[0]['rank'])->toBe(3);
});

test('the forms list shows only the forms taking responses', function () {
    PublicForm::factory()->withQuestions()->create(['title' => 'Open Survey']);
    PublicForm::factory()->withQuestions()->closed()->create(['title' => 'Draft Survey']);
    PublicForm::factory()->withQuestions()->pastDeadline()->create(['title' => 'Old Survey']);

    $this->get(route('public-forms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Index')
            ->has('forms', 2)
            ->where('forms.0.slug', LD_SLUG)
            ->where('forms.0.title', 'Learning and Development Survey')
            ->where('forms.0.estimated_minutes', 5)
            ->where('forms.1.title', 'Open Survey')
        );
});

test('the survey opens without logging in and lists every section and question', function () {
    $this->get(route('public-forms.show', LD_SLUG))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Form')
            ->where('form.slug', LD_SLUG)
            ->has('sections', 3)
            ->where('sections.2.key', ldSectionKey('LinkedIn Learning'))
            ->where('sections.2.step_label', 'LinkedIn')
            ->where('sections.2.audience', 'For salary grade 18 and above')
            ->has('questions', 13)
            ->where('questions.0.key', 'priority_areas')
            ->where('questions.0.type', 'ranking')
            ->where('questions.0.rank', 3)
            ->has('questions.0.options', 9)
            ->where('questions.0.question_fil', 'Sa mga sumusunod na larangan ng pagkatuto at pagpapaunlad, alin ang nais ninyong bigyang-prayoridad ng TDI para sa inyong propesyonal na pag-unlad sa 2027?')
            ->where('otherValue', PublicFormDefinition::OTHER_VALUE)
            ->where('preview', null)
        );
});

test('an unknown form returns 404', function () {
    $this->get(route('public-forms.show', 'does-not-exist'))->assertNotFound();
    $this->getJson(route('public-forms.search', ['slug' => 'does-not-exist', 'q' => 'Juan']))->assertNotFound();
    $this->post(route('public-forms.submit', 'does-not-exist'), ldPayload())->assertNotFound();
});

test('a closed or past-deadline survey shows the closed page and saves nothing', function (array $settings, string $reason) {
    ldForm()->update($settings);
    ldEmployee();

    $this->get(route('public-forms.show', LD_SLUG))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Closed')
            ->where('form.closed_reason', $reason)
        );

    $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'Dela Cruz']))->assertNotFound();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())->assertRedirect(route('public-forms.show', LD_SLUG));

    expect(PublicFormResponse::count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'closed' => [['is_open' => false], PublicForm::CLOSED_NOT_OPEN],
    'past deadline' => [fn () => ['closes_at' => now()->subDay()], PublicForm::CLOSED_DEADLINE],
]);

test('admins can preview a closed form, but even then it takes no answers', function () {
    ldForm()->update(['is_open' => false]);
    ldEmployee();
    $admin = User::factory()->create(['access' => 'admin']);

    // Without ?preview=1, or not an admin: the closed page.
    $this->actingAs($admin)->get(route('public-forms.show', LD_SLUG))->assertInertia(fn ($page) => $page->component('PublicForms/Closed'));
    $this->actingAs(User::factory()->create(['access' => 'user']))
        ->get(route('public-forms.show', ['slug' => LD_SLUG, 'preview' => 1]))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Closed'));

    $this->actingAs($admin)
        ->get(route('public-forms.show', ['slug' => LD_SLUG, 'preview' => 1]))
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Form')
            ->where('preview.closedReason', PublicForm::CLOSED_NOT_OPEN)
            ->has('questions', 13)
        );

    $this->actingAs($admin)
        ->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'Dela Cruz', 'preview' => 1]))
        ->assertOk()
        ->assertJsonPath('0.empcode', 'EMP-LDS-0001');

    $this->actingAs($admin)->post(route('public-forms.submit', LD_SLUG), ldPayload())->assertRedirect(route('public-forms.show', LD_SLUG));

    expect(PublicFormResponse::count())->toBe(0);
});

test('searching needs three letters and returns only what is needed to pick a name', function () {
    ldEmployee();

    $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'De']))->assertOk()->assertExactJson([]);

    $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'juan dela']))
        ->assertOk()
        ->assertExactJson([[
            'empcode' => 'EMP-LDS-0001',
            'name' => 'Juan P. Dela Cruz',
            'position' => 'Training Specialist II',
            'office_division' => 'Training Division',
            'sections' => [ldSectionKey('Learning Priorities'), ldSectionKey('Your CY 2026 Experience')],
            'already_answered' => false,
        ]]);
});

test('search results include the LinkedIn Learning section only for salary grade 18 and above', function (string $salaryGrade, bool $asked) {
    ldEmployee(['SG' => $salaryGrade]);

    $sections = $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'Dela Cruz']))->json('0.sections');

    expect(in_array(ldSectionKey('LinkedIn Learning'), $sections, true))->toBe($asked);
})->with([
    'SG 17' => ['17', false],
    'SG 18' => ['18', true],
    'SG 24' => ['24', true],
    'blank SG (job order)' => ['', false],
]);

test('search results flag employees who already answered', function () {
    ldEmployee();
    PublicFormResponse::factory()->create(['empcode' => 'EMP-LDS-0001']);

    $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'Dela Cruz']))
        ->assertOk()
        ->assertJsonPath('0.already_answered', true);
});

test('submitting saves the answers by EMPCODE with a copy of the employee details and emails a confirmation', function () {
    ldEmployee();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())
        ->assertRedirect(route('public-forms.success', LD_SLUG))
        ->assertSessionHas(PublicFormController::RECEIPT_SESSION_KEY, fn (array $receipt) => $receipt['email'] === 'juan.delacruz@example.com'
            && $receipt['email_sent'] === true
            && str_starts_with($receipt['reference'], 'LDS-'));

    $response = PublicFormResponse::sole();

    expect($response->form_slug)->toBe(LD_SLUG)
        ->and($response->empcode)->toBe('EMP-LDS-0001')
        ->and($response->full_name)->toBe('Juan P. Dela Cruz')
        ->and($response->email)->toBe('juan.delacruz@example.com')
        ->and($response->region)->toBe('NCR')
        ->and($response->employee['position'])->toBe('Training Specialist II')
        ->and($response->employee['sg'])->toBe('15')
        ->and($response->answers['priority_areas'])->toBe(['leadership', 'digital_data_research', PublicFormDefinition::OTHER_VALUE])
        ->and($response->answers['suggestion'])->toBe('More regional sessions.')
        ->and($response->answers)->not->toHaveKey('linkedin_rating')
        ->and($response->other_answers)->toBe(['priority_areas' => 'Public speaking'])
        ->and($response->confirmation_sent_at)->not->toBeNull()
        ->and($response->reference)->toBe(sprintf('LDS-%06d', $response->id));

    Mail::assertSent(PublicFormResponseReceivedMail::class, function (PublicFormResponseReceivedMail $mail) use ($response) {
        return $mail->hasTo('juan.delacruz@example.com') && $mail->response->is($response);
    });
});

test('the confirmation email has the reference number and a copy of the answers', function () {
    ldEmployee();
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload());

    $response = PublicFormResponse::sole();
    $mail = new PublicFormResponseReceivedMail($response, ldForm()->definition());

    $mail->assertHasSubject('Response received: Learning and Development Survey');
    $mail->assertSeeInHtml($response->reference);
    $mail->assertSeeInHtml('Juan P. Dela Cruz');
    $mail->assertSeeInHtml('1. Leadership, change management, coaching, and mentoring');
    $mail->assertSeeInHtml('3. Other: Public speaking');
    $mail->assertSeeInHtml('3 — Satisfied');
});

test('the confirmation email uses the title people saw on the form', function () {
    ldEmployee();
    ldForm()->update(['title' => 'TDI Learning Needs Survey 2027']);

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())->assertSessionHasNoErrors();

    Mail::assertSent(PublicFormResponseReceivedMail::class, fn (PublicFormResponseReceivedMail $mail) => $mail->formTitle() === 'TDI Learning Needs Survey 2027');

    $mail = new PublicFormResponseReceivedMail(PublicFormResponse::sole(), ldForm()->definition());
    $mail->assertHasSubject('Response received: TDI Learning Needs Survey 2027');
    $mail->assertSeeInHtml('TDI Learning Needs Survey 2027');
});

test('SG 18 and above must answer the LinkedIn Learning questions', function () {
    ldEmployee(['SG' => '19']);

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())
        ->assertSessionHasErrors(['answers.linkedin_appreciation', 'answers.linkedin_difficulties', 'answers.linkedin_rating', 'answers.linkedin_recommend']);

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['answers' => ldAnswers() + ldLinkedInAnswers()]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('public-forms.success', LD_SLUG));

    expect(PublicFormResponse::sole()->answers['linkedin_recommend'])->toBe('yes');
});

test('LinkedIn Learning answers from employees below SG 18 are ignored', function () {
    ldEmployee(['SG' => '12']);

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['answers' => ldAnswers() + ldLinkedInAnswers()]))
        ->assertSessionHasNoErrors();

    expect(PublicFormResponse::sole()->answers)->not->toHaveKey('linkedin_appreciation');
});

test('answers that break the question rules are rejected', function (array $answers, array $other, string $errorKey) {
    ldEmployee();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['answers' => ldAnswers($answers), 'answers_other' => $other]))
        ->assertSessionHasErrors($errorKey);

    expect(PublicFormResponse::count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'ranking fewer than three' => [['priority_areas' => ['leadership', 'ethics']], [], 'answers.priority_areas'],
    'ranking the same area twice' => [['priority_areas' => ['leadership', 'leadership', 'ethics']], [], 'answers.priority_areas.0'],
    'ranking an unknown area' => [['priority_areas' => ['leadership', 'ethics', 'cooking']], [], 'answers.priority_areas.2'],
    'more than two approaches' => [['learning_approaches' => ['face_to_face', 'blended', 'coaching_mentoring']], [], 'answers.learning_approaches'],
    '"no barriers" with another barrier' => [['barriers' => ['none', 'workload']], [], 'answers.barriers'],
    'a required question left blank' => [['satisfaction' => null], [], 'answers.satisfaction'],
    'an answer that is not a choice' => [['overall_rating' => '5'], [], 'answers.overall_rating'],
    '"Other" picked without typing it' => [['motivation' => PublicFormDefinition::OTHER_VALUE], ['priority_areas' => 'Public speaking'], 'answers_other.motivation'],
]);

test('the email address and the data privacy consent are required', function () {
    ldEmployee();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['email' => 'not-an-email', 'data_privacy_consent' => false]))
        ->assertSessionHasErrors(['email', 'data_privacy_consent']);

    expect(PublicFormResponse::count())->toBe(0);
});

test('someone not in the employee list cannot submit', function () {
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['empcode' => 'NOT-LISTED']))
        ->assertSessionHasErrors('empcode');

    expect(PublicFormResponse::count())->toBe(0);
});

test('each employee can answer only once', function () {
    ldEmployee();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())->assertSessionHasNoErrors();
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['empcode' => 'emp-lds-0001', 'email' => 'another@example.com']))
        ->assertSessionHasErrors(['empcode' => 'Juan P. Dela Cruz has already answered this form. Each employee can answer it only once.']);

    expect(PublicFormResponse::count())->toBe(1);
    Mail::assertSentCount(1);
});

test('a filled honeypot pretends to succeed and saves nothing', function () {
    ldEmployee();

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('public-forms.success', LD_SLUG));

    expect(PublicFormResponse::count())->toBe(0);
    Mail::assertNothingSent();
});

test('a failed confirmation email still keeps the response and says the email did not go out', function () {
    ldEmployee();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())
        ->assertRedirect(route('public-forms.success', LD_SLUG))
        ->assertSessionHas(PublicFormController::RECEIPT_SESSION_KEY, fn (array $receipt) => $receipt['email_sent'] === false);

    expect(PublicFormResponse::sole()->confirmation_sent_at)->toBeNull();
});

test('the success page shows the receipt once', function () {
    ldEmployee();
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload());
    $response = PublicFormResponse::sole();

    $this->get(route('public-forms.success', LD_SLUG))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Success')
            ->where('form.title', 'Learning and Development Survey')
            ->where('receipt', [
                'reference' => $response->reference,
                'name' => 'Juan P. Dela Cruz',
                'email' => 'juan.delacruz@example.com',
                'email_sent' => true,
            ])
        );

    $this->get(route('public-forms.success', LD_SLUG))
        ->assertInertia(fn ($page) => $page->where('receipt', null));
});

test('the closed and thank-you pages show the form\'s own background picture', function () {
    ldForm()->update(['is_open' => false, 'background_image' => 'public-form-backgrounds/survey.jpg']);
    $expected = ldForm()->customBackgroundUrl();

    $this->get(route('public-forms.show', LD_SLUG))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Closed')->where('backgroundUrl', $expected));

    $this->get(route('public-forms.success', LD_SLUG))
        ->assertInertia(fn ($page) => $page->component('PublicForms/Success')->where('backgroundUrl', $expected));
});

test('responses stay after HR re-imports the employees table', function () {
    ldEmployee();
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload());

    Employee::query()->delete();
    ldEmployee();

    expect(PublicFormResponse::sole()->empcode)->toBe('EMP-LDS-0001');

    $this->getJson(route('public-forms.search', ['slug' => LD_SLUG, 'q' => 'Dela Cruz']))
        ->assertJsonPath('0.already_answered', true);
});

test('a form made in the editor can be answered too', function () {
    $form = PublicForm::factory()->withQuestions()->create(['reference_prefix' => 'TST']);
    ldEmployee();

    $this->post(route('public-forms.submit', $form->slug), ldPayload(['answers' => ['colour' => 'gold'], 'answers_other' => []]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('public-forms.success', $form->slug));

    $response = PublicFormResponse::sole();
    expect($response->answers)->toBe(['colour' => 'gold', 'comments' => null])
        ->and($response->reference)->toBe(sprintf('TST-%06d', $response->id));

    // The same person can still answer another form.
    $this->post(route('public-forms.submit', LD_SLUG), ldPayload())->assertSessionHasNoErrors();
    expect(PublicFormResponse::count())->toBe(2);
});
