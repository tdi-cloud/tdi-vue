<?php

namespace App\Http\Controllers;

use App\Models\ProvisionalEmployee;
use App\Support\EmployeeFieldOptions;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\ProvisionalEmployeeSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin page for the holding list of employees added from approved
 * registrations, until HR's monthly employee list includes them.
 */
class ProvisionalEmployeeController extends Controller
{
    /**
     * Tables that point to a person by empcode, updated together when a
     * wrong employee code is corrected.
     *
     * @var list<string>
     */
    private const EMPCODE_TABLES = ['users', 'participants', 'certificates', 'pending_notifications', 'evaluation_responses', 'program_registrations'];

    // GET /employees-from-registration
    public function index(Request $request): Response
    {
        $showMerged = $request->string('status')->toString() === ProvisionalEmployee::STATUS_MERGED;
        $search = trim($request->string('search')->toString());

        $records = ProvisionalEmployee::query()
            ->when($showMerged, fn ($query) => $query->onlyTrashed())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('empcode', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with(['registration.batch.program', 'user:id,email'])
            ->orderByDesc($showMerged ? 'merged_at' : 'created_at')
            ->paginate(LiteMode::perPage($request, 15))
            ->withQueryString();

        $records->through(fn (ProvisionalEmployee $record) => array_merge($record->only([
            'id', 'empcode', 'lastname', 'firstname', 'mi', 'position', 'sg', 'plantilla_status', 'sex', 'region',
            'office', 'office_division', 'location', 'section', 'unit', 'email', 'contact_number', 'status',
        ]), [
            'name' => trim("{$record->firstname} {$record->lastname}"),
            'program_title' => $record->registration?->batch?->program?->title,
            'batch_label' => $record->registration?->batch?->batch,
            'has_account' => $record->user_id !== null,
            'in_employees_table' => $record->trashed() ? null : $record->insertedEmployeeRow() !== null,
            'added_at' => $record->created_at?->format('M j, Y'),
            'last_checked_at' => $record->last_checked_at?->format('M j, Y g:i A'),
            'merged_at' => $record->merged_at?->format('M j, Y g:i A'),
        ]));

        return Inertia::render('EmployeesFromRegistration/index', [
            'records' => $records,
            'filters' => ['search' => $search, 'status' => $showMerged ? ProvisionalEmployee::STATUS_MERGED : ProvisionalEmployee::STATUS_WAITING],
            'counts' => [
                'waiting' => ProvisionalEmployee::count(),
                'merged' => ProvisionalEmployee::onlyTrashed()->count(),
            ],
            'fieldOptions' => EmployeeFieldOptions::all(),
        ]);
    }

    // POST /employees-from-registration/sync
    public function sync(ProvisionalEmployeeSync $sync): RedirectResponse
    {
        $summary = $sync->run();

        return back()->with('success', FlashMessage::make(
            'Checked against the official employee list',
            sprintf(
                '%d found in the official list and removed from here, %d restored after the re-import, %d still waiting.',
                $summary[ProvisionalEmployeeSync::MERGED],
                $summary[ProvisionalEmployeeSync::RESTORED],
                $summary[ProvisionalEmployeeSync::WAITING],
            ),
        ));
    }

    // PUT /employees-from-registration/{provisionalEmployee}
    // Corrects the details, including a mistyped employee code, everywhere the person appears.
    public function update(Request $request, ProvisionalEmployee $provisionalEmployee, ProvisionalEmployeeSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'empcode' => ['required', 'string', 'max:50'],
            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'mi' => ['nullable', 'string', 'max:10'],
            'position' => ['required', 'string', 'max:255'],
            'sg' => ['nullable', 'string', 'max:20'],
            'plantilla_status' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'string', 'max:20'],
            'region' => ['required', 'string', 'max:255'],
            'office_division' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'regex:/^[0-9+()\-\s]{7,20}$/'],
        ], [], ['empcode' => 'employee code', 'office_division' => 'office/division', 'plantilla_status' => 'plantilla status']);

        $data = array_merge($data, EmployeeFieldOptions::detailsForDivision($data['region'], $data['office_division']));

        $oldEmpcode = $provisionalEmployee->empcode;
        $codeChanged = $data['empcode'] !== $oldEmpcode;

        if ($codeChanged) {
            $this->ensureCodeNotHeldByAnotherRegistrant($provisionalEmployee, $data['empcode']);
        }

        $result = DB::transaction(function () use ($provisionalEmployee, $data, $oldEmpcode, $codeChanged, $sync) {
            $appRow = $provisionalEmployee->insertedEmployeeRow();

            $provisionalEmployee->update($data);
            $appRow?->forceFill($provisionalEmployee->toEmployeeAttributes())->save();

            if ($codeChanged) {
                foreach (self::EMPCODE_TABLES as $table) {
                    DB::table($table)->where('empcode', $oldEmpcode)->update(['empcode' => $data['empcode']]);
                }
            }

            // The corrected code may already be in HR's list.
            return $sync->check($provisionalEmployee->refresh());
        });

        $name = trim("{$provisionalEmployee->firstname} {$provisionalEmployee->lastname}");

        if ($result === ProvisionalEmployeeSync::MERGED) {
            return back()->with('success', FlashMessage::make(
                "{$name} is in the official list",
                "The corrected employee code {$data['empcode']} is already in the official employee list, so they were taken off this list.",
            ));
        }

        return back()->with('success', FlashMessage::make(
            "{$name}'s details were updated",
            $codeChanged
                ? "The new employee code {$data['empcode']} now applies to their account, participations and registrations."
                : 'The change also applies to their row in the employees data.',
        ));
    }

    /**
     * The corrected code may be one that is in the official list (that's how
     * a typo gets fixed and merged), but not one another registrant or
     * another person's account already uses.
     */
    private function ensureCodeNotHeldByAnotherRegistrant(ProvisionalEmployee $record, string $empcode): void
    {
        $lower = Str::lower($empcode);

        $heldByOtherRegistrant = ProvisionalEmployee::withTrashed()
            ->whereKeyNot($record->id)
            ->whereRaw('LOWER(empcode) = ?', [$lower])
            ->exists();

        $heldByOtherAccount = DB::table('users')
            ->whereRaw('LOWER(empcode) = ?', [$lower])
            ->where('id', '!=', $record->user_id ?? 0)
            ->exists();

        if ($heldByOtherRegistrant || $heldByOtherAccount) {
            throw ValidationException::withMessages([
                'empcode' => 'Another person already uses this employee code. Check the code with HR before changing it.',
            ]);
        }
    }
}
