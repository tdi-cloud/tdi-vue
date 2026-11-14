<?php

namespace App\Http\Controllers;

use App\Mail\PublicFormResponseReceivedMail;
use App\Models\Employee;
use App\Models\ProgramRegistration;
use App\Models\PublicForm;
use App\Models\PublicFormResponse;
use App\Models\SiteImage;
use App\Support\PublicForms\PublicFormDefinition;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The public (no login) forms, such as the Learning and Development Survey.
 * Employees find their name in the employee list, answer the questions and
 * get a confirmation email. Each employee can answer a form once.
 */
class PublicFormController extends Controller
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_SEARCH_RESULTS = 10;

    /** Session flash with the reference number, shown once on the success page. */
    public const RECEIPT_SESSION_KEY = 'public_form_receipt';

    // GET /forms
    // Only the forms taking responses; closed ones and drafts stay off the list.
    public function index(): Response
    {
        $forms = PublicForm::query()
            ->takingResponses()
            ->withCount('questions')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('PublicForms/Index', [
            'forms' => $forms->map(fn (PublicForm $form) => $form->toPublicArray($form->questions_count))->values(),
            // The list isn't one form's page, so it uses the site-wide default.
            'backgroundUrl' => SiteImage::urlFor(PublicForm::DEFAULT_BACKGROUND_KEY),
        ]);
    }

    // GET /forms/{slug}
    public function show(Request $request, string $slug): Response
    {
        $form = PublicForm::findBySlugOrFail($slug);
        $definition = $form->definition();
        $closedReason = $form->closedReason();
        $isPreview = $this->isAdminPreview($request);

        if ($closedReason !== null && ! $isPreview) {
            return Inertia::render('PublicForms/Closed', [
                'form' => $form->toPublicArray(count($definition->questions())),
                'backgroundUrl' => $form->publicBackgroundUrl(),
            ]);
        }

        return Inertia::render('PublicForms/Form', [
            'form' => $form->toPublicArray(count($definition->questions())),
            'sections' => $definition->sections(),
            'questions' => $definition->questions(),
            'otherValue' => PublicFormDefinition::OTHER_VALUE,
            'backgroundUrl' => $form->publicBackgroundUrl(),
            'headerImageUrl' => $form->headerImageUrl(),
            'preview' => $isPreview ? ['closedReason' => $closedReason] : null,
        ]);
    }

    // GET /forms/{slug}/search?q=
    // Returns only what's needed to pick the right person, never the full employee record.
    public function search(Request $request, string $slug): JsonResponse
    {
        $form = PublicForm::findBySlugOrFail($slug);

        abort_if($form->closedReason() !== null && ! $this->isAdminPreview($request), 404);

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return response()->json([]);
        }

        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        $employees = Employee::query()
            ->where(function ($outer) use ($words) {
                foreach ($words as $word) {
                    $outer->where(function ($inner) use ($word) {
                        $inner->where('LASTNAME', 'LIKE', "%{$word}%")
                            ->orWhere('FIRSTNAME', 'LIKE', "%{$word}%")
                            ->orWhere('EMPCODE', 'LIKE', "%{$word}%");
                    });
                }
            })
            ->orderBy('LASTNAME')
            ->orderBy('FIRSTNAME')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get(['id', 'EMPCODE', 'LASTNAME', 'FIRSTNAME', 'MI', 'POSITION', 'SG', 'OFFICE/DIVISION']);

        $answered = PublicFormResponse::where('form_slug', $slug)
            ->whereIn('empcode', $employees->pluck('EMPCODE'))
            ->pluck('empcode')
            ->map(fn (string $empcode) => Str::lower($empcode))
            ->all();

        $definition = $form->definition();

        return response()->json($employees->map(fn (Employee $employee) => [
            'empcode' => $employee->EMPCODE,
            'name' => $employee->name,
            'position' => $employee->POSITION,
            'office_division' => $employee->getAttribute('OFFICE/DIVISION'),
            'sections' => $definition->sectionKeysFor($employee),
            'already_answered' => in_array(Str::lower($employee->EMPCODE), $answered, true),
        ])->values());
    }

    // POST /forms/{slug}
    public function submit(Request $request, string $slug): RedirectResponse
    {
        $form = PublicForm::findBySlugOrFail($slug);

        // Honeypot: the field is hidden from people, so only bots fill it in.
        // Pretend it worked and save nothing.
        if ($request->filled('website')) {
            return redirect()->route('public-forms.success', $slug);
        }

        // Also in an admin preview: a closed form never takes answers.
        if ($form->closedReason() !== null) {
            return redirect()->route('public-forms.show', $slug);
        }

        $employee = $this->findListedEmployee((string) $request->input('empcode'));
        $this->ensureNotAnswered($slug, $employee);

        $definition = $form->definition();
        $questions = $definition->questionsFor($employee);
        [$rules, $messages, $attributes] = $definition->rules($questions, $request);

        $data = $request->validate(array_merge([
            'empcode' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'data_privacy_consent' => ['accepted'],
        ], $rules), $messages, array_merge($attributes, [
            'email' => 'email address',
            'data_privacy_consent' => 'data privacy consent',
        ]));

        [$answers, $otherAnswers] = $definition->normalizeAnswers($questions, $data['answers'] ?? [], $data['answers_other'] ?? []);

        try {
            $response = PublicFormResponse::create([
                'form_slug' => $slug,
                'empcode' => $employee->EMPCODE,
                'full_name' => $employee->name,
                'email' => Str::lower($data['email']),
                'region' => $employee->REGION,
                'office_division' => $employee->getAttribute('OFFICE/DIVISION'),
                'employee' => ProgramRegistration::snapshotFromEmployee($employee),
                'answers' => $answers,
                'other_answers' => $otherAnswers,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two submissions for the same person at the same moment.
            throw $this->alreadyAnswered($employee);
        }

        $response->setRelation('form', $form);
        $emailSent = $this->sendConfirmation($response, $definition);

        return redirect()->route('public-forms.success', $slug)->with(self::RECEIPT_SESSION_KEY, [
            'reference' => $response->reference,
            'name' => $response->full_name,
            'email' => $response->email,
            'email_sent' => $emailSent,
        ]);
    }

    // GET /forms/{slug}/success
    public function success(Request $request, string $slug): Response
    {
        $form = PublicForm::query()->where('slug', $slug)->withCount('questions')->firstOrFail();

        return Inertia::render('PublicForms/Success', [
            'form' => $form->toPublicArray($form->questions_count),
            'receipt' => $request->session()->get(self::RECEIPT_SESSION_KEY),
            'backgroundUrl' => $form->publicBackgroundUrl(),
        ]);
    }

    /**
     * Admins can open a closed form with ?preview=1 to check how it looks
     * before opening it.
     */
    private function isAdminPreview(Request $request): bool
    {
        return $request->boolean('preview') && $request->user()?->isAdmin() === true;
    }

    private function findListedEmployee(string $empcode): Employee
    {
        $employee = trim($empcode) === ''
            ? null
            : Employee::whereRaw('LOWER(EMPCODE) = ?', [Str::lower(trim($empcode))])->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'empcode' => "We couldn't find that employee in the list. Go back to the first step and search for your name again.",
            ]);
        }

        return $employee;
    }

    private function ensureNotAnswered(string $slug, Employee $employee): void
    {
        $exists = PublicFormResponse::where('form_slug', $slug)
            ->whereRaw('LOWER(empcode) = ?', [Str::lower($employee->EMPCODE)])
            ->exists();

        if ($exists) {
            throw $this->alreadyAnswered($employee);
        }
    }

    private function alreadyAnswered(Employee $employee): ValidationException
    {
        return ValidationException::withMessages([
            'empcode' => "{$employee->name} has already answered this form. Each employee can answer it only once.",
        ]);
    }

    /**
     * Sends the confirmation right away. A failed email doesn't undo the
     * response: it's logged, and the success page says the email didn't go out.
     */
    private function sendConfirmation(PublicFormResponse $response, PublicFormDefinition $definition): bool
    {
        try {
            Mail::to($response->email)->send(new PublicFormResponseReceivedMail($response, $definition));
        } catch (Throwable $e) {
            Log::error('Failed to send public form confirmation email', [
                'public_form_response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $response->update(['confirmation_sent_at' => now()]);

        return true;
    }
}
