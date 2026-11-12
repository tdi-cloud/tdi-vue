<?php

namespace App\Http\Controllers;

use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormSection;
use App\Support\FlashMessage;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The question editor of a public form (admins): sections (each one a step
 * of the form) and their questions. Answers are saved under each question's
 * answer_key and its choices' values, which never change once created, so
 * wording can be edited without breaking answers already sent.
 */
class PublicFormEditorController extends Controller
{
    public const MAX_SALARY_GRADE = 33;

    // POST /public-forms/{slug}/sections
    public function storeSection(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();

        $form->sections()->create(array_merge($this->validateSection($request), [
            'sort_order' => (int) PublicFormSection::where('public_form_id', $form->id)->max('sort_order') + 1,
        ]));

        return back()->with('success', FlashMessage::make('Section added', 'It shows as a new step of the form.', 'Add its questions next.'));
    }

    // PUT /public-form-sections/{section}
    public function updateSection(Request $request, PublicFormSection $section): RedirectResponse
    {
        $section->update($this->validateSection($request));

        return back()->with('success', FlashMessage::make('Section saved', 'People see the change the next time they open the form.'));
    }

    // DELETE /public-form-sections/{section}
    public function destroySection(PublicFormSection $section): RedirectResponse
    {
        if ($section->questions()->exists()) {
            throw ValidationException::withMessages([
                'section' => "Move this section's questions to another section, or remove them, before deleting it.",
            ]);
        }

        if (PublicFormSection::where('public_form_id', $section->public_form_id)->count() <= 1) {
            throw ValidationException::withMessages([
                'section' => 'A form needs at least one section.',
            ]);
        }

        $section->delete();

        return back()->with('success', FlashMessage::make('Section deleted', "\"{$section->title}\" was removed from the form."));
    }

    // POST /public-form-sections/{section}/move
    public function moveSection(Request $request, PublicFormSection $section): RedirectResponse
    {
        $this->direction($request) === 'up' ? $section->moveUp() : $section->moveDown();

        return back();
    }

    // POST /public-forms/{slug}/questions
    public function storeQuestion(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();
        $data = $this->validateQuestion($request, $form);

        PublicFormQuestion::create(array_merge($data, [
            'public_form_id' => $form->id,
            'answer_key' => $this->uniqueAnswerKey($form, $data['question']),
            'sort_order' => $this->nextSortOrder($data['public_form_section_id']),
        ]));

        return back()->with('success', FlashMessage::make('Question added', 'People see it the next time they open the form.'));
    }

    // PUT /public-form-questions/{question}
    public function updateQuestion(Request $request, PublicFormQuestion $question): RedirectResponse
    {
        $data = $this->validateQuestion($request, $question->form, $question);

        // Moved to another section: it goes last there.
        if ($data['public_form_section_id'] !== $question->public_form_section_id) {
            $data['sort_order'] = $this->nextSortOrder($data['public_form_section_id']);
        }

        $question->update($data);

        return back()->with('success', FlashMessage::make('Question saved', 'People see the change the next time they open the form.'));
    }

    // DELETE /public-form-questions/{question}
    public function destroyQuestion(PublicFormQuestion $question): RedirectResponse
    {
        // Soft delete: answers already sent to it still show in the responses and the CSV.
        $question->delete();

        return back()->with('success', FlashMessage::make('Question removed', 'Answers already sent to it still show in the responses and the CSV export.'));
    }

    // POST /public-form-questions/{question}/move
    public function moveQuestion(Request $request, PublicFormQuestion $question): RedirectResponse
    {
        $this->direction($request) === 'up' ? $question->moveUp() : $question->moveDown();

        return back();
    }

    /**
     * @return array{title: string, step_label: string|null, description: string|null, min_salary_grade: int|null}
     */
    private function validateSection(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'step_label' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'min_salary_grade' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_SALARY_GRADE],
        ], [], [
            'step_label' => 'short name',
            'min_salary_grade' => 'minimum salary grade',
        ]);

        return [
            'title' => trim($data['title']),
            'step_label' => $this->nullableText($data['step_label'] ?? null),
            'description' => $this->nullableText($data['description'] ?? null),
            'min_salary_grade' => isset($data['min_salary_grade']) ? (int) $data['min_salary_grade'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request, PublicForm $form, ?PublicFormQuestion $existing = null): array
    {
        $type = (string) $request->input('type');
        $hasChoices = in_array($type, [PublicFormDefinition::TYPE_RANKING, PublicFormDefinition::TYPE_MULTIPLE, PublicFormDefinition::TYPE_SINGLE], true);
        $choiceCount = $hasChoices ? count((array) $request->input('options', [])) + ($request->boolean('allow_other') ? 1 : 0) : 0;

        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('public_form_sections', 'id')->where('public_form_id', $form->id)],
            'type' => ['required', Rule::in(PublicFormQuestion::TYPES)],
            'question' => ['required', 'string', 'max:500'],
            'question_fil' => ['nullable', 'string', 'max:500'],
            'instruction' => ['nullable', 'string', 'max:255'],
            'instruction_fil' => ['nullable', 'string', 'max:255'],
            'required' => ['boolean'],
            'options' => $hasChoices ? ['required', 'array', 'min:2', 'max:30'] : ['nullable', 'array'],
            'options.*.value' => ['nullable', 'string', 'max:60'],
            'options.*.label' => $hasChoices ? ['required', 'string', 'max:255', 'distinct:ignore_case'] : ['nullable'],
            'options.*.label_fil' => ['nullable', 'string', 'max:255'],
            'options.*.score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'options.*.exclusive' => ['nullable', 'boolean'],
            'allow_other' => ['boolean'],
            'other_label' => ['nullable', 'string', 'max:100'],
            'other_label_fil' => ['nullable', 'string', 'max:100'],
            'rank' => $type === PublicFormDefinition::TYPE_RANKING ? ['required', 'integer', 'min:1', 'max:'.max(1, $choiceCount)] : ['nullable'],
            'max' => $type === PublicFormDefinition::TYPE_MULTIPLE ? ['nullable', 'integer', 'min:1', 'max:'.max(1, $choiceCount)] : ['nullable'],
        ], [
            'options.required' => 'Add at least two choices.',
            'options.min' => 'Add at least two choices.',
            'options.*.label.required' => 'Type this choice, or remove it.',
            'options.*.label.distinct' => 'Each choice must be different.',
            'rank.max' => "You can't ask people to rank more choices than the question has.",
            'max.max' => "The limit can't be more than the number of choices.",
        ], [
            'section_id' => 'section',
            'question_fil' => 'Filipino translation',
            'instruction_fil' => 'Filipino instruction',
            'rank' => 'number of choices to rank',
            'max' => 'most answers allowed',
        ]);

        $allowOther = $hasChoices && (bool) ($data['allow_other'] ?? false);

        return [
            'public_form_section_id' => (int) $data['section_id'],
            'type' => $type,
            'question' => trim($data['question']),
            'question_fil' => $this->nullableText($data['question_fil'] ?? null),
            'instruction' => $this->nullableText($data['instruction'] ?? null),
            'instruction_fil' => $this->nullableText($data['instruction_fil'] ?? null),
            'is_required' => (bool) ($data['required'] ?? false),
            'options' => $hasChoices ? $this->choices($data['options'], $type, $existing) : null,
            'allow_other' => $allowOther,
            'other_label' => $allowOther ? ($this->nullableText($data['other_label'] ?? null) ?? 'Other') : null,
            'other_label_fil' => $allowOther ? ($this->nullableText($data['other_label_fil'] ?? null) ?? 'Iba pa') : null,
            'rank_count' => $type === PublicFormDefinition::TYPE_RANKING ? (int) $data['rank'] : null,
            'max_choices' => $type === PublicFormDefinition::TYPE_MULTIPLE && isset($data['max']) ? (int) $data['max'] : null,
        ];
    }

    /**
     * The choices as saved. A choice that already existed keeps its value
     * (answers point to it); a new one gets a value made from its label that
     * the question has never used, so old answers never point to it.
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array{value: string, label: string, label_fil: string|null, score: int|null, exclusive: bool}>
     */
    private function choices(array $options, string $type, ?PublicFormQuestion $existing): array
    {
        $existingValues = array_column($existing?->normalizedOptions() ?? [], 'value');
        $used = [];
        $choices = [];

        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');

            if (! in_array($value, $existingValues, true) || in_array($value, $used, true)) {
                $value = $this->newChoiceValue((string) $option['label'], [...$existingValues, ...$used]);
            }

            $used[] = $value;
            $score = $option['score'] ?? null;

            $choices[] = [
                'value' => $value,
                'label' => trim((string) $option['label']),
                'label_fil' => $this->nullableText($option['label_fil'] ?? null),
                // Only a single-choice question shows choices as a rating scale.
                'score' => $type === PublicFormDefinition::TYPE_SINGLE && $score !== null && $score !== '' ? (int) $score : null,
                // Only a "pick several" question has a choice that can't be combined with others.
                'exclusive' => $type === PublicFormDefinition::TYPE_MULTIPLE && (bool) ($option['exclusive'] ?? false),
            ];
        }

        return $choices;
    }

    /**
     * @param  list<string>  $taken
     */
    private function newChoiceValue(string $label, array $taken): string
    {
        $base = Str::slug(Str::limit($label, 50, ''), '_') ?: 'choice';
        $value = $base;

        for ($number = 2; in_array($value, $taken, true) || $value === PublicFormDefinition::OTHER_VALUE; $number++) {
            $value = "{$base}_{$number}";
        }

        return $value;
    }

    /**
     * A key for the question's answers made from its words, unique in the
     * form (removed questions included, so their old answers stay theirs).
     */
    private function uniqueAnswerKey(PublicForm $form, string $question): string
    {
        $base = Str::slug(Str::limit($question, 40, ''), '_') ?: 'question';
        $key = $base;

        for ($number = 2; PublicFormQuestion::withTrashed()->where('public_form_id', $form->id)->where('answer_key', $key)->exists(); $number++) {
            $key = "{$base}_{$number}";
        }

        return $key;
    }

    private function nextSortOrder(int $sectionId): int
    {
        return (int) PublicFormQuestion::where('public_form_section_id', $sectionId)->max('sort_order') + 1;
    }

    private function direction(Request $request): string
    {
        return $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
