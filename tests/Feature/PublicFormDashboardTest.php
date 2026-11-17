<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormResponse;
use App\Models\PublicFormSection;
use App\Models\User;
use App\Support\PublicForms\PublicFormDashboard;
use App\Support\PublicForms\PublicFormDefinition;

function pfDashboardAdmin(string $access = 'admin'): User
{
    return User::factory()->create(['access' => $access]);
}

/**
 * A form with every question type: a section everyone answers (single,
 * rating scale, multiple, ranking, text) and one for salary grade 18 and up.
 */
function pfDashboardForm(): PublicForm
{
    $form = PublicForm::factory()->create(['title' => 'Learning Needs Survey']);
    $everyone = PublicFormSection::factory()->for($form, 'form')->create(['title' => 'Your learning', 'sort_order' => 0]);
    $senior = PublicFormSection::factory()->for($form, 'form')->create(['title' => 'For senior staff', 'min_salary_grade' => 18, 'sort_order' => 1]);

    $option = fn (string $value, string $label, ?int $score = null, bool $exclusive = false) => [
        'value' => $value, 'label' => $label, 'label_fil' => null, 'score' => $score, 'exclusive' => $exclusive,
    ];

    $questions = [
        [$everyone, 'format', PublicFormDefinition::TYPE_SINGLE, 'How do you prefer to learn?', [
            $option('online', 'Online self-paced learning'), $option('face', 'Face-to-face training'), $option('blended', 'Blended learning'),
        ], ['allow_other' => true]],
        [$everyone, 'rating', PublicFormDefinition::TYPE_SINGLE, 'How satisfied are you with past trainings?', [
            $option('1', 'Poor', 1), $option('2', 'Fair', 2), $option('3', 'Good', 3), $option('4', 'Excellent', 4),
        ], []],
        [$everyone, 'topics', PublicFormDefinition::TYPE_MULTIPLE, 'Which topics interest you?', [
            $option('leadership', 'Leadership'), $option('data', 'Data analysis'), $option('writing', 'Report writing'), $option('none', 'None of these', exclusive: true),
        ], []],
        [$everyone, 'priorities', PublicFormDefinition::TYPE_RANKING, 'Rank your top 3 priorities.', [
            $option('leadership', 'Leadership'), $option('data', 'Data analysis'), $option('writing', 'Report writing'), $option('ethics', 'Ethics'),
        ], ['allow_other' => true, 'rank_count' => 3]],
        [$everyone, 'comments', PublicFormDefinition::TYPE_TEXT, 'Anything else?', [], ['is_required' => false]],
        [$senior, 'coaching', PublicFormDefinition::TYPE_SINGLE, 'Would you coach a junior colleague?', [
            $option('yes', 'Yes'), $option('no', 'No'),
        ], []],
    ];

    foreach ($questions as $order => [$section, $key, $type, $text, $options, $extra]) {
        PublicFormQuestion::factory()->for($form, 'form')->for($section, 'section')->create(array_merge([
            'answer_key' => $key,
            'type' => $type,
            'question' => $text,
            'options' => $options ?: null,
            'sort_order' => $order,
        ], $extra));
    }

    return $form;
}

/**
 * @param  array<string, mixed>  $answers
 * @param  array<string, mixed>  $attributes
 */
function pfDashboardRespond(PublicForm $form, array $answers, array $attributes = []): PublicFormResponse
{
    return PublicFormResponse::factory()->create(array_merge([
        'form_slug' => $form->slug,
        'answers' => $answers,
        'other_answers' => [],
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $filters
 * @return array<string, mixed>
 */
function pfDashboardSummary(PublicForm $form, array $filters = []): array
{
    return (new PublicFormDashboard(PublicForm::findBySlugOrFail($form->slug)))->summary($filters);
}

/**
 * @param  array<string, mixed>  $summary
 * @return array<string, mixed>
 */
function pfDashboardQuestion(array $summary, string $key): array
{
    $questions = [...collect($summary['sections'])->flatMap(fn (array $section) => $section['questions'])->all(), ...$summary['removed_questions']];

    return collect($questions)->firstWhere('key', $key) ?? throw new RuntimeException("No question {$key}");
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, int>
 */
function pfDashboardCounts(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $row) => [$row['label'] => $row['count']])->all();
}

test('single-choice questions count each choice in the form order, with percentages of the people who answered', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online', 'rating' => '4']);
    pfDashboardRespond($form, ['format' => 'online', 'rating' => '4']);
    pfDashboardRespond($form, ['format' => 'online', 'rating' => '3']);
    pfDashboardRespond($form, ['format' => 'face', 'rating' => '2']);
    pfDashboardRespond($form, ['format' => '__other__', 'rating' => null], ['other_answers' => ['format' => 'Coaching']]);

    $summary = pfDashboardSummary($form);
    $format = pfDashboardQuestion($summary, 'format');

    expect($format['number'])->toBe(1)
        ->and($format['answered'])->toBe(5)
        ->and($format['total'])->toBe(5)
        ->and(array_column($format['options'], 'label'))->toBe(['Online self-paced learning', 'Face-to-face training', 'Blended learning', 'Other'])
        ->and(array_column($format['options'], 'count'))->toBe([3, 1, 0, 1])
        ->and(array_column($format['options'], 'percent'))->toBe([60.0, 20.0, 0.0, 20.0])
        ->and($format['options'][3]['is_other'])->toBeTrue()
        ->and($format['scale'])->toBeNull()
        ->and($format['takeaway'])->toBe(['text' => 'Most respondents (60%) chose Online self-paced learning.', 'emphasis' => ['Online self-paced learning']]);

    $rating = pfDashboardQuestion($summary, 'rating');

    expect($rating['answered'])->toBe(4)
        ->and(array_column($rating['options'], 'percent'))->toBe([0.0, 25.0, 25.0, 50.0])
        ->and($rating['scale'])->toBe(['average' => 3.25, 'min' => 1, 'max' => 4, 'reading' => 'Good', 'scored_count' => 4])
        ->and($rating['takeaway']['text'])->toBe('The average score is 3.3 on a 1–4 scale, closest to Good.');
});

test('multiple-choice percentages are of the people who answered, can add up past 100%, and keep exclusive choices apart', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['topics' => ['leadership', 'data']]);
    pfDashboardRespond($form, ['topics' => ['leadership']]);
    pfDashboardRespond($form, ['topics' => ['data', 'writing', 'leadership']]);
    pfDashboardRespond($form, ['topics' => ['none']]);
    pfDashboardRespond($form, ['topics' => null]);

    $topics = pfDashboardQuestion(pfDashboardSummary($form), 'topics');

    expect($topics['answered'])->toBe(4)
        ->and($topics['total'])->toBe(5)
        ->and(pfDashboardCounts($topics['options']))->toBe(['Leadership' => 3, 'Data analysis' => 2, 'Report writing' => 1])
        ->and(array_column($topics['options'], 'percent'))->toBe([75.0, 50.0, 25.0])
        ->and(pfDashboardCounts($topics['exclusive_options']))->toBe(['None of these' => 1])
        ->and($topics['exclusive_options'][0]['percent'])->toBe(25.0)
        ->and($topics['takeaway']['text'])->toBe('Leadership was chosen most often, by 75% of respondents.');
});

test('ranking questions count each rank, how often a choice was ranked #1, and a weighted score', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['priorities' => ['leadership', 'data', 'writing']]);
    pfDashboardRespond($form, ['priorities' => ['data', 'leadership', 'ethics']]);
    pfDashboardRespond($form, ['priorities' => ['data', 'writing', '__other__']], ['other_answers' => ['priorities' => 'Public speaking']]);
    pfDashboardRespond($form, ['priorities' => ['leadership', 'ethics', 'data']]);
    pfDashboardRespond($form, ['priorities' => ['data', 'ethics', 'leadership']]);

    $priorities = pfDashboardQuestion(pfDashboardSummary($form), 'priorities');
    $rows = collect($priorities['options'])->keyBy('value');

    expect($priorities['rank'])->toBe(3)
        ->and($priorities['answered'])->toBe(5)
        // Rank 1 earns 3 points, rank 2 earns 2 and rank 3 earns 1.
        ->and(array_column($priorities['options'], 'value'))->toBe(['data', 'leadership', 'ethics', 'writing', '__other__'])
        ->and(array_column($priorities['options'], 'weighted_score'))->toBe([12, 9, 5, 3, 1])
        ->and($rows['data']['rank_counts'])->toBe([3, 1, 1])
        ->and($rows['data']['first_count'])->toBe(3)
        ->and($rows['leadership']['rank_counts'])->toBe([2, 1, 1])
        ->and($rows['writing']['first_count'])->toBe(0)
        ->and($priorities['takeaway'])->toBe(['text' => 'Data analysis was ranked #1 most often (3 times).', 'emphasis' => ['Data analysis']])
        ->and($priorities['other']['count'])->toBe(1)
        ->and($priorities['other']['answers'])->toBe([['text' => 'Public speaking', 'count' => 1]]);
});

test('the takeaway names the overall leader of a ranking when it is not the one ranked #1 most often', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['priorities' => ['ethics', 'leadership', 'data']]);
    pfDashboardRespond($form, ['priorities' => ['ethics', 'leadership', 'data']]);
    pfDashboardRespond($form, ['priorities' => ['data', 'leadership', 'writing']]);
    pfDashboardRespond($form, ['priorities' => ['writing', 'leadership', 'data']]);

    $priorities = pfDashboardQuestion(pfDashboardSummary($form), 'priorities');

    expect($priorities['options'][0]['label'])->toBe('Leadership')
        ->and($priorities['takeaway']['text'])->toBe('Ethics was ranked #1 most often (2 times). Leadership has the highest overall score.')
        ->and($priorities['takeaway']['emphasis'])->toBe(['Ethics', 'Leadership']);
});

test('"Other" answers are grouped without regard to capitals or extra spaces', function () {
    $form = pfDashboardForm();

    foreach (['Coaching', 'coaching  ', 'COACHING', 'Mentoring', 'Peer   learning', 'peer learning'] as $text) {
        pfDashboardRespond($form, ['format' => '__other__'], ['other_answers' => ['format' => $text]]);
    }
    pfDashboardRespond($form, ['format' => 'online']);

    $other = pfDashboardQuestion(pfDashboardSummary($form), 'format')['other'];

    expect($other['label'])->toBe('Other')
        ->and($other['count'])->toBe(6)
        ->and(array_column($other['answers'], 'count'))->toBe([3, 2, 1])
        ->and(mb_strtolower($other['answers'][0]['text']))->toBe('coaching')
        ->and(mb_strtolower(preg_replace('/\s+/', ' ', $other['answers'][1]['text'])))->toBe('peer learning')
        ->and($other['answers'][2]['text'])->toBe('Mentoring');
});

test('a question in a salary-grade section uses the people who answered it as its base', function () {
    $form = pfDashboardForm();
    // Two SG 18+ employees answer the senior section; three below SG 18 skip it.
    pfDashboardRespond($form, ['format' => 'online', 'coaching' => 'yes']);
    pfDashboardRespond($form, ['format' => 'online', 'coaching' => 'no']);
    pfDashboardRespond($form, ['format' => 'face']);
    pfDashboardRespond($form, ['format' => 'face']);
    pfDashboardRespond($form, ['format' => 'blended']);

    $summary = pfDashboardSummary($form);
    $coaching = pfDashboardQuestion($summary, 'coaching');

    expect($coaching['answered'])->toBe(2)
        ->and($coaching['total'])->toBe(5)
        ->and(array_column($coaching['options'], 'percent'))->toBe([50.0, 50.0])
        ->and($coaching['takeaway']['text'])->toBe('Yes and No were chosen equally often (50% each).')
        ->and($summary['sections'][1]['title'])->toBe('For senior staff')
        ->and($summary['sections'][1]['audience'])->toBe('For salary grade 18 and above');
});

test('written answers show the count and the 10 latest answers with the office and region only', function () {
    $form = pfDashboardForm();

    foreach (range(1, 12) as $day) {
        pfDashboardRespond($form, ['comments' => "Comment {$day}"], [
            'full_name' => 'Hidden Person',
            'region' => 'Region VII',
            'office_division' => 'Admin Division',
            'created_at' => now()->subDays(13 - $day),
        ]);
    }
    pfDashboardRespond($form, ['comments' => null]);

    $comments = pfDashboardQuestion(pfDashboardSummary($form), 'comments');

    expect($comments['answered'])->toBe(12)
        ->and($comments['takeaway']['text'])->toBe('12 people wrote an answer.')
        ->and($comments['latest_answers'])->toHaveCount(10)
        ->and($comments['latest_answers'][0]['text'])->toBe('Comment 12')
        ->and($comments['latest_answers'][0]['office'])->toBe('Admin Division')
        ->and($comments['latest_answers'][0]['region'])->toBe('Region VII')
        ->and($comments['latest_answers'][0])->not->toHaveKey('name')
        ->and(json_encode($comments))->not->toContain('Hidden Person');
});

test('removed questions are listed last, flagged, and keep their old answers', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online', 'comments' => 'Keep it short']);

    PublicFormQuestion::where('public_form_id', $form->id)->where('answer_key', 'rating')->sole()->delete();

    $summary = pfDashboardSummary($form);
    $current = collect($summary['sections'])->flatMap(fn (array $section) => $section['questions']);

    expect($current->pluck('key')->all())->toBe(['format', 'topics', 'priorities', 'comments', 'coaching'])
        ->and($current->pluck('number')->all())->toBe([1, 2, 3, 4, 5])
        ->and($summary['removed_questions'])->toHaveCount(1)
        ->and($summary['removed_questions'][0]['key'])->toBe('rating')
        ->and($summary['removed_questions'][0]['removed'])->toBeTrue()
        ->and($summary['removed_questions'][0]['number'])->toBeNull();
});

test('the overview, the timeline and the respondent profile count every response', function () {
    $this->travelTo(now()->setDate(2026, 11, 14)->setTime(10, 0));
    $form = pfDashboardForm();
    $form->update(['closes_at' => now()->addDays(3)]);

    $employee = PublicFormResponse::factory()->raw()['employee'];
    pfDashboardRespond($form, ['format' => 'online'], ['created_at' => now()->subHour(), 'employee' => array_merge($employee, ['sex' => 'F', 'sg' => '8', 'plantilla_status' => 'Permanent'])]);
    pfDashboardRespond($form, ['format' => 'online'], ['created_at' => now()->subHours(2), 'confirmation_sent_at' => null, 'employee' => array_merge($employee, ['sex' => 'M', 'sg' => 'SG 18', 'plantilla_status' => 'Permanent'])]);
    pfDashboardRespond($form, ['format' => 'face'], ['created_at' => now()->subDays(3), 'region' => 'Region VII', 'office_division' => 'Admin Division', 'employee' => array_merge($employee, ['sex' => 'F', 'sg' => '24', 'plantilla_status' => 'Casual'])]);
    pfDashboardRespond($form, ['format' => 'face'], ['created_at' => now()->subDays(10), 'employee' => array_merge($employee, ['sex' => '', 'sg' => '', 'plantilla_status' => 'Contractual'])]);

    $summary = pfDashboardSummary($form);

    expect($summary['overview'])->toMatchArray([
        'total' => 4,
        'all_responses' => 4,
        'today' => 2,
        'last_7_days' => 3,
        'previous_7_days' => 1,
        'regions' => 2,
        'offices' => 2,
        'emails_pending' => 1,
        'first_response_at' => 'Nov 4, 2026 10:00 AM',
        'latest_response_at' => 'Nov 14, 2026 9:00 AM',
    ])
        ->and($summary['status'])->toMatchArray(['state' => 'closing_soon', 'label' => 'Closing soon', 'days_left' => 3])
        ->and($summary['status']['detail'])->toBe('Closes in 3 days, on November 17, 2026 10:00 AM.')
        // Every day from the first response to today, with the running total.
        ->and($summary['timeline'])->toHaveCount(11)
        ->and($summary['timeline'][0])->toBe(['date' => '2026-11-04', 'label' => 'Nov 4', 'count' => 1, 'total' => 1])
        ->and($summary['timeline'][7])->toBe(['date' => '2026-11-11', 'label' => 'Nov 11', 'count' => 1, 'total' => 2])
        ->and($summary['timeline'][10])->toBe(['date' => '2026-11-14', 'label' => 'Nov 14', 'count' => 2, 'total' => 4])
        ->and(pfDashboardCounts($summary['profile']['regions']))->toBe(['NCR' => 3, 'Region VII' => 1])
        ->and($summary['profile']['regions'][0]['percent'])->toBe(75.0)
        ->and(pfDashboardCounts($summary['profile']['sex']))->toBe(['Female' => 2, 'Male' => 1, 'Not stated' => 1])
        ->and(pfDashboardCounts($summary['profile']['plantilla_status']))->toBe(['Casual' => 1, 'Contractual' => 1, 'Permanent' => 2])
        ->and(pfDashboardCounts($summary['profile']['salary_grade']))->toBe(['SG 1–9' => 1, 'SG 10–17' => 0, 'SG 18–23' => 1, 'SG 24+' => 1, 'Not stated' => 1]);
});

test('offices past the top 10 are added up as "Others"', function () {
    $form = pfDashboardForm();

    foreach (range(1, 12) as $number) {
        pfDashboardRespond($form, ['format' => 'online'], ['office_division' => sprintf('Office %02d', $number)]);
    }
    pfDashboardRespond($form, ['format' => 'online'], ['office_division' => 'Office 01']);

    $offices = pfDashboardSummary($form)['profile']['offices'];

    expect($offices)->toHaveCount(11)
        ->and($offices[0])->toBe(['label' => 'Office 01', 'count' => 2, 'percent' => 15.4])
        ->and($offices[10])->toMatchArray(['label' => 'Others', 'count' => 2]);
});

test('the region, office and date filters change every number', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online'], ['region' => 'NCR', 'office_division' => 'Training Division']);
    pfDashboardRespond($form, ['format' => 'online'], ['region' => 'NCR', 'office_division' => 'Admin Division', 'created_at' => now()->subDays(20)]);
    pfDashboardRespond($form, ['format' => 'face'], ['region' => 'Region VII', 'office_division' => 'Admin Division', 'created_at' => now()->subDays(10)]);

    $byRegion = pfDashboardSummary($form, ['region' => 'Region VII']);

    expect($byRegion['overview']['total'])->toBe(1)
        ->and($byRegion['overview']['all_responses'])->toBe(3)
        ->and($byRegion['filters']['region'])->toBe('Region VII')
        ->and(pfDashboardQuestion($byRegion, 'format')['takeaway']['text'])->toBe('Most respondents (100%) chose Face-to-face training.')
        // The lists to filter by always show every region and office of the form.
        ->and($byRegion['filter_options'])->toBe(['regions' => ['NCR', 'Region VII'], 'offices' => ['Admin Division', 'Training Division']]);

    expect(pfDashboardSummary($form, ['office' => 'Admin Division'])['overview']['total'])->toBe(2)
        ->and(pfDashboardSummary($form, ['region' => 'NCR', 'office' => 'Admin Division'])['overview']['total'])->toBe(1)
        ->and(pfDashboardSummary($form, ['from' => now()->subDays(15)->toDateString()])['overview']['total'])->toBe(2)
        ->and(pfDashboardSummary($form, ['to' => now()->subDays(15)->toDateString()])['overview']['total'])->toBe(1)
        ->and(pfDashboardSummary($form, ['from' => now()->subDays(10)->toDateString(), 'to' => now()->subDays(10)->toDateString()])['overview']['total'])->toBe(1);
});

test('the dashboard filters come from the page link, and a malformed date is ignored', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online'], ['region' => 'NCR']);
    pfDashboardRespond($form, ['format' => 'face'], ['region' => 'Region VII', 'created_at' => now()->subDays(10)]);

    $this->actingAs(pfDashboardAdmin())
        ->get(route('public-forms.admin.show', ['slug' => $form->slug, 'region' => 'NCR', 'from' => 'not-a-date', 'to' => '2026-02-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('dashboard.overview.total', 1)
                ->where('dashboard.filters', ['region' => 'NCR', 'office' => null, 'from' => null, 'to' => null])
            )
        );

    $this->actingAs(pfDashboardAdmin())
        ->get(route('public-forms.admin.show', ['slug' => $form->slug, 'from' => now()->toDateString(), 'to' => now()->subDays(12)->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                // A range picked backwards still covers the days in between.
                ->where('dashboard.overview.total', 2)
                ->where('dashboard.filters.from', now()->subDays(12)->toDateString())
            )
        );
});

test('a form without responses gets an empty but complete dashboard', function () {
    $summary = pfDashboardSummary(pfDashboardForm());

    expect($summary['overview'])->toMatchArray([
        'total' => 0, 'all_responses' => 0, 'today' => 0, 'last_7_days' => 0, 'regions' => 0, 'offices' => 0, 'emails_pending' => 0,
        'first_response_at' => null, 'latest_response_at' => null,
    ])
        ->and($summary['timeline'])->toBe([])
        ->and($summary['profile']['regions'])->toBe([])
        ->and(pfDashboardCounts($summary['profile']['sex']))->toBe(['Female' => 0, 'Male' => 0])
        ->and($summary['profile']['salary_grade'])->toHaveCount(5)
        ->and($summary['sections'])->toHaveCount(2)
        ->and($summary['removed_questions'])->toBe([])
        ->and($summary['filter_options'])->toBe(['regions' => [], 'offices' => []])
        ->and($summary['status'])->toMatchArray(['state' => 'open', 'label' => 'Open']);

    $format = pfDashboardQuestion($summary, 'format');

    expect($format['answered'])->toBe(0)
        ->and(array_column($format['options'], 'percent'))->toBe([0.0, 0.0, 0.0, 0.0])
        ->and($format['other'])->toBe(['label' => 'Other', 'count' => 0, 'answers' => []])
        ->and($format['takeaway']['text'])->toBe('No one has answered this question yet.')
        ->and(pfDashboardQuestion($summary, 'rating')['scale'])->toMatchArray(['average' => null, 'reading' => null])
        ->and(pfDashboardQuestion($summary, 'priorities')['options'][0]['rank_counts'])->toBe([0, 0, 0]);
});

test('a closed form shows as closed on its dashboard', function () {
    $form = pfDashboardForm();

    $form->update(['is_open' => false]);
    expect(pfDashboardSummary($form)['status'])->toMatchArray(['state' => 'closed', 'label' => 'Not open']);

    $form->update(['is_open' => true, 'closes_at' => now()->subDay()]);
    expect(pfDashboardSummary($form)['status'])->toMatchArray(['state' => 'closed', 'label' => 'Closed']);
});

test('guests and regular users cannot see a form dashboard', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online']);

    $this->get(route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'dashboard']))->assertRedirect(route('login'));

    $this->actingAs(pfDashboardAdmin('user'))
        ->get(route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'dashboard', 'region' => 'NCR']))
        ->assertForbidden();

    // The deferred request that loads the dashboard is refused too.
    $this->actingAs(pfDashboardAdmin('user'))
        ->get(route('public-forms.admin.show', $form->slug), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Partial-Component' => 'PublicForms/Admin/Show',
            'X-Inertia-Partial-Data' => 'dashboard',
        ])
        ->assertForbidden();
});

test('a form with responses opens on its dashboard, which loads after the page', function () {
    $form = pfDashboardForm();
    pfDashboardRespond($form, ['format' => 'online']);

    $this->actingAs(pfDashboardAdmin())
        ->get(route('public-forms.admin.show', $form->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('PublicForms/Admin/Show')
            ->where('initialTab', 'dashboard')
            ->missing('dashboard')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('dashboard.overview.total', 1)
                ->where('dashboard.sections.0.questions.0.key', 'format')
            )
        );

    $this->actingAs(pfDashboardAdmin())
        ->get(route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'responses']))
        ->assertInertia(fn ($page) => $page->where('initialTab', 'responses'));

    $this->actingAs(pfDashboardAdmin())
        ->get(route('public-forms.admin.show', pfDashboardForm()->slug))
        ->assertInertia(fn ($page) => $page->where('initialTab', 'questions'));
});
