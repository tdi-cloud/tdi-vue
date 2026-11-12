<?php

namespace App\Support\PublicForms;

use App\Models\Employee;
use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormSection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * How a public form is answered: its sections and questions (built by admins
 * in the question editor) as plain arrays for the pages, the validation of
 * answers, and the answers in words for the email, the admin page and the
 * CSV export.
 *
 * Question shape:
 * - key: the question's answer_key, used in the request and the saved answers
 * - section: the id of its section (as a string)
 * - type: ranking (put `rank` choices in order), multiple (tick up to `max`;
 *   no limit when null), single or text
 * - question / question_fil: the question in English and in Filipino
 * - instruction / instruction_fil: optional
 * - options: list of {value, label, label_fil, score, exclusive}; an exclusive
 *   option (e.g. "No significant barriers") can't be combined with others
 * - allow_other: adds an "Other" choice with a text box
 * - required, rank, max, max_length, removed (a removed question kept for old answers)
 */
class PublicFormDefinition
{
    public const TYPE_RANKING = 'ranking';

    public const TYPE_MULTIPLE = 'multiple';

    public const TYPE_SINGLE = 'single';

    public const TYPE_TEXT = 'text';

    /** Sent in place of an option when "Other" is picked; the typed text goes in answers_other. */
    public const OTHER_VALUE = '__other__';

    public const TEXT_MAX_LENGTH = 2000;

    public const OTHER_MAX_LENGTH = 255;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $currentQuestions = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $removedQuestions = null;

    public function __construct(private readonly PublicForm $form) {}

    public static function for(PublicForm $form): self
    {
        $form->loadMissing('sections.questions');

        return new self($form);
    }

    public function form(): PublicForm
    {
        return $this->form;
    }

    /**
     * @return list<array{key: string, title: string, step_label: string, description: string|null, audience: string|null}>
     */
    public function sections(): array
    {
        return $this->form->sections->map(fn (PublicFormSection $section) => [
            'key' => (string) $section->id,
            'title' => $section->title,
            'step_label' => $section->stepLabel(),
            'description' => $section->description,
            'audience' => $section->audienceLabel(),
        ])->values()->all();
    }

    /**
     * Whether the employee answers this section: every section, except those
     * for a minimum salary grade (employees without one, such as job
     * orders, skip those).
     */
    public function sectionAppliesTo(string $sectionKey, Employee $employee): bool
    {
        $minimum = $this->form->sections->firstWhere('id', (int) $sectionKey)?->min_salary_grade;

        if ($minimum === null) {
            return true;
        }

        $salaryGrade = self::salaryGrade($employee->SG);

        return $salaryGrade !== null && $salaryGrade >= $minimum;
    }

    /**
     * The number in HR's SG column ("18", "SG 18", "18-2"), or null when blank.
     */
    public static function salaryGrade(?string $value): ?int
    {
        return preg_match('/\d+/', (string) $value, $matches) === 1 ? (int) $matches[0] : null;
    }

    /**
     * @return list<string>
     */
    public function sectionKeysFor(Employee $employee): array
    {
        return array_values(array_filter(
            array_column($this->sections(), 'key'),
            fn (string $key) => $this->sectionAppliesTo($key, $employee),
        ));
    }

    /**
     * The current questions in the order people see them (section by section).
     * With $includeRemoved, the removed ones follow, so old answers to them
     * still show in the responses and the CSV.
     *
     * @return list<array<string, mixed>>
     */
    public function questions(bool $includeRemoved = false): array
    {
        $this->currentQuestions ??= $this->form->sections
            ->flatMap(fn (PublicFormSection $section) => $section->questions)
            ->map(fn (PublicFormQuestion $question) => $this->toQuestionArray($question))
            ->values()
            ->all();

        if (! $includeRemoved) {
            return $this->currentQuestions;
        }

        $this->removedQuestions ??= PublicFormQuestion::onlyTrashed()
            ->where('public_form_id', $this->form->id)
            ->orderBy('deleted_at')
            ->get()
            ->map(fn (PublicFormQuestion $question) => $this->toQuestionArray($question))
            ->all();

        return [...$this->currentQuestions, ...$this->removedQuestions];
    }

    /**
     * The questions this employee is asked (their sections only).
     *
     * @return list<array<string, mixed>>
     */
    public function questionsFor(Employee $employee): array
    {
        $sectionKeys = $this->sectionKeysFor($employee);

        return array_values(array_filter(
            $this->questions(),
            fn (array $question) => in_array($question['section'], $sectionKeys, true),
        ));
    }

    /**
     * Validation for the answers of the given questions.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     */
    public function rules(array $questions, Request $request): array
    {
        $rules = [
            'answers' => ['nullable', 'array'],
            'answers_other' => ['nullable', 'array'],
        ];
        $messages = [];
        $attributes = [];

        foreach ($questions as $question) {
            $key = "answers.{$question['key']}";
            $presence = $question['required'] ? 'required' : 'nullable';
            $choices = array_column($question['options'], 'value');

            $attributes[$key] = 'answer';
            $messages["{$key}.required"] = 'Please answer this question.';
            $messages["{$key}.*.in"] = 'Please choose from the listed answers.';
            $messages["{$key}.*.distinct"] = 'Each answer can be chosen only once.';

            if ($question['allow_other']) {
                $choices[] = self::OTHER_VALUE;
                $otherKey = "answers_other.{$question['key']}";
                $pickedOther = in_array(self::OTHER_VALUE, (array) $request->input($key), true);

                $rules[$otherKey] = [Rule::requiredIf($pickedOther), 'nullable', 'string', 'max:'.self::OTHER_MAX_LENGTH];
                $messages["{$otherKey}.required"] = 'Please type your answer for "Other".';
                $attributes[$otherKey] = '"Other" answer';
            }

            switch ($question['type']) {
                case self::TYPE_RANKING:
                    $rules[$key] = [$presence, 'array', "size:{$question['rank']}"];
                    $rules["{$key}.*"] = ['string', 'distinct', Rule::in($choices)];
                    $messages["{$key}.size"] = "Please rank exactly {$question['rank']} choices.";
                    break;

                case self::TYPE_MULTIPLE:
                    $rules[$key] = array_values(array_filter([
                        $presence,
                        'array',
                        $question['max'] !== null ? "max:{$question['max']}" : null,
                        $this->exclusiveRule($question),
                    ]));
                    $rules["{$key}.*"] = ['string', 'distinct', Rule::in($choices)];
                    $messages["{$key}.max"] = "Please choose up to {$question['max']} answers.";
                    break;

                case self::TYPE_SINGLE:
                    $rules[$key] = [$presence, 'string', Rule::in($choices)];
                    $messages["{$key}.in"] = 'Please choose from the listed answers.';
                    break;

                default:
                    $rules[$key] = [$presence, 'string', "max:{$question['max_length']}"];
            }
        }

        return [$rules, $messages, $attributes];
    }

    /**
     * Keeps only the given questions' answers (null when left blank), and the
     * "Other" text only where "Other" was picked.
     *
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherAnswers
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function normalizeAnswers(array $questions, array $answers, array $otherAnswers): array
    {
        $normalized = [];
        $normalizedOther = [];

        foreach ($questions as $question) {
            $value = $answers[$question['key']] ?? null;

            if (is_array($value)) {
                $value = array_values(array_map('strval', $value));
                $value = $value === [] ? null : $value;
            } elseif ($value !== null) {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
            }

            $normalized[$question['key']] = $value;

            if ($question['allow_other'] && in_array(self::OTHER_VALUE, (array) $value, true)) {
                $normalizedOther[$question['key']] = trim((string) ($otherAnswers[$question['key']] ?? ''));
            }
        }

        return [$normalized, $normalizedOther];
    }

    /**
     * The saved answers in words, in question order. Questions the employee
     * wasn't asked (e.g. a section for higher salary grades) are left out;
     * removed questions they answered come last, without a number.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherAnswers
     * @return list<array{number: int|null, key: string, section: string, question: string, removed: bool, lines: list<string>}>
     */
    public function readableAnswers(array $answers, array $otherAnswers): array
    {
        $rows = [];
        $number = 0;

        foreach ($this->questions(includeRemoved: true) as $question) {
            if (! $question['removed']) {
                $number++;
            }

            if (! array_key_exists($question['key'], $answers)) {
                continue;
            }

            $value = $answers[$question['key']];
            $other = $otherAnswers[$question['key']] ?? null;
            $lines = array_map(fn ($choice) => $this->choiceLabel($question, (string) $choice, $other), (array) ($value ?? []));

            if ($question['type'] === self::TYPE_RANKING) {
                $lines = array_map(fn (string $line, int $rank) => ($rank + 1).'. '.$line, $lines, array_keys($lines));
            }

            $rows[] = [
                'number' => $question['removed'] ? null : $number,
                'key' => $question['key'],
                'section' => $question['section'],
                'question' => $question['question'],
                'removed' => $question['removed'],
                'lines' => array_values($lines),
            ];
        }

        return $rows;
    }

    /**
     * Spreadsheet headers for the answers: one column per question, and one
     * per rank for ranking questions so each rank can be counted on its own.
     * Removed questions come last, marked "(removed)".
     *
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        $headers = [];
        $number = 0;

        foreach ($this->questions(includeRemoved: true) as $question) {
            $label = $question['removed'] ? "{$question['question']} (removed)" : 'Q'.(++$number).' '.$question['question'];

            if ($question['type'] === self::TYPE_RANKING) {
                for ($rank = 1; $rank <= $question['rank']; $rank++) {
                    $headers[] = "{$label} (Rank {$rank})";
                }
            } else {
                $headers[] = $label;
            }
        }

        return $headers;
    }

    /**
     * Spreadsheet cells matching exportHeaders(); several answers are joined with "; ".
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherAnswers
     * @return list<string>
     */
    public function exportCells(array $answers, array $otherAnswers): array
    {
        $cells = [];

        foreach ($this->questions(includeRemoved: true) as $question) {
            $values = (array) ($answers[$question['key']] ?? []);
            $other = $otherAnswers[$question['key']] ?? null;
            $labels = array_map(fn ($choice) => $this->choiceLabel($question, (string) $choice, $other), $values);

            if ($question['type'] === self::TYPE_RANKING) {
                for ($rank = 0; $rank < $question['rank']; $rank++) {
                    $cells[] = $labels[$rank] ?? '';
                }
            } else {
                $cells[] = implode('; ', $labels);
            }
        }

        return $cells;
    }

    /**
     * A choice in words: the option's label (with its score, e.g. "4 — Very
     * Satisfied"), "Other: …", or the text answer itself. A choice removed
     * since shows its saved value.
     *
     * @param  array<string, mixed>  $question
     */
    protected function choiceLabel(array $question, string $choice, ?string $otherText): string
    {
        if ($choice === self::OTHER_VALUE) {
            return 'Other: '.trim((string) $otherText);
        }

        foreach ($question['options'] as $option) {
            if ($option['value'] === $choice) {
                return $option['score'] !== null ? "{$option['score']} — {$option['label']}" : $option['label'];
            }
        }

        return $choice;
    }

    /**
     * Fails when an exclusive option (e.g. "No significant barriers") is
     * ticked together with other answers.
     *
     * @param  array<string, mixed>  $question
     */
    private function exclusiveRule(array $question): Closure
    {
        $exclusive = [];

        foreach ($question['options'] as $option) {
            if ($option['exclusive']) {
                $exclusive[$option['value']] = $option['label'];
            }
        }

        return function (string $attribute, mixed $value, Closure $fail) use ($exclusive): void {
            if (! is_array($value) || count($value) < 2) {
                return;
            }

            foreach ($exclusive as $optionValue => $label) {
                if (in_array($optionValue, $value, true)) {
                    $fail("\"{$label}\" can't be combined with other answers.");

                    return;
                }
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toQuestionArray(PublicFormQuestion $question): array
    {
        $options = $question->normalizedOptions();
        $choiceCount = count($options) + ($question->allow_other ? 1 : 0);

        return [
            'key' => $question->answer_key,
            'section' => (string) $question->public_form_section_id,
            'type' => $question->type,
            'question' => $question->question,
            'question_fil' => $question->question_fil,
            'instruction' => $question->instruction,
            'instruction_fil' => $question->instruction_fil,
            'options' => $options,
            'allow_other' => $question->allow_other,
            'other_label' => $question->other_label ?: 'Other',
            'other_label_fil' => $question->other_label_fil ?: 'Iba pa',
            'required' => $question->is_required,
            // A ranking without a saved count ranks up to three choices.
            'rank' => $question->type === self::TYPE_RANKING ? ($question->rank_count ?: min(3, max(1, $choiceCount))) : null,
            'max' => $question->type === self::TYPE_MULTIPLE ? $question->max_choices : null,
            'max_length' => self::TEXT_MAX_LENGTH,
            'removed' => $question->trashed(),
        ];
    }
}
