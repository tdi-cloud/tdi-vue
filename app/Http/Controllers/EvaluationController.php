<?php

namespace App\Http\Controllers;

use App\Models\EvaluationAnswer;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    // GET /evaluate/{slug}
    public function show(string $slug)
    {
        $form = EvaluationForm::where('slug', $slug)
            ->with([
                'sections.questions',
                'facilitators',
                'batch.program.coverPage',
            ])
            ->firstOrFail();

        if (! $form->is_active) {
            return Inertia::render('Evaluation/Closed', [
                'form' => $form,
            ]);
        }

        $participants = $form->batch->participants()
            ->with('employee')
            ->get()
            ->map(fn ($p) => [
                'participant_id' => $p->id,
                'empcode' => $p->empcode,
                'name' => $p->employee?->name ?? $p->empcode,
            ])
            ->values();

        return Inertia::render('Evaluation/Form', [
            'form' => $form,
            'participants' => $participants,
        ]);
    }

    // POST /evaluate/{slug}
    public function submit(Request $request, string $slug)
    {
        $form = EvaluationForm::where('slug', $slug)
            ->with(['sections.questions', 'facilitators'])
            ->firstOrFail();

        if (! $form->is_active) {
            return redirect()->route('evaluate.show', $slug);
        }

        if ($request->filled('empcode') && $form->responses()->where('empcode', $request->empcode)->exists()) {
            return back()->with('error', 'You have already submitted an evaluation for this batch.')->withInput();
        }

        $rules = [
            'email' => 'required|email|max:255',
            'empcode' => 'nullable|string|max:255',
            'respondent_name' => 'required|string|max:255',
            'name_source' => 'required|in:'.EvaluationResponse::SOURCE_PARTICIPANT.','.EvaluationResponse::SOURCE_MANUAL,
            'participant_id' => [
                'nullable', 'integer',
                Rule::exists('participants', 'id')->where('batch_id', $form->batch_id),
            ],
        ];

        foreach ($form->sections as $section) {
            foreach ($section->questions as $question) {
                if ($section->key === EvaluationSection::KEY_FACILITATORS) {
                    foreach ($form->facilitators as $facilitator) {
                        $key = "facilitator_answers.{$facilitator->id}.{$question->id}";
                        $rules[$key] = $this->rulesForQuestion($question);
                        if ($question->type === EvaluationQuestion::TYPE_CHECKBOX) {
                            $rules["{$key}.*"] = Rule::in($question->options ?? []);
                        }
                    }
                } else {
                    $key = "answers.{$question->id}";
                    $rules[$key] = $this->rulesForQuestion($question);
                    if ($question->type === EvaluationQuestion::TYPE_CHECKBOX) {
                        $rules["{$key}.*"] = Rule::in($question->options ?? []);
                    }
                }
            }
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($form, $data, $request) {
            $response = EvaluationResponse::create([
                'evaluation_form_id' => $form->id,
                'participant_id' => $data['participant_id'] ?? null,
                'empcode' => $data['empcode'] ?? null,
                'email' => $data['email'],
                'respondent_name' => $data['respondent_name'],
                'name_source' => $data['name_source'],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            foreach ($form->sections as $section) {
                foreach ($section->questions as $question) {
                    if ($section->key === EvaluationSection::KEY_FACILITATORS) {
                        foreach ($form->facilitators as $facilitator) {
                            $value = $data['facilitator_answers'][$facilitator->id][$question->id] ?? null;
                            $this->createAnswer($response, $question, $value, $facilitator->id);
                        }
                    } else {
                        $value = $data['answers'][$question->id] ?? null;
                        $this->createAnswer($response, $question, $value, null);
                    }
                }
            }
        });

        return redirect()->route('evaluate.success', $slug);
    }

    // GET /evaluate/{slug}/success
    public function success(string $slug)
    {
        $form = EvaluationForm::where('slug', $slug)
            ->with('batch.program')
            ->firstOrFail();

        return Inertia::render('Evaluation/Success', [
            'form' => $form,
        ]);
    }

    private function rulesForQuestion(EvaluationQuestion $question): array
    {
        $required = $question->is_required ? 'required' : 'nullable';

        return match ($question->type) {
            EvaluationQuestion::TYPE_LIKERT5 => [$required, 'integer', 'between:1,5'],
            EvaluationQuestion::TYPE_SCALE10 => [$required, 'integer', 'between:1,10'],
            EvaluationQuestion::TYPE_CHECKBOX => [$required, 'array'],
            default => [$required, 'string'],
        };
    }

    private function createAnswer(EvaluationResponse $response, EvaluationQuestion $question, mixed $value, ?int $facilitatorId): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $isNumeric = in_array($question->type, [EvaluationQuestion::TYPE_LIKERT5, EvaluationQuestion::TYPE_SCALE10], true);

        EvaluationAnswer::create([
            'evaluation_response_id' => $response->id,
            'evaluation_question_id' => $question->id,
            'evaluation_facilitator_id' => $facilitatorId,
            'value_numeric' => $isNumeric ? (int) $value : null,
            'value_text' => $isNumeric ? null : (is_array($value) ? json_encode($value) : (string) $value),
        ]);
    }
}
