<?php

namespace App\Http\Controllers;

use App\Models\ForeignSponsorConfig;
use App\Models\ForeignProgram;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ForeignNominationController extends Controller
{
    public function show(string $slug)
    {
        $config = ForeignSponsorConfig::where('slug', $slug)
            ->where('is_active', true)
            ->with(['requirements' => fn($q) => $q->orderBy('sort_order')])
            ->firstOrFail();
    
        $programsQuery = ForeignProgram::where('organizing_sponsor', $config->organizing_sponsor);
    
        // If admin selected specific programs, show only those
        if (!empty($config->selected_program_ids)) {
            $programsQuery->whereIn('id', $config->selected_program_ids);
        } else {
            // Otherwise show all non-concluded programs
            $programsQuery->whereNotIn('status', ['concluded', 'no_nominee']);
        }
    
        $programs = $programsQuery
            ->orderBy('program_start')
            ->get(['id', 'program_title', 'program_start', 'program_end', 'slots', 'modality']);
    
        return Inertia::render('ForeignPrograms/NominationForm', [
            'config'   => $config,
            'programs' => $programs,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $config = ForeignSponsorConfig::where('slug', $slug)
            ->where('is_active', true)
            ->with('requirements')
            ->firstOrFail();

        $request->validate([
            'foreign_program_id' => 'required|exists:foreign_programs,id',
            'firstname'          => 'required|string|max:255',
            'middle_name'        => 'nullable|string|max:255',
            'surname'            => 'required|string|max:255',
            'sex'                => 'required|in:male,female,other',
            'age'                => 'required|integer|min:18|max:100',
            'position'           => 'required|string|max:255',
            'agency'             => 'required|string|max:255',
            'contact_number'     => 'required|string|max:50',
            'email'              => 'required|email|max:255',
            'accomplished_form'  => 'nullable|file|mimes:pdf|max:10240',
        ]);

        // Validate requirement files
        foreach ($config->requirements as $req) {
            if ($req->file_required) {
                $request->validate([
                    "requirement_{$req->id}" => 'required|file|max:10240',
                ], [
                    "requirement_{$req->id}.required" => "The file for \"{$req->question}\" is required.",
                ]);
            }
        }

        // Upload accomplished form
        $accomplishedPath = null;
        if ($request->hasFile('accomplished_form')) {
            $accomplishedPath = $request->file('accomplished_form')
                ->store('nominees/accomplished', 'public');
        }

        // Create nominee
        $nominee = ForeignNominee::create([
            'foreign_program_id'        => $request->foreign_program_id,
            'foreign_sponsor_config_id' => $config->id,
            'firstname'                 => $request->firstname,
            'middle_name'               => $request->middle_name,
            'surname'                   => $request->surname,
            'sex'                       => $request->sex,
            'age'                       => $request->age,
            'position'                  => $request->position,
            'agency'                    => $request->agency,
            'contact_number'            => $request->contact_number,
            'email'                     => $request->email,
            'accomplished_form_path'    => $accomplishedPath,
            'status'                    => 'for_interview',
        ]);

        // Upload requirement files
        foreach ($config->requirements as $req) {
            $fileKey = "requirement_{$req->id}";
            if ($request->hasFile($fileKey)) {
                $path = $request->file($fileKey)
                    ->store("nominees/requirements/{$nominee->id}", 'public');

                ForeignNomineeSubmission::create([
                    'foreign_nominee_id'             => $nominee->id,
                    'foreign_nominee_requirement_id' => $req->id,
                    'file_path'                      => $path,
                ]);
            }
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