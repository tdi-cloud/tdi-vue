<?php

namespace App\Http\Controllers;

use App\Models\PublicForm;
use App\Models\PublicFormQuestion;
use App\Models\PublicFormResponse;
use App\Models\PublicFormSection;
use App\Models\SiteImage;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin side of the public forms: the list of forms (and creating new ones),
 * and for each form its responses (list, delete, CSV export), its question
 * editor (see PublicFormEditorController) and its settings: public link,
 * title and description, open/close and background picture.
 */
class PublicFormAdminController extends Controller
{
    public const TABS = ['responses', 'questions', 'settings'];

    // GET /public-forms
    public function index(): Response
    {
        $forms = PublicForm::query()->withCount('questions')->with('creator:id,name')->latest()->latest('id')->get();

        $totals = PublicFormResponse::whereIn('form_slug', $forms->pluck('slug'))
            ->selectRaw('form_slug, COUNT(*) as total, MAX(created_at) as latest_at')
            ->groupBy('form_slug')
            ->get()
            ->keyBy('form_slug');

        return Inertia::render('PublicForms/Admin/Index', [
            'forms' => $forms->map(function (PublicForm $form) use ($totals) {
                $total = $totals->get($form->slug);
                $latestAt = $total?->latest_at ? Carbon::parse($total->latest_at) : null;

                return array_merge($this->formData($form, $form->questions_count), [
                    'responses_count' => (int) ($total?->total ?? 0),
                    'latest_response_at' => $latestAt?->format('M j, Y g:i A'),
                    'created_by_name' => $form->creator?->name,
                ]);
            })->values(),
        ]);
    }

    // POST /public-forms
    // A new form starts closed, as a blank form or as a copy of another form's questions.
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'copy_from' => ['nullable', 'integer', Rule::exists('public_forms', 'id')],
        ], [], [
            'copy_from' => 'form to copy',
        ]);

        $source = isset($data['copy_from']) ? PublicForm::with('sections.questions')->find($data['copy_from']) : null;
        $title = trim($data['title']);

        $form = DB::transaction(function () use ($request, $source, $title) {
            $form = PublicForm::create([
                'slug' => PublicForm::uniqueSlugFor($title),
                'title' => $title,
                'kicker' => $source?->kicker,
                'description' => $source?->description,
                'reference_prefix' => PublicForm::referencePrefixFor($title),
                // Closed until an admin has set it up and opens it.
                'is_open' => false,
                'hide_background' => $source !== null && $source->hide_background,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($source === null) {
                $form->sections()->create(['title' => 'Questions', 'sort_order' => 0]);

                return $form;
            }

            foreach ($source->sections as $section) {
                $sectionCopy = $form->sections()->create($section->only(['title', 'step_label', 'description', 'min_salary_grade', 'sort_order']));

                foreach ($section->questions as $question) {
                    PublicFormQuestion::create(array_merge($question->only([
                        'answer_key', 'type', 'question', 'question_fil', 'instruction', 'instruction_fil', 'options',
                        'allow_other', 'other_label', 'other_label_fil', 'is_required', 'rank_count', 'max_choices', 'sort_order',
                    ]), [
                        'public_form_id' => $form->id,
                        'public_form_section_id' => $sectionCopy->id,
                    ]));
                }
            }

            return $form;
        });

        return redirect()->route('public-forms.admin.show', ['slug' => $form->slug, 'tab' => 'questions'])->with('success', FlashMessage::make(
            'Form created',
            "{$form->title} is saved, but not open yet.",
            'Add or change its questions, preview it, then open it in Settings.',
        ));
    }

    // GET /public-forms/{slug}
    public function show(Request $request, string $slug): Response
    {
        $form = PublicForm::findBySlugOrFail($slug);
        $definition = $form->definition();
        $search = trim($request->string('search')->toString());

        $responses = PublicFormResponse::where('form_slug', $slug)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('empcode', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('office_division', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->latest('id')
            ->paginate(LiteMode::perPage($request, 15))
            ->withQueryString()
            ->through(function (PublicFormResponse $response) use ($form, $definition) {
                $response->setRelation('form', $form);

                return [
                    'id' => $response->id,
                    'reference' => $response->reference,
                    'empcode' => $response->empcode,
                    'name' => $response->full_name,
                    'email' => $response->email,
                    'position' => $response->employee['position'] ?? null,
                    'office_division' => $response->office_division,
                    'region' => $response->region,
                    'submitted_at' => $response->created_at?->format('M j, Y g:i A'),
                    'confirmation_sent' => $response->confirmation_sent_at !== null,
                    'answers' => $definition->readableAnswers($response->answers ?? [], $response->other_answers ?? []),
                ];
            });

        $total = PublicFormResponse::where('form_slug', $slug)->count();
        $tab = $request->query('tab');

        return Inertia::render('PublicForms/Admin/Show', [
            'form' => $this->formData($form, count($definition->questions())),
            'sections' => $definition->sections(),
            'editor' => $this->editorData($form),
            'responses' => $responses,
            'filters' => ['search' => $search],
            'stats' => [
                'total' => $total,
                'emails_not_sent' => PublicFormResponse::where('form_slug', $slug)->whereNull('confirmation_sent_at')->count(),
            ],
            'defaultBackgroundUrl' => SiteImage::urlFor(PublicForm::DEFAULT_BACKGROUND_KEY),
            'initialTab' => in_array($tab, self::TABS, true) ? $tab : ($total > 0 ? 'responses' : 'questions'),
        ]);
    }

    // PUT /public-forms/{slug}
    public function update(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->withCount('questions')->firstOrFail();

        $data = $request->validate([
            'is_open' => ['required', 'boolean'],
            'closes_at' => ['nullable', 'date'],
        ], [], [
            'is_open' => 'accepting responses',
            'closes_at' => 'closing date',
        ]);

        if ($data['is_open'] && $form->questions_count === 0) {
            throw ValidationException::withMessages([
                'is_open' => 'Add at least one question before opening the form.',
            ]);
        }

        $form->update([
            'is_open' => $data['is_open'],
            'closes_at' => $data['closes_at'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $message = match ($form->closedReason()) {
            null => FlashMessage::make('Form is open', "{$form->title} is taking responses".($form->closes_at ? ' until '.$form->closes_at->format('F j, Y g:i A') : '').'.', 'Share the public link with employees.'),
            PublicForm::CLOSED_DEADLINE => FlashMessage::make('Form is closed', "The closing date of {$form->title} has passed, so it no longer takes responses.", 'Set a later closing date to open it again.'),
            default => FlashMessage::make('Form is closed', "{$form->title} no longer takes responses. The answers already sent are kept."),
        };

        return back()->with('success', $message);
    }

    // PUT /public-forms/{slug}/details
    // The title, the short line above it and the description people see.
    public function updateDetails(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kicker' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'kicker' => 'text above the title',
        ]);

        $form->update([
            'title' => trim($data['title']),
            'kicker' => $this->nullableText($data['kicker'] ?? null),
            'description' => $this->nullableText($data['description'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', FlashMessage::make(
            'Title and description saved',
            "The public form now shows \"{$form->title}\". People see the change the next time they open it.",
        ));
    }

    // POST /public-forms/{slug}/background
    // This form's own picture, in place of the site-wide default (Homepage Images).
    public function uploadBackground(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['image' => 'background picture']);

        $this->deleteBackgroundFile($form);

        // Uploading a picture means they want it shown, even if "No background" was chosen before.
        $form->update([
            'background_image' => $request->file('image')->store(PublicForm::BACKGROUND_DIRECTORY, 'public'),
            'hide_background' => false,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', FlashMessage::make('Background updated', "People will see the new picture when they open the {$form->title}."));
    }

    // PUT /public-forms/{slug}/background
    // "No background" (a plain light gray page), or showing the picture again.
    public function updateBackground(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'hide_background' => ['required', 'boolean'],
        ]);

        $form->update([
            'hide_background' => $data['hide_background'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $data['hide_background']
            ? FlashMessage::make('Background removed', "The {$form->title} now shows on a plain light gray page.")
            : FlashMessage::make('Background shown', "The {$form->title} shows its background picture again."));
    }

    // DELETE /public-forms/{slug}/background
    public function destroyBackground(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->firstOrFail();

        $this->deleteBackgroundFile($form);
        // Back to the site default, which also undoes "No background".
        $form->update(['background_image' => null, 'hide_background' => false, 'updated_by' => $request->user()->id]);

        return back()->with('success', FlashMessage::make('Background reset', "The {$form->title} now uses the site-wide default picture from Homepage Images."));
    }

    // DELETE /public-forms/{slug}
    // Only a form without responses can be deleted; one with responses can be closed instead.
    public function destroy(string $slug): RedirectResponse
    {
        $form = PublicForm::where('slug', $slug)->withCount('responses')->firstOrFail();

        if ($form->responses_count > 0) {
            return back()->with('error', FlashMessage::make(
                "This form can't be deleted",
                "{$form->title} has {$form->responses_count} ".Str::plural('response', $form->responses_count).'. Close it instead, or delete its responses first.',
            ));
        }

        $this->deleteBackgroundFile($form);
        $form->delete();

        return redirect()->route('public-forms.admin.index')->with('success', FlashMessage::make('Form deleted', "{$form->title} and its questions were deleted. Its link no longer works."));
    }

    // DELETE /public-forms/{slug}/responses/{publicFormResponse}
    // e.g. a test answer before the form is shared; the employee can then answer again.
    public function destroyResponse(string $slug, PublicFormResponse $publicFormResponse): RedirectResponse
    {
        abort_unless($publicFormResponse->form_slug === $slug, 404);

        $publicFormResponse->delete();

        return back()->with('success', FlashMessage::make('Response deleted', "{$publicFormResponse->full_name}'s answers were deleted, so they can answer the form again."));
    }

    // GET /public-forms/{slug}/export
    public function export(string $slug): StreamedResponse
    {
        $form = PublicForm::findBySlugOrFail($slug);
        $definition = $form->definition();
        $filename = Str::slug($form->title).'-responses-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($form, $definition) {
            $out = fopen('php://output', 'w');

            // BOM so Excel opens the Filipino and accented characters correctly
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge([
                'Reference', 'Submitted At', 'EMPCODE', 'Name', 'Position', 'Salary Grade', 'Plantilla Status',
                'Sex', 'Region', 'Office', 'Office/Division', 'Email', 'Confirmation Email Sent',
            ], $definition->exportHeaders()));

            PublicFormResponse::where('form_slug', $form->slug)->chunkById(500, function ($responses) use ($out, $form, $definition) {
                foreach ($responses as $response) {
                    $response->setRelation('form', $form);
                    $employee = $response->employee ?? [];

                    fputcsv($out, array_merge([
                        $response->reference,
                        $response->created_at?->format('Y-m-d H:i:s'),
                        $response->empcode,
                        $response->full_name,
                        $employee['position'] ?? '',
                        $employee['sg'] ?? '',
                        $employee['plantilla_status'] ?? '',
                        $employee['sex'] ?? '',
                        $response->region,
                        $employee['office'] ?? '',
                        $response->office_division,
                        $response->email,
                        $response->confirmation_sent_at ? 'Yes' : 'No',
                    ], $definition->exportCells($response->answers ?? [], $response->other_answers ?? [])));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(PublicForm $form, int $questionCount): array
    {
        return array_merge($form->toPublicArray($questionCount), [
            'id' => $form->id,
            'public_url' => route('public-forms.show', $form->slug),
            'preview_url' => route('public-forms.show', ['slug' => $form->slug, 'preview' => 1]),
            'question_count' => $questionCount,
            'is_open' => $form->is_open,
            'closes_at' => $form->closes_at?->format('Y-m-d\TH:i'),
            'background_image_url' => $form->customBackgroundUrl(),
            'hide_background' => $form->hide_background,
        ]);
    }

    /**
     * Sections and questions with everything the question editor needs.
     *
     * @return array<string, mixed>
     */
    private function editorData(PublicForm $form): array
    {
        return [
            'sections' => $form->sections->map(fn (PublicFormSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'step_label' => $section->step_label,
                'description' => $section->description,
                'min_salary_grade' => $section->min_salary_grade,
                'audience' => $section->audienceLabel(),
                'questions' => $section->questions->map(fn (PublicFormQuestion $question) => [
                    'id' => $question->id,
                    'section_id' => $question->public_form_section_id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'question_fil' => $question->question_fil,
                    'instruction' => $question->instruction,
                    'instruction_fil' => $question->instruction_fil,
                    'options' => $question->normalizedOptions(),
                    'allow_other' => $question->allow_other,
                    'other_label' => $question->other_label,
                    'other_label_fil' => $question->other_label_fil,
                    'required' => $question->is_required,
                    'rank' => $question->rank_count,
                    'max' => $question->max_choices,
                ])->values(),
            ])->values(),
            'removed_count' => PublicFormQuestion::onlyTrashed()->where('public_form_id', $form->id)->count(),
            'other_value' => PublicFormDefinition::OTHER_VALUE,
        ];
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function deleteBackgroundFile(PublicForm $form): void
    {
        if ($form->background_image && Storage::disk('public')->exists($form->background_image)) {
            Storage::disk('public')->delete($form->background_image);
        }
    }
}
