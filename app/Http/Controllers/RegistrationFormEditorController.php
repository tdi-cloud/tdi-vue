<?php

namespace App\Http\Controllers;

use App\Models\RegistrationForm;
use App\Models\RegistrationFormEditor;
use App\Models\User;
use App\Support\FlashMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The admins, besides the owner, who can change a registration form. Only the
 * form's owner or a superadmin can add or remove them.
 */
class RegistrationFormEditorController extends Controller
{
    public const MIN_SEARCH_LENGTH = 2;

    public const MAX_SEARCH_RESULTS = 10;

    // GET /registration-forms/{registrationForm}/editors/search?q=
    // Admins by name or employee code, each with the reason they can't be added (if any).
    public function search(Request $request, RegistrationForm $registrationForm): JsonResponse
    {
        $this->ensureCanManageEditors($request, $registrationForm);

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return response()->json([]);
        }

        $editorIds = $registrationForm->editors()->pluck('user_id')->flip();

        $admins = User::query()
            ->whereIn('access', ['admin', 'superadmin'])
            ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('empcode', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get(['id', 'name', 'empcode', 'access']);

        return response()->json($admins->map(fn (User $admin) => [
            'id' => $admin->id,
            'name' => $admin->name,
            'empcode' => $admin->empcode,
            'unavailable' => match (true) {
                $registrationForm->isOwnedBy($admin) => 'Owner',
                $admin->isSuperAdmin() => 'Superadmin',
                isset($editorIds[$admin->id]) => 'Already an editor',
                default => null,
            },
        ])->values());
    }

    // POST /registration-forms/{registrationForm}/editors
    public function store(Request $request, RegistrationForm $registrationForm): RedirectResponse
    {
        $this->ensureCanManageEditors($request, $registrationForm);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [], ['user_id' => 'editor']);

        $editor = User::findOrFail($data['user_id']);
        $this->ensureCanBeAdded($registrationForm, $editor);

        $registrationForm->editors()->create([
            'user_id' => $editor->id,
            'added_by_empcode' => $request->user()->empcode,
            'added_by_name' => $request->user()->name,
        ]);

        return back()->with('success', FlashMessage::make(
            "{$editor->name} can now edit this form",
            'They can change its settings and questions, and approve or reject registrations.',
        ));
    }

    // DELETE /registration-form-editors/{registrationFormEditor}
    public function destroy(Request $request, RegistrationFormEditor $registrationFormEditor): RedirectResponse
    {
        $this->ensureCanManageEditors($request, $registrationFormEditor->form);

        $name = $registrationFormEditor->user->name;
        $registrationFormEditor->delete();

        return back()->with('success', FlashMessage::make(
            "{$name} is no longer an editor",
            'They can still view this form, but can no longer change it or review its registrations.',
        ));
    }

    private function ensureCanManageEditors(Request $request, RegistrationForm $form): void
    {
        abort_unless(
            $form->canManageEditorsBy($request->user()),
            403,
            'Only the owner of this registration form or a superadmin can choose who else can edit it.',
        );
    }

    /**
     * Editors must be admins (the registration pages are admin-only), and
     * never someone who can already edit the form.
     */
    private function ensureCanBeAdded(RegistrationForm $form, User $user): void
    {
        $problem = match (true) {
            ! $user->isAdmin() => "{$user->name} doesn't have admin access. Only admins can be editors.",
            $form->isOwnedBy($user) => "{$user->name} owns this form and can already edit it.",
            $user->isSuperAdmin() => "{$user->name} is a superadmin and can already edit every registration form.",
            $form->editors()->where('user_id', $user->id)->exists() => "{$user->name} is already an editor of this form.",
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['user_id' => $problem]);
        }
    }
}
