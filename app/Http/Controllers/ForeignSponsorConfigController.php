<?php

namespace App\Http\Controllers;

use App\Models\ForeignNominee;
use App\Models\ForeignNomineeRequirement;
use App\Models\ForeignNomineeSubmission;
use App\Models\ForeignSponsorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ForeignSponsorConfigController extends Controller
{
    // GET /foreign-sponsor-configs
    public function index()
    {
        $configs = ForeignSponsorConfig::withCount('requirements', 'nominees')
            ->orderBy('organizing_sponsor')
            ->get();

        return response()->json($configs);
    }

    // GET /foreign-sponsor-configs/{config}
    public function show(ForeignSponsorConfig $config)
    {
        return response()->json(
            $config->load(['requirements' => fn ($q) => $q->orderBy('sort_order')])
        );
    }

    // POST /foreign-sponsor-configs
    public function store(Request $request)
    {
        $data = $request->validate([
            'organizing_sponsor' => 'required|string|max:255',
            'form_title' => 'required|string|max:255',
            'is_active' => 'boolean',
            'accomplished_form_note' => 'nullable|string',
        ]);

        $data['slug'] = Str::slug($data['organizing_sponsor']);

        $config = ForeignSponsorConfig::create($data);

        return response()->json($config->load('requirements'));
    }

    // PUT /foreign-sponsor-configs/{config}
    public function update(Request $request, ForeignSponsorConfig $config)
    {
        $data = $request->validate([
            'form_title' => 'required|string|max:255',
            'is_active' => 'boolean',
            'accomplished_form_note' => 'nullable|string',
            'available_courses' => 'nullable|array',
            'available_courses.*.title' => 'required|string|max:255',
            'available_courses.*.url' => 'required|url',
            'selected_program_ids' => 'nullable|array',   // ← IDAGDAG
            'selected_program_ids.*' => 'integer',          // ← IDAGDAG
        ]);

        $config->update($data);

        return response()->json($config->fresh()->load('requirements'));
    }

    // ── Requirements ──────────────────────────────────────────────────────────

    // POST /foreign-sponsor-configs/{config}/requirements
    public function storeRequirement(Request $request, ForeignSponsorConfig $config)
    {
        $data = $request->validate([
            'question' => 'required|string|max:255',
            'description' => 'nullable|string',
            'link' => 'nullable|url',
            'file_required' => 'boolean',
        ]);

        $data['sort_order'] = $config->requirements()->max('sort_order') + 1;

        $req = $config->requirements()->create($data);

        return response()->json($req);
    }

    // PUT /foreign-nominee-requirements/{requirement}
    public function updateRequirement(Request $request, ForeignNomineeRequirement $requirement)
    {
        $data = $request->validate([
            'question' => 'required|string|max:255',
            'description' => 'nullable|string',
            'link' => 'nullable|url',
            'file_required' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $requirement->update($data);

        return response()->json($requirement);
    }

    // DELETE /foreign-nominee-requirements/{requirement}
    public function destroyRequirement(ForeignNomineeRequirement $requirement)
    {
        $requirement->delete();

        return response()->json(['success' => true]);
    }

    // ── Nominees ──────────────────────────────────────────────────────────────

    // PATCH /foreign-nominees/{nominee}/status
    public function updateNomineeStatus(Request $request, ForeignNominee $nominee)
    {
        $data = $request->validate([
            'status' => 'required|in:for_interview,endorsed,waiting_result,not_endorsed,accepted,regret,cancelled',
        ]);

        $nominee->update($data);

        return response()->json($nominee->fresh()->load('program'));
    }

    // ── Submissions ───────────────────────────────────────────────────────────

    // POST /foreign-nominee-submissions/{submission}/replace
    public function replaceSubmission(Request $request, ForeignNomineeSubmission $submission)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $path = $request->file('file')->store("nominees/requirements/{$submission->foreign_nominee_id}", 'public');

        $submission->update(['file_path' => $path]);

        return response()->json($submission->fresh());
    }

    // POST /foreign-nominees/{nominee}/accomplished-form/replace
    public function replaceAccomplishedForm(Request $request, ForeignNominee $nominee)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        if ($nominee->accomplished_form_path) {
            Storage::disk('public')->delete($nominee->accomplished_form_path);
        }

        $path = $request->file('file')->store('nominees/accomplished', 'public');

        $nominee->update(['accomplished_form_path' => $path]);

        return response()->json($nominee->fresh());
    }
}
