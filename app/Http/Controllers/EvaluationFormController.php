<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationFacilitator;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationSection;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationFormController extends Controller
{
    // GET /batches/{batch}/evaluation
    public function edit(Batch $batch): Response
    {
        $batch->load([
            'program.coverPage',
            'evaluationForm.sections.questions',
            'evaluationForm.facilitators',
        ]);

        $siblingBatchesWithForms = Batch::where('program_code', $batch->program_code)
            ->where('id', '!=', $batch->id)
            ->whereHas('evaluationForm')
            ->with('evaluationForm:id,batch_id')
            ->get(['id', 'batch']);

        return Inertia::render('programs/EvaluationConfig', [
            'batch' => $batch,
            'siblingBatchesWithForms' => $siblingBatchesWithForms,
        ]);
    }

    // POST /batches/{batch}/evaluation-form
    public function store(Request $request, Batch $batch)
    {
        abort_if($batch->evaluationForm, 422, 'This batch already has an evaluation form.');

        $data = $request->validate([
            'mode' => 'required|in:default,clone',
            'source_batch_id' => 'required_if:mode,clone|nullable|exists:batches,id',
        ]);

        $user = $request->user();

        $form = EvaluationForm::create([
            'batch_id' => $batch->id,
            'slug' => EvaluationForm::generateSlugFor($batch),
            'created_by_empcode' => $user?->empcode,
            'created_by_name' => $user?->name ?? 'System',
        ]);

        if ($data['mode'] === 'clone') {
            $source = EvaluationForm::where('batch_id', $data['source_batch_id'])
                ->with(['sections.questions', 'facilitators'])
                ->firstOrFail();

            DB::transaction(function () use ($form, $source) {
                foreach ($source->sections as $section) {
                    $newSection = $form->sections()->create([
                        'key' => $section->key,
                        'title' => $section->title,
                        'description' => $section->description,
                        'sort_order' => $section->sort_order,
                    ]);

                    foreach ($section->questions as $question) {
                        $newSection->questions()->create([
                            'type' => $question->type,
                            'label' => $question->label,
                            'options' => $question->options,
                            'is_required' => $question->is_required,
                            'sort_order' => $question->sort_order,
                        ]);
                    }
                }

                foreach ($source->facilitators as $facilitator) {
                    $form->facilitators()->create([
                        'name' => $facilitator->name,
                        'role' => $facilitator->role,
                        'sort_order' => $facilitator->sort_order,
                    ]);
                }
            });
        } else {
            $form->seedDefaults();
        }

        return back()->with('success', 'Evaluation form created successfully.');
    }

    // PUT /evaluation-forms/{evaluationForm}
    public function update(Request $request, EvaluationForm $evaluationForm)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'intro_text' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $evaluationForm->update($data);

        return back()->with('success', 'Evaluation form settings updated.');
    }

    // DELETE /evaluation-forms/{evaluationForm}
    public function destroy(EvaluationForm $evaluationForm)
    {
        $evaluationForm->delete();

        return back()->with('success', 'Evaluation form deleted.');
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    // PUT /evaluation-sections/{evaluationSection}
    public function updateSection(Request $request, EvaluationSection $evaluationSection)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $evaluationSection->update($data);

        return back()->with('success', 'Section updated.');
    }

    // POST /evaluation-sections/{evaluationSection}/move-up
    public function moveSectionUp(EvaluationSection $evaluationSection)
    {
        $evaluationSection->moveUp();

        return back();
    }

    // POST /evaluation-sections/{evaluationSection}/move-down
    public function moveSectionDown(EvaluationSection $evaluationSection)
    {
        $evaluationSection->moveDown();

        return back();
    }

    // ── Questions ─────────────────────────────────────────────────────────────

    // POST /evaluation-sections/{evaluationSection}/questions
    public function storeQuestion(Request $request, EvaluationSection $evaluationSection)
    {
        $data = $this->validateQuestion($request);
        $data['sort_order'] = $evaluationSection->questions()->max('sort_order') + 1;

        $evaluationSection->questions()->create($data);

        return back()->with('success', 'Question added.');
    }

    // PUT /evaluation-questions/{evaluationQuestion}
    public function updateQuestion(Request $request, EvaluationQuestion $evaluationQuestion)
    {
        $evaluationQuestion->update($this->validateQuestion($request));

        return back()->with('success', 'Question updated.');
    }

    // DELETE /evaluation-questions/{evaluationQuestion}
    public function destroyQuestion(EvaluationQuestion $evaluationQuestion)
    {
        $evaluationQuestion->delete();

        return back()->with('success', 'Question removed.');
    }

    // POST /evaluation-questions/{evaluationQuestion}/move-up
    public function moveQuestionUp(EvaluationQuestion $evaluationQuestion)
    {
        $evaluationQuestion->moveUp();

        return back();
    }

    // POST /evaluation-questions/{evaluationQuestion}/move-down
    public function moveQuestionDown(EvaluationQuestion $evaluationQuestion)
    {
        $evaluationQuestion->moveDown();

        return back();
    }

    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(EvaluationQuestion::TYPES)],
            'label' => 'required|string',
            'options' => 'required_if:type,checkbox|nullable|array|min:1',
            'options.*' => 'string|max:255',
            'is_required' => 'boolean',
        ]);

        if ($data['type'] !== EvaluationQuestion::TYPE_CHECKBOX) {
            $data['options'] = null;
        }

        return $data;
    }

    // ── Facilitators ──────────────────────────────────────────────────────────

    // POST /evaluation-forms/{evaluationForm}/facilitators
    public function storeFacilitator(Request $request, EvaluationForm $evaluationForm)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
        ]);

        $data['sort_order'] = EvaluationFacilitator::nextSortOrder($evaluationForm->id);

        $evaluationForm->facilitators()->create($data);

        return back()->with('success', 'Facilitator added.');
    }

    // PUT /evaluation-facilitators/{evaluationFacilitator}
    public function updateFacilitator(Request $request, EvaluationFacilitator $evaluationFacilitator)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
        ]);

        $evaluationFacilitator->update($data);

        return back()->with('success', 'Facilitator updated.');
    }

    // DELETE /evaluation-facilitators/{evaluationFacilitator}
    public function destroyFacilitator(EvaluationFacilitator $evaluationFacilitator)
    {
        $evaluationFacilitator->delete();

        return back()->with('success', 'Facilitator removed.');
    }

    // POST /evaluation-facilitators/{evaluationFacilitator}/move-up
    public function moveFacilitatorUp(EvaluationFacilitator $evaluationFacilitator)
    {
        $evaluationFacilitator->moveUp();

        return back();
    }

    // POST /evaluation-facilitators/{evaluationFacilitator}/move-down
    public function moveFacilitatorDown(EvaluationFacilitator $evaluationFacilitator)
    {
        $evaluationFacilitator->moveDown();

        return back();
    }

    // ── Results ───────────────────────────────────────────────────────────────

    // GET /programs/{program}/evaluation-dashboard
    public function dashboardData(Request $request, Program $program)
    {
        $batchId = $request->input('batch_id');

        $formIds = EvaluationForm::whereHas('batch', function ($q) use ($program, $batchId) {
            $q->where('program_code', $program->program_code);
            if ($batchId) {
                $q->where('id', $batchId);
            }
        })->pluck('id');

        $responseIds = EvaluationResponse::whereIn('evaluation_form_id', $formIds)->pluck('id');

        $totalResponses = $responseIds->count();

        $avgBySection = EvaluationAnswer::query()
            ->join('evaluation_questions', 'evaluation_answers.evaluation_question_id', '=', 'evaluation_questions.id')
            ->join('evaluation_sections', 'evaluation_questions.evaluation_section_id', '=', 'evaluation_sections.id')
            ->whereIn('evaluation_answers.evaluation_response_id', $responseIds)
            ->whereIn('evaluation_questions.type', [EvaluationQuestion::TYPE_LIKERT5, EvaluationQuestion::TYPE_SCALE10])
            ->selectRaw('evaluation_sections.key as section_key, evaluation_sections.title as section_title, avg(evaluation_answers.value_numeric) as avg_rating')
            ->groupBy('evaluation_sections.key', 'evaluation_sections.title')
            ->get();

        $avgByFacilitator = EvaluationAnswer::query()
            ->join('evaluation_facilitators', 'evaluation_answers.evaluation_facilitator_id', '=', 'evaluation_facilitators.id')
            ->whereIn('evaluation_answers.evaluation_response_id', $responseIds)
            ->whereNotNull('evaluation_answers.evaluation_facilitator_id')
            ->selectRaw('evaluation_facilitators.id, evaluation_facilitators.name, avg(evaluation_answers.value_numeric) as avg_rating')
            ->groupBy('evaluation_facilitators.id', 'evaluation_facilitators.name')
            ->get();

        $overallDistribution = EvaluationAnswer::query()
            ->join('evaluation_questions', 'evaluation_answers.evaluation_question_id', '=', 'evaluation_questions.id')
            ->whereIn('evaluation_answers.evaluation_response_id', $responseIds)
            ->where('evaluation_questions.type', EvaluationQuestion::TYPE_SCALE10)
            ->selectRaw('evaluation_answers.value_numeric as rating, count(*) as total')
            ->groupBy('evaluation_answers.value_numeric')
            ->orderBy('rating')
            ->get();

        $responsesPerBatch = EvaluationResponse::query()
            ->join('evaluation_forms', 'evaluation_responses.evaluation_form_id', '=', 'evaluation_forms.id')
            ->join('batches', 'evaluation_forms.batch_id', '=', 'batches.id')
            ->whereIn('evaluation_forms.id', $formIds)
            ->selectRaw('batches.id as batch_id, batches.batch as batch_label, count(*) as total')
            ->groupBy('batches.id', 'batches.batch')
            ->get();

        return response()->json([
            'total_responses' => $totalResponses,
            'avg_by_section' => $avgBySection,
            'avg_by_facilitator' => $avgByFacilitator,
            'overall_distribution' => $overallDistribution,
            'responses_per_batch' => $responsesPerBatch,
        ]);
    }

    // GET /evaluation-forms/{evaluationForm}/responses
    public function responses(Request $request, EvaluationForm $evaluationForm)
    {
        $responses = $evaluationForm->responses()
            ->with(['answers.question', 'answers.facilitator'])
            ->latest()
            ->paginate(20);

        return response()->json($responses);
    }
}
