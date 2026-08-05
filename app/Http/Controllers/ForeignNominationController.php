<?php

namespace App\Http\Controllers;

use App\Mail\NewNomineeNotification;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeSubmission;
use App\Models\ForeignProgram;
use App\Models\ForeignSponsorConfig;
use App\Models\SiteImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ForeignNominationController extends Controller
{
    public function show(string $slug)
    {
        $config = ForeignSponsorConfig::where('slug', $slug)
            ->where('is_active', true)
            ->with(['requirements' => fn ($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        $programsQuery = ForeignProgram::where('organizing_sponsor', $config->organizing_sponsor);

        // If admin selected specific programs, show only those
        if (! empty($config->selected_program_ids)) {
            $programsQuery->whereIn('id', $config->selected_program_ids);
        } else {
            // Otherwise show all non-concluded programs
            $programsQuery->whereNotIn('status', ['concluded', 'no_nominee']);
        }

        $programsQuery->where(function ($q) {
            $q->whereNull('submission_date')
                ->orWhereDate('submission_date', '>=', now()->toDateString());
        });

        $programs = $programsQuery
            ->orderBy('program_start')
            ->get(['id', 'program_title', 'program_start', 'program_end', 'slots', 'modality']);

        $sponsorLogos = collect(SiteImage::resolvedUrls())
            ->filter(fn ($url, $key) => str_starts_with($key, 'sponsor_logo_'))
            ->mapWithKeys(fn ($url, $key) => [str_replace('sponsor_logo_', '', $key) => $url])
            ->all();

        return Inertia::render('ForeignPrograms/NominationForm', [
            'config' => $config,
            'programs' => $programs,
            'sponsorLogos' => $sponsorLogos,
        ]);
    }

    // GET /nominate/{slug}/check-email — lets the form warn the applicant
    // before they reach the final step that their email was already used.
    public function checkEmail(Request $request, string $slug)
    {
        ForeignSponsorConfig::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'email' => 'required|email',
            'foreign_program_id' => 'required|exists:foreign_programs,id',
        ]);

        $alreadySubmitted = ForeignNominee::where('foreign_program_id', $data['foreign_program_id'])
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->exists();

        return response()->json(['already_submitted' => $alreadySubmitted]);
    }

    public function submit(Request $request, string $slug)
    {
        $config = ForeignSponsorConfig::where('slug', $slug)
            ->where('is_active', true)
            ->with('requirements')
            ->firstOrFail();

        $request->validate([
            'foreign_program_id' => 'required|exists:foreign_programs,id',
            'firstname' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname' => 'required|string|max:255',
            'sex' => 'required|in:male,female,other',
            'age' => 'required|integer|min:18|max:100',
            'position' => 'required|string|max:255',
            'agency' => 'required|string|max:255',
            'contact_number' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'accomplished_form' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $program = ForeignProgram::findOrFail($request->foreign_program_id);
        if (
            $program->submission_date
            && $program->submission_date->lt(now()->startOfDay())
        ) {
            return back()
                ->withErrors([
                    'foreign_program_id' => 'The submission period for this program has already ended.',
                ])
                ->withInput();
        }

        $alreadySubmitted = ForeignNominee::where('foreign_program_id', $request->foreign_program_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($request->email)])
            ->exists();

        if ($alreadySubmitted) {
            return back()
                ->withErrors([
                    'email' => 'This email has already been used to submit a nomination for this program.',
                ])
                ->withInput();
        }

        foreach ($config->requirements as $req) {
            if ($req->file_required) {
                $request->validate([
                    "requirement_{$req->id}" => 'required|file|max:10240',
                ], [
                    "requirement_{$req->id}.required" => "The file for \"{$req->question}\" is required.",
                ]);
            }
        }

        $accomplishedPath = null;
        if ($request->hasFile('accomplished_form')) {
            $accomplishedPath = $request->file('accomplished_form')
                ->store('nominees/accomplished', 'public');
        }

        $nominee = ForeignNominee::create([
            'foreign_program_id' => $request->foreign_program_id,
            'foreign_sponsor_config_id' => $config->id,
            'firstname' => $request->firstname,
            'middle_name' => $request->middle_name,
            'surname' => $request->surname,
            'sex' => $request->sex,
            'age' => $request->age,
            'position' => $request->position,
            'agency' => $request->agency,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'accomplished_form_path' => $accomplishedPath,
            'status' => 'for_interview',
        ]);

        foreach ($config->requirements as $req) {
            $fileKey = "requirement_{$req->id}";
            if ($request->hasFile($fileKey)) {
                $path = $request->file($fileKey)
                    ->store("nominees/requirements/{$nominee->id}", 'public');

                ForeignNomineeSubmission::create([
                    'foreign_nominee_id' => $nominee->id,
                    'foreign_nominee_requirement_id' => $req->id,
                    'file_path' => $path,
                ]);
            }
        }

        try {
            $recipients = array_filter(array_map(
                'trim',
                explode(',', config('mail.nomination_notify') ?? '')
            ));

            if (! empty($recipients)) {
                Mail::to($recipients)
                    ->send(new NewNomineeNotification(
                        $nominee->load(['program', 'sponsorConfig'])
                    ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send nominee notification email', [
                'nominee_id' => $nominee->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('nominate.success', $slug);
    }

    public function success(string $slug)
    {
        $config = ForeignSponsorConfig::where('slug', $slug)->firstOrFail();

        return Inertia::render('ForeignPrograms/NominationSuccess', [
            'config' => $config,
        ]);
    }
}
