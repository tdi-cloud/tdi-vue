<?php

namespace App\Support\PublicForms;

use App\Models\PublicForm;
use App\Models\PublicFormResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The Dashboard tab of a public form: a summary of all its responses, built
 * from the form's question definition so it works for any form an admin
 * builds in the question editor.
 *
 * Responses are read in chunks, so a big form is never loaded into memory at
 * once. A question's percentages use the people who answered that question
 * as the base (not all responses), because some sections are only for higher
 * salary grades and optional questions can be left blank.
 *
 * @phpstan-type Filters array{region: string|null, office: string|null, from: string|null, to: string|null}
 * @phpstan-type CountRow array{label: string, count: int, percent: float}
 * @phpstan-type Takeaway array{text: string, emphasis: list<string>}
 * @phpstan-type OptionRow array{value: string, label: string, score: int|null, exclusive: bool, is_other: bool, removed: bool, count: int, percent: float, rank_counts: list<int>, first_count: int, weighted_score: int}
 * @phpstan-type QuestionSummary array{
 *     key: string,
 *     number: int|null,
 *     section: string,
 *     type: string,
 *     question: string,
 *     instruction: string|null,
 *     removed: bool,
 *     answered: int,
 *     total: int,
 *     takeaway: Takeaway,
 *     options: list<OptionRow>,
 *     exclusive_options: list<OptionRow>,
 *     scale: array{average: float|null, min: int, max: int, reading: string|null, scored_count: int}|null,
 *     rank: int|null,
 *     max: int|null,
 *     latest_answers: list<array{text: string, office: string|null, region: string|null, submitted_at: string|null}>,
 *     other: array{label: string, count: int, answers: list<array{text: string, count: int}>}|null
 * }
 */
class PublicFormDashboard
{
    /** Salary grade bands for the "Salary grade" chart: label => [lowest, highest]. */
    private const SALARY_GRADE_BANDS = [
        'SG 1–9' => [1, 9],
        'SG 10–17' => [10, 17],
        'SG 18–23' => [18, 23],
        'SG 24+' => [24, PHP_INT_MAX],
    ];

    private const NOT_STATED = 'Not stated';

    private const TOP_OFFICES = 10;

    /** A donut stays readable up to this many slices; smaller ones fold into "Others". */
    private const DONUT_SLICES = 6;

    private const LATEST_TEXT_ANSWERS = 10;

    private const TOP_OTHER_ANSWERS = 10;

    private const CLOSING_SOON_DAYS = 7;

    private const CHUNK_SIZE = 500;

    public function __construct(private readonly PublicForm $form) {}

    /**
     * Everything the Dashboard tab shows, for the responses that match the filters.
     *
     * @param  array{region?: string|null, office?: string|null, from?: string|null, to?: string|null}  $filters  dates as Y-m-d
     * @return array{
     *     generated_at: string,
     *     filters: Filters,
     *     filter_options: array{regions: list<string>, offices: list<string>},
     *     status: array{state: string, label: string, detail: string, closes_at: string|null, days_left: int|null},
     *     overview: array{total: int, all_responses: int, today: int, last_7_days: int, previous_7_days: int, first_response_at: string|null, latest_response_at: string|null, latest_response_ago: string|null, regions: int, offices: int, emails_pending: int},
     *     timeline: list<array{date: string, label: string, count: int, total: int}>,
     *     profile: array{regions: list<CountRow>, offices: list<CountRow>, sex: list<CountRow>, plantilla_status: list<CountRow>, salary_grade: list<CountRow>},
     *     sections: list<array{key: string, title: string, audience: string|null, questions: list<QuestionSummary>}>,
     *     removed_questions: list<QuestionSummary>
     * }
     */
    public function summary(array $filters = []): array
    {
        $filters = [
            'region' => $filters['region'] ?? null,
            'office' => $filters['office'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ];

        $definition = $this->form->definition();
        $questions = $definition->questions(includeRemoved: true);
        $tallies = array_fill_keys(array_column($questions, 'key'), null);

        foreach ($questions as $question) {
            $tallies[$question['key']] = ['answered' => 0, 'choices' => [], 'ranks' => [], 'texts' => [], 'other_count' => 0, 'other_texts' => [], 'score_sum' => 0, 'score_count' => 0];
        }

        $scores = $this->scoresByValue($questions);
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $todayStart->copy()->subDays(6);
        $previousWeekStart = $weekStart->copy()->subDays(7);

        $total = $today = $lastWeek = $previousWeek = $emailsPending = 0;
        $firstAt = $latestAt = null;
        $perDay = [];
        $profile = ['regions' => [], 'offices' => [], 'sex' => [], 'plantilla_status' => [], 'salary_grade' => array_fill_keys([...array_keys(self::SALARY_GRADE_BANDS), self::NOT_STATED], 0)];

        $this->responsesQuery($filters)
            ->select(['id', 'region', 'office_division', 'employee', 'answers', 'other_answers', 'confirmation_sent_at', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy(self::CHUNK_SIZE)
            ->each(function (PublicFormResponse $response) use (
                $questions, $scores, $todayStart, $weekStart, $previousWeekStart,
                &$tallies, &$total, &$today, &$lastWeek, &$previousWeek, &$emailsPending, &$firstAt, &$latestAt, &$perDay, &$profile,
            ): void {
                $total++;
                $createdAt = $response->created_at;

                if ($createdAt !== null) {
                    $day = $createdAt->toDateString();
                    $perDay[$day] = ($perDay[$day] ?? 0) + 1;
                    $firstAt = $firstAt === null || $createdAt->lt($firstAt) ? $createdAt : $firstAt;
                    $latestAt = $latestAt === null || $createdAt->gt($latestAt) ? $createdAt : $latestAt;

                    if ($createdAt->gte($todayStart)) {
                        $today++;
                    }

                    if ($createdAt->gte($weekStart)) {
                        $lastWeek++;
                    } elseif ($createdAt->gte($previousWeekStart)) {
                        $previousWeek++;
                    }
                }

                if ($response->confirmation_sent_at === null) {
                    $emailsPending++;
                }

                $employee = $response->employee ?? [];
                $region = $this->label($response->region ?: ($employee['region'] ?? null));
                $office = $this->label($response->office_division ?: ($employee['office_division'] ?? null));

                $profile['regions'][$region] = ($profile['regions'][$region] ?? 0) + 1;
                $profile['offices'][$office] = ($profile['offices'][$office] ?? 0) + 1;
                $sex = $this->sexLabel($employee['sex'] ?? null);
                $profile['sex'][$sex] = ($profile['sex'][$sex] ?? 0) + 1;
                $status = $this->label($employee['plantilla_status'] ?? null);
                $profile['plantilla_status'][$status] = ($profile['plantilla_status'][$status] ?? 0) + 1;
                $profile['salary_grade'][$this->salaryGradeBand($employee['sg'] ?? null)]++;

                $place = [
                    'office' => $office === self::NOT_STATED ? null : $office,
                    'region' => $region === self::NOT_STATED ? null : $region,
                    'submitted_at' => $createdAt?->format('M j, Y g:i A'),
                ];

                foreach ($questions as $question) {
                    $this->tally($tallies[$question['key']], $question, $scores[$question['key']], $response->answers ?? [], $response->other_answers ?? [], $place);
                }
            });

        $summaries = [];
        $number = 0;

        foreach ($questions as $question) {
            $summaries[] = $this->questionSummary($question, $tallies[$question['key']], $total, $question['removed'] ? null : ++$number);
        }

        return [
            'generated_at' => $now->format('F j, Y g:i A'),
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'status' => $this->status(),
            'overview' => [
                'total' => $total,
                'all_responses' => PublicFormResponse::where('form_slug', $this->form->slug)->count(),
                'today' => $today,
                'last_7_days' => $lastWeek,
                'previous_7_days' => $previousWeek,
                'first_response_at' => $firstAt?->format('M j, Y g:i A'),
                'latest_response_at' => $latestAt?->format('M j, Y g:i A'),
                'latest_response_ago' => $latestAt?->diffForHumans(),
                'regions' => count(array_diff(array_keys($profile['regions']), [self::NOT_STATED])),
                'offices' => count(array_diff(array_keys($profile['offices']), [self::NOT_STATED])),
                'emails_pending' => $emailsPending,
            ],
            'timeline' => $this->timeline($perDay, $firstAt, $latestAt, $filters),
            'profile' => [
                'regions' => $this->countRows($this->sortByCount($profile['regions']), $total),
                'offices' => $this->countRows($this->foldTail($this->sortByCount($profile['offices']), self::TOP_OFFICES), $total),
                'sex' => $this->countRows($this->sexOrder($profile['sex']), $total),
                'plantilla_status' => $this->countRows($this->foldTail($this->alphabetical($profile['plantilla_status']), self::DONUT_SLICES - 1), $total),
                'salary_grade' => $this->countRows($profile['salary_grade'], $total),
            ],
            'sections' => $this->sections($definition, array_values(array_filter($summaries, fn (array $summary) => ! $summary['removed']))),
            'removed_questions' => array_values(array_filter($summaries, fn (array $summary) => $summary['removed'])),
        ];
    }

    /**
     * @param  Filters  $filters
     * @return Builder<PublicFormResponse>
     */
    private function responsesQuery(array $filters): Builder
    {
        return PublicFormResponse::query()
            ->where('form_slug', $this->form->slug)
            ->when($filters['region'], fn (Builder $query, string $region) => $query->where('region', $region))
            ->when($filters['office'], fn (Builder $query, string $office) => $query->where('office_division', $office))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * Adds one response's answer to a question's tally.
     *
     * @param  array<string, mixed>  $tally
     * @param  array<string, mixed>  $question
     * @param  array<string, int>  $scores
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherAnswers
     * @param  array{office: string|null, region: string|null, submitted_at: string|null}  $place
     */
    private function tally(array &$tally, array $question, array $scores, array $answers, array $otherAnswers, array $place): void
    {
        $values = array_values(array_filter(
            array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', (array) ($answers[$question['key']] ?? [])),
            fn (string $value) => $value !== '',
        ));

        if ($values === []) {
            return;
        }

        $tally['answered']++;

        switch ($question['type']) {
            case PublicFormDefinition::TYPE_TEXT:
                if (count($tally['texts']) < self::LATEST_TEXT_ANSWERS) {
                    $tally['texts'][] = ['text' => implode(' ', $values), ...$place];
                }

                return;

            case PublicFormDefinition::TYPE_RANKING:
                foreach ($values as $position => $value) {
                    $tally['ranks'][$value][$position] = ($tally['ranks'][$value][$position] ?? 0) + 1;
                }
                break;

            case PublicFormDefinition::TYPE_MULTIPLE:
                foreach (array_unique($values) as $value) {
                    $tally['choices'][$value] = ($tally['choices'][$value] ?? 0) + 1;
                }
                break;

            default:
                $choice = $values[0];
                $tally['choices'][$choice] = ($tally['choices'][$choice] ?? 0) + 1;

                if (isset($scores[$choice])) {
                    $tally['score_sum'] += $scores[$choice];
                    $tally['score_count']++;
                }
        }

        if (in_array(PublicFormDefinition::OTHER_VALUE, $values, true)) {
            $tally['other_count']++;
            $text = trim((string) ($otherAnswers[$question['key']] ?? ''));

            if ($text !== '') {
                $group = Str::lower(preg_replace('/\s+/u', ' ', $text));
                $tally['other_texts'][$group] ??= ['text' => $text, 'count' => 0];
                $tally['other_texts'][$group]['count']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $tally
     * @return QuestionSummary
     */
    private function questionSummary(array $question, array $tally, int $total, ?int $number): array
    {
        $answered = $tally['answered'];
        $summary = [
            'key' => $question['key'],
            'number' => $number,
            'section' => $question['section'],
            'type' => $question['type'],
            'question' => $question['question'],
            'instruction' => $question['instruction'],
            'removed' => $question['removed'],
            'answered' => $answered,
            'total' => $total,
            'takeaway' => $this->takeaway('No one has answered this question yet.'),
            'options' => [],
            'exclusive_options' => [],
            'scale' => null,
            'rank' => null,
            'max' => $question['max'],
            'latest_answers' => [],
            'other' => null,
        ];

        switch ($question['type']) {
            case PublicFormDefinition::TYPE_TEXT:
                $summary['latest_answers'] = $tally['texts'];

                if ($answered > 0) {
                    $summary['takeaway'] = $this->takeaway($answered === 1 ? '1 person wrote an answer.' : number_format($answered).' people wrote an answer.');
                }

                return $summary;

            case PublicFormDefinition::TYPE_RANKING:
                [$summary['rank'], $summary['options']] = $this->rankingRows($question, $tally['ranks'], $answered);
                break;

            case PublicFormDefinition::TYPE_MULTIPLE:
                $rows = $this->choiceRows($question, $tally['choices'], $answered);
                usort($rows, fn (array $a, array $b) => $b['count'] <=> $a['count']);
                $summary['options'] = array_values(array_filter($rows, fn (array $row) => ! $row['exclusive']));
                $summary['exclusive_options'] = array_values(array_filter($rows, fn (array $row) => $row['exclusive']));
                break;

            default:
                $summary['options'] = $this->choiceRows($question, $tally['choices'], $answered);
                $summary['scale'] = $this->scale($question, $tally);
        }

        if ($question['allow_other'] || $tally['other_count'] > 0) {
            $otherAnswers = array_values($tally['other_texts']);
            usort($otherAnswers, fn (array $a, array $b) => [$b['count'], Str::lower($a['text'])] <=> [$a['count'], Str::lower($b['text'])]);

            $summary['other'] = [
                'label' => $question['other_label'],
                'count' => $tally['other_count'],
                'answers' => array_slice($otherAnswers, 0, self::TOP_OTHER_ANSWERS),
            ];
        }

        if ($answered > 0) {
            $summary['takeaway'] = match ($question['type']) {
                PublicFormDefinition::TYPE_RANKING => $this->rankingTakeaway($summary['options']),
                PublicFormDefinition::TYPE_MULTIPLE => $this->multipleTakeaway($summary['options'], $summary['exclusive_options']),
                default => $this->singleTakeaway($summary['options'], $summary['scale']),
            };
        }

        return $summary;
    }

    /**
     * One row per choice in the form's order, then choices removed since
     * that still have answers, then "Other".
     *
     * @param  array<string, mixed>  $question
     * @param  array<array-key, int>  $counts
     * @return list<OptionRow>
     */
    private function choiceRows(array $question, array $counts, int $answered): array
    {
        return array_map(
            fn (array $choice) => $this->optionRow($choice, $counts[$choice['value']] ?? 0, $answered),
            $this->choices($question, array_map('strval', array_keys($counts))),
        );
    }

    /**
     * Each choice's count at every rank, how often it was ranked #1, and a
     * weighted score (rank 1 earns as many points as there are ranks, the
     * last rank earns 1), highest score first.
     *
     * @param  array<string, mixed>  $question
     * @param  array<array-key, array<int, int>>  $ranks
     * @return array{0: int, 1: list<OptionRow>}
     */
    private function rankingRows(array $question, array $ranks, int $answered): array
    {
        $rankCount = max(1, (int) $question['rank'], ...array_map(fn (array $positions) => max(array_keys($positions)) + 1, array_values($ranks) ?: [[0 => 0]]));
        $rows = [];

        foreach ($this->choices($question, array_map('strval', array_keys($ranks))) as $order => $choice) {
            $positions = $ranks[$choice['value']] ?? [];
            $rankCounts = array_map(fn (int $position) => $positions[$position] ?? 0, range(0, $rankCount - 1));
            $timesRanked = array_sum($rankCounts);
            $row = $this->optionRow($choice, $timesRanked, $answered);

            $row['rank_counts'] = $rankCounts;
            $row['first_count'] = $rankCounts[0];
            $row['weighted_score'] = array_sum(array_map(fn (int $count, int $position) => $count * ($rankCount - $position), $rankCounts, array_keys($rankCounts)));
            $rows[] = [$row, $order];
        }

        usort($rows, fn (array $a, array $b) => [$b[0]['weighted_score'], $b[0]['first_count'], $a[1]] <=> [$a[0]['weighted_score'], $a[0]['first_count'], $b[1]]);

        return [$rankCount, array_column($rows, 0)];
    }

    /**
     * The question's choices, plus removed choices that still have answers and "Other".
     *
     * @param  array<string, mixed>  $question
     * @param  list<string>  $answeredValues
     * @return list<array{value: string, label: string, score: int|null, exclusive: bool, is_other: bool, removed: bool}>
     */
    private function choices(array $question, array $answeredValues): array
    {
        $choices = array_map(fn (array $option) => [
            'value' => $option['value'],
            'label' => $option['label'],
            'score' => $option['score'],
            'exclusive' => $option['exclusive'],
            'is_other' => false,
            'removed' => false,
        ], $question['options']);

        $known = array_column($question['options'], 'value');

        foreach ($answeredValues as $value) {
            if ($value !== PublicFormDefinition::OTHER_VALUE && ! in_array($value, $known, true)) {
                $choices[] = ['value' => $value, 'label' => $value, 'score' => null, 'exclusive' => false, 'is_other' => false, 'removed' => true];
            }
        }

        if ($question['allow_other'] || in_array(PublicFormDefinition::OTHER_VALUE, $answeredValues, true)) {
            $choices[] = ['value' => PublicFormDefinition::OTHER_VALUE, 'label' => $question['other_label'], 'score' => null, 'exclusive' => false, 'is_other' => true, 'removed' => false];
        }

        return $choices;
    }

    /**
     * @param  array{value: string, label: string, score: int|null, exclusive: bool, is_other: bool, removed: bool}  $choice
     * @return OptionRow
     */
    private function optionRow(array $choice, int $count, int $base): array
    {
        return [...$choice, 'count' => $count, 'percent' => $this->percent($count, $base), 'rank_counts' => [], 'first_count' => 0, 'weighted_score' => 0];
    }

    /**
     * For a rating scale (choices with scores): the average score and the
     * choice nearest to it, in words.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $tally
     * @return array{average: float|null, min: int, max: int, reading: string|null, scored_count: int}|null
     */
    private function scale(array $question, array $tally): ?array
    {
        $scored = array_values(array_filter($question['options'], fn (array $option) => $option['score'] !== null));

        if ($scored === []) {
            return null;
        }

        usort($scored, fn (array $a, array $b) => $a['score'] <=> $b['score']);
        $average = $tally['score_count'] > 0 ? round($tally['score_sum'] / $tally['score_count'], 2) : null;
        $reading = null;

        if ($average !== null) {
            $closest = null;

            // Ascending with <=, so a tie (e.g. 2.5 between 2 and 3) reads as the higher choice.
            foreach ($scored as $option) {
                if ($closest === null || abs($option['score'] - $average) <= abs($closest['score'] - $average)) {
                    $closest = $option;
                }
            }

            $reading = $closest['label'];
        }

        return [
            'average' => $average,
            'min' => $scored[0]['score'],
            'max' => $scored[count($scored) - 1]['score'],
            'reading' => $reading,
            'scored_count' => $tally['score_count'],
        ];
    }

    /**
     * @param  list<OptionRow>  $rows
     * @param  array{average: float|null, min: int, max: int, reading: string|null, scored_count: int}|null  $scale
     * @return Takeaway
     */
    private function singleTakeaway(array $rows, ?array $scale): array
    {
        if ($scale !== null && $scale['average'] !== null && $scale['reading'] !== null) {
            return $this->takeaway(
                'The average score is '.number_format($scale['average'], 1)." on a {$scale['min']}–{$scale['max']} scale, closest to {$scale['reading']}.",
                [$scale['reading']],
            );
        }

        $leaders = $this->leaders($rows, 'count');

        if ($leaders === []) {
            return $this->takeaway('No one has answered this question yet.');
        }

        $percent = $this->wholePercent($leaders[0]['percent']);
        $labels = array_column($leaders, 'label');

        if (count($leaders) > 1) {
            return $this->takeaway($this->listOf($labels)." were chosen equally often ({$percent}% each).", $labels);
        }

        return $percent > 50
            ? $this->takeaway("Most respondents ({$percent}%) chose {$labels[0]}.", $labels)
            : $this->takeaway("{$labels[0]} was the most common answer ({$percent}%).", $labels);
    }

    /**
     * @param  list<OptionRow>  $rows
     * @param  list<OptionRow>  $exclusiveRows
     * @return Takeaway
     */
    private function multipleTakeaway(array $rows, array $exclusiveRows): array
    {
        $leaders = $this->leaders($rows, 'count') ?: $this->leaders($exclusiveRows, 'count');

        if ($leaders === []) {
            return $this->takeaway('No one has answered this question yet.');
        }

        $percent = $this->wholePercent($leaders[0]['percent']);
        $labels = array_column($leaders, 'label');

        return count($leaders) > 1
            ? $this->takeaway($this->listOf($labels)." were chosen most often, each by {$percent}% of respondents.", $labels)
            : $this->takeaway("{$labels[0]} was chosen most often, by {$percent}% of respondents.", $labels);
    }

    /**
     * @param  list<OptionRow>  $rows
     * @return Takeaway
     */
    private function rankingTakeaway(array $rows): array
    {
        $leaders = $this->leaders($rows, 'first_count');

        if ($leaders === []) {
            return $this->takeaway('No choice has been ranked #1 yet.');
        }

        $labels = array_column($leaders, 'label');
        $times = $leaders[0]['first_count'];
        $timesText = $times === 1 ? '1 time' : number_format($times).' times';
        $text = count($leaders) > 1
            ? $this->listOf($labels)." were ranked #1 most often ({$timesText} each)."
            : "{$labels[0]} was ranked #1 most often ({$timesText}).";

        // Rows are sorted by weighted score, so the first row leads overall.
        $topScore = $rows[0];

        if (! in_array($topScore['label'], $labels, true) && $topScore['weighted_score'] > 0) {
            $text .= " {$topScore['label']} has the highest overall score.";
            $labels[] = $topScore['label'];
        }

        return $this->takeaway($text, $labels);
    }

    /**
     * The rows sharing the highest value of $field (none when it's 0).
     *
     * @param  list<OptionRow>  $rows
     * @return list<OptionRow>
     */
    private function leaders(array $rows, string $field): array
    {
        $highest = max([0, ...array_column($rows, $field)]);

        return $highest === 0 ? [] : array_values(array_filter($rows, fn (array $row) => $row[$field] === $highest));
    }

    /**
     * @param  list<string>  $emphasis  parts of the text to show in bold
     * @return Takeaway
     */
    private function takeaway(string $text, array $emphasis = []): array
    {
        return ['text' => $text, 'emphasis' => array_values(array_unique($emphasis))];
    }

    /**
     * "A", "A and B", "A, B and C".
     *
     * @param  list<string>  $labels
     */
    private function listOf(array $labels): string
    {
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels)." and {$last}";
    }

    /**
     * The current questions grouped under their sections, in form order.
     *
     * @param  list<QuestionSummary>  $questions
     * @return list<array{key: string, title: string, audience: string|null, questions: list<QuestionSummary>}>
     */
    private function sections(PublicFormDefinition $definition, array $questions): array
    {
        $sections = [];

        foreach ($definition->sections() as $section) {
            $sectionQuestions = array_values(array_filter($questions, fn (array $question) => $question['section'] === $section['key']));

            if ($sectionQuestions !== []) {
                $sections[] = [
                    'key' => $section['key'],
                    'title' => $section['title'],
                    'audience' => $section['audience'],
                    'questions' => $sectionQuestions,
                ];
            }
        }

        return $sections;
    }

    /**
     * Responses per day from the first to the last response (or to today
     * while the form is open), with the running total.
     *
     * @param  array<string, int>  $perDay
     * @param  Filters  $filters
     * @return list<array{date: string, label: string, count: int, total: int}>
     */
    private function timeline(array $perDay, ?Carbon $firstAt, ?Carbon $latestAt, array $filters): array
    {
        if ($firstAt === null || $latestAt === null) {
            return [];
        }

        $end = $latestAt->copy()->startOfDay();
        $today = today();

        if ($this->form->closedReason() === null && $today->gt($end) && ($filters['to'] === null || Carbon::parse($filters['to'])->gte($today))) {
            $end = $today;
        }

        $rows = [];
        $runningTotal = 0;

        for ($day = $firstAt->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $count = $perDay[$day->toDateString()] ?? 0;
            $runningTotal += $count;
            $rows[] = ['date' => $day->toDateString(), 'label' => $day->format('M j'), 'count' => $count, 'total' => $runningTotal];
        }

        return $rows;
    }

    /**
     * Open, closing soon (within a week) or closed, in words.
     *
     * @return array{state: string, label: string, detail: string, closes_at: string|null, days_left: int|null}
     */
    private function status(): array
    {
        $closesAt = $this->form->closes_at;
        $closesAtLabel = $closesAt?->format('F j, Y g:i A');

        return match ($this->form->closedReason()) {
            PublicForm::CLOSED_NOT_OPEN => ['state' => 'closed', 'label' => 'Not open', 'detail' => 'Not taking responses right now.', 'closes_at' => $closesAtLabel, 'days_left' => null],
            PublicForm::CLOSED_DEADLINE => ['state' => 'closed', 'label' => 'Closed', 'detail' => "Closed on {$closesAtLabel}.", 'closes_at' => $closesAtLabel, 'days_left' => null],
            default => $closesAt === null
                ? ['state' => 'open', 'label' => 'Open', 'detail' => 'Taking responses, with no closing date.', 'closes_at' => null, 'days_left' => null]
                : $this->openUntil($closesAt),
        };
    }

    /**
     * @return array{state: string, label: string, detail: string, closes_at: string, days_left: int}
     */
    private function openUntil(Carbon $closesAt): array
    {
        $daysLeft = (int) today()->diffInDays($closesAt->copy()->startOfDay());
        $closingSoon = $daysLeft <= self::CLOSING_SOON_DAYS;
        $when = match ($daysLeft) {
            0 => 'Closes today',
            1 => 'Closes tomorrow',
            default => "Closes in {$daysLeft} days",
        };

        return [
            'state' => $closingSoon ? 'closing_soon' : 'open',
            'label' => $closingSoon ? 'Closing soon' : 'Open',
            'detail' => "{$when}, on {$closesAt->format('F j, Y g:i A')}.",
            'closes_at' => $closesAt->format('F j, Y g:i A'),
            'days_left' => $daysLeft,
        ];
    }

    /**
     * The regions and offices that have responses, for the filter lists.
     *
     * @return array{regions: list<string>, offices: list<string>}
     */
    private function filterOptions(): array
    {
        $distinct = fn (string $column) => PublicFormResponse::where('form_slug', $this->form->slug)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        return ['regions' => $distinct('region'), 'offices' => $distinct('office_division')];
    }

    /**
     * Each question's choice scores by value (rating scales only).
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, array<string, int>>
     */
    private function scoresByValue(array $questions): array
    {
        $scores = [];

        foreach ($questions as $question) {
            $scores[$question['key']] = [];

            foreach ($question['options'] as $option) {
                if ($option['score'] !== null) {
                    $scores[$question['key']][$option['value']] = $option['score'];
                }
            }
        }

        return $scores;
    }

    private function label(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? self::NOT_STATED : $value;
    }

    private function sexLabel(mixed $value): string
    {
        $value = $this->label($value);

        return match (Str::upper($value)) {
            'F', 'FEMALE' => 'Female',
            'M', 'MALE' => 'Male',
            default => $value,
        };
    }

    private function salaryGradeBand(mixed $value): string
    {
        $grade = PublicFormDefinition::salaryGrade(is_scalar($value) ? (string) $value : null);

        foreach (self::SALARY_GRADE_BANDS as $label => [$lowest, $highest]) {
            if ($grade !== null && $grade >= $lowest && $grade <= $highest) {
                return $label;
            }
        }

        return self::NOT_STATED;
    }

    /**
     * Female and Male always come first (even at 0), so their colors don't
     * change when a filter is applied; "Not stated" comes last.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sexOrder(array $counts): array
    {
        $ordered = ['Female' => $counts['Female'] ?? 0, 'Male' => $counts['Male'] ?? 0];

        return $ordered + $this->alphabetical(array_diff_key($counts, $ordered));
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortByCount(array $counts): array
    {
        uksort($counts, fn ($a, $b) => [(string) $a === self::NOT_STATED, -$counts[$a], (string) $a] <=> [(string) $b === self::NOT_STATED, -$counts[$b], (string) $b]);

        return $counts;
    }

    /**
     * A to Z, with "Not stated" last.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function alphabetical(array $counts): array
    {
        uksort($counts, fn ($a, $b) => [(string) $a === self::NOT_STATED, Str::lower((string) $a)] <=> [(string) $b === self::NOT_STATED, Str::lower((string) $b)]);

        return $counts;
    }

    /**
     * The first $keep entries, with the rest added up as "Others".
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function foldTail(array $counts, int $keep): array
    {
        if (count($counts) <= $keep + 1) {
            return $counts;
        }

        $kept = array_slice($counts, 0, $keep, true);

        return $kept + ['Others' => array_sum(array_slice($counts, $keep, null, true))];
    }

    /**
     * @param  array<array-key, int>  $counts
     * @return list<CountRow>
     */
    private function countRows(array $counts, int $base): array
    {
        $rows = [];

        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => $count, 'percent' => $this->percent($count, $base)];
        }

        return $rows;
    }

    private function percent(int $count, int $base): float
    {
        return $base > 0 ? round($count / $base * 100, 1) : 0.0;
    }

    private function wholePercent(float $percent): int
    {
        return (int) round($percent);
    }
}
