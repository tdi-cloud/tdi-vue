<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormEditor;
use App\Models\RegistrationQuestion;
use App\Models\SiteImage;
use App\Models\User;
use App\Support\EmployeeFieldOptions;
use App\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin side of a batch's public registration form: settings, look and the
 * extra questions. Any admin can view it. Only the program's creator or a
 * superadmin can set one up; after that, only the form's owner, its editors
 * or a superadmin can change it.
 */
class RegistrationFormController extends Controller
{
    // GET /batches/{batch}/registration
    public function edit(Request $request, Batch $batch): Response
    {
        $batch->load(['program.coverPage', 'registrationForm.questions', 'registrationForm.editors.user:id,name,empcode,access']);
        $form = $batch->registrationForm;
        $user = $request->user();

        return Inertia::render('programs/RegistrationConfig', [
            'batch' => $batch->only(['id', 'batch', 'program_code', 'date_start', 'date_end', 'venue']),
            'program' => $batch->program?->only(['id', 'title', 'pax']),
            'registrationForm' => $form ? [
                'id' => $form->id,
                'slug' => $form->slug,
                'title' => $form->title,
                'intro_text' => $form->intro_text,
                'theme' => $form->theme,
                'is_open' => $form->is_open,
                'closes_at_input' => $form->closes_at?->format('Y-m-d\TH:i'),
                'closes_at_label' => $form->closes_at?->format('M j, Y g:i A'),
                'slot_limit' => $form->slot_limit,
                'slots_taken' => $form->slotsTaken(),
                'closed_reason' => $form->closedReason(),
                'background_image_url' => $form->background_image_url,
                'hide_background' => $form->hide_background,
                'created_by_name' => $form->created_by_name,
                'owner' => $form->created_by_empcode ? [
                    'name' => $form->created_by_name,
                    'empcode' => $form->created_by_empcode,
                ] : null,
                'editors' => $form->editors->map(fn (RegistrationFormEditor $editor) => [
                    'id' => $editor->id,
                    'name' => $editor->user->name,
                    'empcode' => $editor->user->empcode,
                    'has_admin_access' => $editor->user->isAdmin(),
                    'added_by_name' => $editor->added_by_name,
                    'added_at' => $editor->created_at?->format('M j, Y g:i A'),
                ])->values(),
                'questions' => $form->questions,
            ] : null,
            'registrations' => $form ? $this->registrationRows($form) : [],
            'canCreateForm' => $form === null && $batch->program?->canCreateRegistrationFormsBy($user) === true,
            'canEdit' => $form?->canBeEditedBy($user) === true,
            'canManageEditors' => $form?->canManageEditorsBy($user) === true,
            'defaultBackgroundUrl' => SiteImage::urlFor('registration_background'),
            'themes' => RegistrationForm::THEMES,
            'fieldOptions' => EmployeeFieldOptions::all(),
        ]);
    }

    /**
     * Everything the review table needs, including whether approving will
     * create an account or is blocked by an email that someone else uses.
     *
     * @return list<array<string, mixed>>
     */
    private function registrationRows(RegistrationForm $form): array
    {
        $registrations = $form->registrations()
            ->with(['user:id,email,must_change_password', 'provisionalEmployee:id,program_registration_id,status'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        $lowerEmpcodes = $registrations->map(fn (ProgramRegistration $r) => Str::lower($r->empcode))->unique()->values();
        $lowerEmails = $registrations->map(fn (ProgramRegistration $r) => Str::lower($r->email))->unique()->values();

        $listedEmpcodes = Employee::whereIn(DB::raw('LOWER(EMPCODE)'), $lowerEmpcodes)
            ->pluck('EMPCODE')
            ->map(fn ($code) => Str::lower($code))
            ->flip();

        $accountEmpcodes = User::whereIn(DB::raw('LOWER(empcode)'), $lowerEmpcodes)
            ->pluck('empcode')
            ->map(fn ($code) => Str::lower((string) $code))
            ->flip();

        $emailOwners = User::whereIn(DB::raw('LOWER(email)'), $lowerEmails)
            ->get(['email', 'empcode'])
            ->mapWithKeys(fn (User $user) => [Str::lower($user->email) => Str::lower((string) $user->empcode)]);

        $reviewerNames = User::whereIn('empcode', $registrations->pluck('reviewed_by')->filter()->unique())
            ->pluck('name', 'empcode');

        return $registrations->map(function (ProgramRegistration $registration) use ($listedEmpcodes, $accountEmpcodes, $emailOwners, $reviewerNames) {
            $empcode = Str::lower($registration->empcode);
            $emailOwner = $emailOwners[Str::lower($registration->email)] ?? null;

            $account = match ($registration->status) {
                ProgramRegistration::STATUS_PENDING => match (true) {
                    isset($accountEmpcodes[$empcode]) => 'existing',
                    $emailOwner !== null && $emailOwner !== $empcode => 'email_taken',
                    default => 'will_create',
                },
                ProgramRegistration::STATUS_APPROVED => $registration->account_created ? 'created' : 'existing',
                default => null,
            };

            return array_merge($registration->only([
                'id', 'source', 'status', 'empcode', 'lastname', 'firstname', 'mi', 'position', 'sg',
                'plantilla_status', 'sex', 'region', 'office', 'office_division', 'location', 'section', 'unit',
                'email', 'contact_number', 'custom_answers', 'remarks',
            ]), [
                'name' => $registration->fullName(),
                'submitted_at' => $registration->created_at?->format('M j, Y g:i A'),
                'reviewed_by_name' => $registration->reviewed_by ? ($reviewerNames[$registration->reviewed_by] ?? $registration->reviewed_by) : null,
                'reviewed_at' => $registration->reviewed_at?->format('M j, Y g:i A'),
                'in_employee_list' => isset($listedEmpcodes[$empcode]),
                'account' => $account,
                'can_resend_login' => $registration->status === ProgramRegistration::STATUS_APPROVED
                    && $registration->account_created
                    && $registration->user?->must_change_password === true,
                'holding_status' => $registration->provisionalEmployee?->status,
            ]);
        })->values()->all();
    }

    // POST /batches/{batch}/registration-form
    public function store(Request $request, Batch $batch): RedirectResponse
    {
        abort_unless(
            $batch->program?->canCreateRegistrationFormsBy($request->user()) === true,
            403,
            'Only the admin who created this program or a superadmin can set up its registration form.',
        );
        abort_if($batch->registrationForm()->exists(), 422, 'This batch already has a registration form.');

        $user = $request->user();
        $pax = (string) $batch->program?->pax;

        DB::transaction(function () use ($batch, $pax, $user) {
            $form = RegistrationForm::create([
                'batch_id' => $batch->id,
                'slug' => RegistrationForm::generateSlugFor($batch),
                // Start from the program's pax when it's a plain number; it can be changed anytime.
                'slot_limit' => ctype_digit($pax) && (int) $pax > 0 ? (int) $pax : null,
                'created_by_empcode' => $user?->empcode,
                'created_by_name' => $user?->name ?? 'System',
            ]);

            $form->seedDefaultQuestions();
        });

        return back()->with('success', FlashMessage::make(
            "Registration form created for {$batch->batch}",
            'It is saved and closed for now, so nobody can register yet. It already asks whether they are a Person With Disability.',
            'Customize it if you like, then switch it to "Accepting Registrations" and share the link.',
        ));
    }

    // PUT /registration-forms/{registrationForm}
    public function update(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationForm);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string', 'max:2000'],
            'theme' => ['required', Rule::in(RegistrationForm::THEMES)],
            'is_open' => ['required', 'boolean'],
            'closes_at' => ['nullable', 'date'],
            'slot_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'hide_background' => ['sometimes', 'boolean'],
        ]);

        $registrationForm->update($data);

        return back()->with('success', FlashMessage::make('Registration settings saved', 'The changes apply to the registration link right away.'));
    }

    // POST /registration-forms/{registrationForm}/background
    // A per-batch override of the site-wide default (site-images.registration_background).
    public function uploadBackground(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationForm);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $this->deleteBackgroundFile($registrationForm);

        $path = $request->file('image')->store('registration-backgrounds', 'public');
        // Uploading an image means they want it shown, even if "No background" was chosen before.
        $registrationForm->update(['background_image' => $path, 'hide_background' => false]);

        return back()->with('success', FlashMessage::make('Registration background updated', 'People will see the new image when they open the registration form.'));
    }

    // DELETE /registration-forms/{registrationForm}/background
    public function destroyBackground(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationForm);

        $this->deleteBackgroundFile($registrationForm);
        // Back to the site default, which also undoes "No background".
        $registrationForm->update(['background_image' => null, 'hide_background' => false]);

        return back()->with('success', FlashMessage::make('Registration background reset', 'The registration form now uses the site default image.'));
    }

    // ── Additional questions ──────────────────────────────────────────────────

    // POST /registration-forms/{registrationForm}/questions
    public function storeQuestion(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationForm);

        $data = $this->validateQuestion($request);
        $data['sort_order'] = (int) $registrationForm->questions()->max('sort_order') + 1;

        $registrationForm->questions()->create($data);

        return back()->with('success', FlashMessage::make('Question added', 'It now appears in the "Additional Questions" step of the form.'));
    }

    // PUT /registration-questions/{registrationQuestion}
    public function updateQuestion(Request $request, RegistrationQuestion $registrationQuestion): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationQuestion->form);

        $registrationQuestion->update($this->validateQuestion($request));

        return back()->with('success', FlashMessage::make('Question updated', 'Your changes are saved.'));
    }

    // DELETE /registration-questions/{registrationQuestion}
    public function destroyQuestion(Request $request, RegistrationQuestion $registrationQuestion): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationQuestion->form);

        $registrationQuestion->delete();

        return back()->with('success', FlashMessage::make('Question removed', 'Answers already submitted keep their copy of this question.'));
    }

    // POST /registration-questions/{registrationQuestion}/move-up
    public function moveQuestionUp(Request $request, RegistrationQuestion $registrationQuestion): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationQuestion->form);

        $registrationQuestion->moveUp();

        return back();
    }

    // POST /registration-questions/{registrationQuestion}/move-down
    public function moveQuestionDown(Request $request, RegistrationQuestion $registrationQuestion): RedirectResponse
    {
        $this->ensureCanEdit($request, $registrationQuestion->form);

        $registrationQuestion->moveDown();

        return back();
    }

    private function ensureCanEdit(Request $request, RegistrationForm $form): void
    {
        abort_unless(
            $form->canBeEditedBy($request->user()),
            403,
            'Only the owner of this registration form, its editors or a superadmin can change it.',
        );
    }

    /**
     * @return array{type: string, label: string, help_text: ?string, options: ?array<int, string>, allow_other: bool, is_required: bool}
     */
    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(RegistrationQuestion::TYPES)],
            'label' => ['required', 'string', 'max:500'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'options' => ['required_if:type,'.implode(',', RegistrationQuestion::CHOICE_TYPES), 'nullable', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:255', 'distinct:ignore_case', Rule::notIn([RegistrationQuestion::OTHER_VALUE])],
            'allow_other' => ['boolean'],
            'is_required' => ['boolean'],
        ], [
            'options.*.distinct' => 'Each option must be different.',
            'options.*.required' => 'Fill in this option or remove it.',
        ]);

        if (! in_array($data['type'], RegistrationQuestion::CHOICE_TYPES, true)) {
            $data['options'] = null;
        }

        $data['help_text'] ??= null;
        $data['allow_other'] = in_array($data['type'], RegistrationQuestion::OTHER_TYPES, true) && (bool) ($data['allow_other'] ?? false);
        $data['is_required'] = (bool) ($data['is_required'] ?? false);

        return $data;
    }

    private function deleteBackgroundFile(RegistrationForm $form): void
    {
        if ($form->background_image && Storage::disk('public')->exists($form->background_image)) {
            Storage::disk('public')->delete($form->background_image);
        }
    }
}
