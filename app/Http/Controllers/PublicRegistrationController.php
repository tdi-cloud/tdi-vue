<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\ProgramRegistration;
use App\Models\RegistrationForm;
use App\Models\RegistrationQuestion;
use App\Models\User;
use App\Notifications\RegistrationSubmitted;
use App\Support\EmployeeFieldOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public (no login) registration form people fill in to join a batch.
 * Registrations wait as "pending" until the program's manager reviews them.
 */
class PublicRegistrationController extends Controller
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_SEARCH_RESULTS = 10;

    // GET /join/{slug}
    public function show(Request $request, string $slug): Response
    {
        $form = $this->findForm($slug);
        $closedReason = $form->closedReason();
        $isPreview = $this->isManagerPreview($request, $form);

        if ($closedReason !== null && ! $isPreview) {
            return Inertia::render('Registration/Closed', [
                'form' => $this->publicFormData($form),
                'reason' => $closedReason,
            ]);
        }

        return Inertia::render('Registration/Form', [
            'form' => $this->publicFormData($form),
            'questions' => $form->questions->map(fn (RegistrationQuestion $question) => [
                'id' => $question->id,
                'label' => $question->label,
                'help_text' => $question->help_text,
                'type' => $question->type,
                'options' => $question->options,
                'allow_other' => $question->allowsOther(),
                'is_required' => $question->is_required,
            ])->values(),
            'fieldOptions' => EmployeeFieldOptions::all(),
            'backgroundUrl' => $form->publicBackgroundUrl(),
            'preview' => $isPreview ? ['closedReason' => $closedReason] : null,
        ]);
    }

    // GET /join/{slug}/search?q=
    // Returns only the code, name and office/division, never the full employee record.
    public function search(Request $request, string $slug): JsonResponse
    {
        $form = $this->findForm($slug);

        abort_if($form->closedReason() !== null && ! $this->isManagerPreview($request, $form), 404);

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
            ->get(['id', 'EMPCODE', 'LASTNAME', 'FIRSTNAME', 'MI', 'OFFICE/DIVISION']);

        return response()->json($employees->map(fn (Employee $employee) => [
            'empcode' => $employee->EMPCODE,
            'name' => $employee->name,
            'office_division' => $employee->getAttribute('OFFICE/DIVISION'),
        ])->values());
    }

    // POST /join/{slug}
    public function submit(Request $request, string $slug): RedirectResponse
    {
        $form = $this->findForm($slug);

        // Honeypot: the field is hidden from people, so only bots fill it in.
        // Pretend it worked and save nothing.
        if ($request->filled('website')) {
            return redirect()->route('join.success', $slug);
        }

        if ($form->closedReason() !== null) {
            return redirect()->route('join.show', $slug);
        }

        $isManual = $request->input('source') === ProgramRegistration::SOURCE_MANUAL;
        [$rules, $messages, $attributes] = $this->rules($form, $request);
        $data = $request->validate($rules, $messages, $attributes);

        $snapshot = $isManual
            ? $this->manualSnapshot($data)
            : ProgramRegistration::snapshotFromEmployee($this->findListedEmployee($data['empcode']));

        $this->ensureNotAlreadyRegistered($form->batch, $snapshot['empcode']);

        $registered = DB::transaction(function () use ($form, $data, $snapshot, $isManual, $request) {
            // Lock the form so two people can't both take the last slot.
            $lockedForm = RegistrationForm::whereKey($form->id)->lockForUpdate()->first();

            if ($lockedForm->isFull()) {
                return false;
            }

            return $lockedForm->registrations()->create(array_merge($snapshot, [
                'batch_id' => $lockedForm->batch_id,
                'source' => $isManual ? ProgramRegistration::SOURCE_MANUAL : ProgramRegistration::SOURCE_LISTED,
                'email' => Str::lower($data['email']),
                'contact_number' => $data['contact_number'],
                'custom_answers' => $this->customAnswers($form, $data['answers'] ?? [], $data['answers_other'] ?? []),
                'data_privacy_consent_at' => now(),
                'status' => ProgramRegistration::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]));
        });

        if ($registered === false) {
            return redirect()->route('join.show', $slug);
        }

        $this->notifyManagers($form, $registered);

        return redirect()->route('join.success', $slug);
    }

    // GET /join/{slug}/success
    public function success(string $slug): Response
    {
        return Inertia::render('Registration/Success', [
            'form' => $this->publicFormData($this->findForm($slug)),
        ]);
    }

    /**
     * Lets the program's creator and every superadmin know there's a
     * registration to review.
     */
    private function notifyManagers(RegistrationForm $form, ProgramRegistration $registration): void
    {
        $creatorEmpcode = $form->batch->program?->added_by;

        $recipients = User::query()
            ->where('access', 'superadmin')
            ->when($creatorEmpcode, fn ($query) => $query->orWhere(fn ($q) => $q->where('empcode', $creatorEmpcode)->whereIn('access', ['admin', 'superadmin'])))
            ->get();

        $registration->setRelation('batch', $form->batch);

        foreach ($recipients as $recipient) {
            $recipient->notify(new RegistrationSubmitted($registration));
        }
    }

    private function findForm(string $slug): RegistrationForm
    {
        return RegistrationForm::where('slug', $slug)
            ->with(['batch.program.coverPage', 'questions'])
            ->firstOrFail();
    }

    /**
     * Managers can open a closed form with ?preview=1 to check how it looks.
     */
    private function isManagerPreview(Request $request, RegistrationForm $form): bool
    {
        $user = $request->user();

        return $request->boolean('preview')
            && $user !== null
            && $form->batch->program?->canManageRegistrationsBy($user) === true;
    }

    /**
     * Only what the public page needs to show, nothing internal.
     *
     * @return array<string, mixed>
     */
    private function publicFormData(RegistrationForm $form): array
    {
        $batch = $form->batch;
        $program = $batch->program;

        return [
            'slug' => $form->slug,
            'title' => $form->title,
            'intro_text' => $form->intro_text,
            'theme' => $form->theme,
            'closes_at_label' => $form->closes_at?->format('M j, Y g:i A'),
            'batch' => [
                'batch' => $batch->batch,
                'date_start' => $batch->date_start,
                'date_end' => $batch->date_end,
                'time_start' => $batch->time_start,
                'time_end' => $batch->time_end,
                'modality' => $batch->modality,
                'venue' => $batch->venue,
            ],
            'program' => [
                'title' => $program?->title,
                'cover_url' => $program?->coverPage?->image_url,
            ],
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     */
    private function rules(RegistrationForm $form, Request $request): array
    {
        $rules = [
            'source' => ['required', Rule::in([ProgramRegistration::SOURCE_LISTED, ProgramRegistration::SOURCE_MANUAL])],
            'empcode' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'data_privacy_consent' => ['accepted'],
            'answers' => ['nullable', 'array'],
            'answers_other' => ['nullable', 'array'],
        ];

        $messages = [];

        $attributes = [
            'empcode' => 'employee code (EMPCODE)',
            'contact_number' => 'contact number',
            'data_privacy_consent' => 'data privacy consent',
        ];

        if ($request->input('source') === ProgramRegistration::SOURCE_MANUAL) {
            $options = EmployeeFieldOptions::all();
            $choice = fn (array $allowed) => $allowed === [] ? [] : [Rule::in($allowed)];
            $regionDivisions = EmployeeFieldOptions::officeDivisionsFor($request->string('region')->toString());

            $rules += [
                'lastname' => ['required', 'string', 'max:255'],
                'firstname' => ['required', 'string', 'max:255'],
                'mi' => ['nullable', 'string', 'max:10'],
                'position' => ['required', 'string', 'max:255'],
                'sg' => ['nullable', 'string', 'max:20'],
                'plantilla_status' => ['required', 'string', 'max:255', ...$choice($options['plantilla_status'])],
                'sex' => ['required', 'string', 'max:20', ...$choice($options['sex'])],
                'region' => ['required', 'string', 'max:255', ...$choice($options['region'])],
                // Only the office/divisions that belong to the chosen region.
                'office_division' => ['required', 'string', 'max:255', ...($regionDivisions === null ? [] : [Rule::in($regionDivisions)])],
            ];

            $attributes += [
                'lastname' => 'last name',
                'firstname' => 'first name',
                'mi' => 'middle initial',
                'sg' => 'salary grade',
                'plantilla_status' => 'plantilla status',
                'office_division' => 'office/division',
            ];
        }

        foreach ($form->questions as $question) {
            $key = "answers.{$question->id}";
            $presence = $question->is_required ? 'required' : 'nullable';
            $attributes[$key] = Str::limit($question->label, 60);
            $choices = $question->options ?? [];

            if ($question->allowsOther()) {
                // "Other" is sent as a marker value, with the typed answer next to it.
                $choices[] = RegistrationQuestion::OTHER_VALUE;
                $otherKey = "answers_other.{$question->id}";
                $pickedOther = in_array(RegistrationQuestion::OTHER_VALUE, (array) $request->input($key), true);

                $rules[$otherKey] = [Rule::requiredIf($pickedOther), 'nullable', 'string', 'max:255'];
                $messages["{$otherKey}.required"] = 'Please type your answer for "Other".';
            }

            if ($question->type === RegistrationQuestion::TYPE_CHECKBOX) {
                $rules[$key] = [$presence, 'array'];
                $rules["{$key}.*"] = ['string', Rule::in($choices)];
            } elseif ($question->hasChoices()) {
                $rules[$key] = [$presence, 'string', Rule::in($choices)];
            } else {
                $max = $question->type === RegistrationQuestion::TYPE_TEXTAREA ? 2000 : 255;
                $rules[$key] = [$presence, 'string', "max:{$max}"];
            }
        }

        return [$rules, $messages, $attributes];
    }

    private function findListedEmployee(string $empcode): Employee
    {
        $employee = Employee::whereRaw('LOWER(EMPCODE) = ?', [Str::lower($empcode)])->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'empcode' => "We couldn't find that employee in the list. Search for your name again, or choose \"I'm not in the list\".",
            ]);
        }

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function manualSnapshot(array $data): array
    {
        $exists = Employee::whereRaw('LOWER(EMPCODE) = ?', [Str::lower($data['empcode'])])->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'empcode' => 'This employee code is already in the employee list. Go back to the first step and search for your name instead.',
            ]);
        }

        $snapshot = [];

        foreach (array_keys(ProgramRegistration::EMPLOYEE_COLUMNS) as $column) {
            $value = $data[$column] ?? null;
            $snapshot[$column] = $value === null ? null : trim((string) $value);
        }

        // Only the region and office/division are asked; office, location,
        // section and unit follow from them.
        return array_merge($snapshot, EmployeeFieldOptions::detailsForDivision($snapshot['region'], $snapshot['office_division']));
    }

    /**
     * One registration per person per program: blocks people who are already
     * participants, or already have a pending/approved registration, in any
     * batch of the same program.
     */
    private function ensureNotAlreadyRegistered(Batch $batch, string $empcode): void
    {
        $programBatchIds = Batch::where('program_code', $batch->program_code)->pluck('id');
        $empcodeLower = Str::lower($empcode);

        $isParticipant = Participant::whereIn('batch_id', $programBatchIds)
            ->whereRaw('LOWER(empcode) = ?', [$empcodeLower])
            ->exists();

        if ($isParticipant) {
            throw ValidationException::withMessages([
                'empcode' => 'You are already a participant of this program, so there is no need to register again.',
            ]);
        }

        $hasRegistration = ProgramRegistration::whereIn('batch_id', $programBatchIds)
            ->whereRaw('LOWER(empcode) = ?', [$empcodeLower])
            ->holdingSlot()
            ->exists();

        if ($hasRegistration) {
            throw ValidationException::withMessages([
                'empcode' => 'You have already registered for this program. Please wait for the organizer to review your registration.',
            ]);
        }
    }

    /**
     * Each answer keeps its question's label and type, so it still reads
     * correctly if the question is later edited or deleted.
     *
     * A picked "Other" is saved as "Other: <what they typed>".
     *
     * @param  array<int|string, mixed>  $answers
     * @param  array<int|string, mixed>  $otherAnswers
     * @return list<array{question_id: int, label: string, type: string, value: mixed}>
     */
    private function customAnswers(RegistrationForm $form, array $answers, array $otherAnswers): array
    {
        return $form->questions->map(function (RegistrationQuestion $question) use ($answers, $otherAnswers) {
            $value = $answers[$question->id] ?? null;
            $otherText = 'Other: '.trim((string) ($otherAnswers[$question->id] ?? ''));
            $resolve = fn ($choice) => $choice === RegistrationQuestion::OTHER_VALUE && $question->allowsOther() ? $otherText : $choice;

            if (is_array($value)) {
                $value = array_values(array_map($resolve, $value));
            } elseif ($value !== null) {
                $value = $resolve($value);
            }

            return [
                'question_id' => $question->id,
                'label' => $question->label,
                'type' => $question->type,
                'value' => $value === '' ? null : $value,
            ];
        })->values()->all();
    }
}
