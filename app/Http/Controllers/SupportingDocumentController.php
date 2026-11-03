<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramSupportingDocument;
use App\Support\FlashMessage;
use App\Support\LiteMode;
use App\Support\SearchSuggester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportingDocumentController extends Controller
{
    /**
     * Registry page: view-only table ng lahat ng supporting documents
     * across all programs, with search + filters.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'document_type', 'document_series', 'origin']);

        $documents = ProgramSupportingDocument::query()
            ->with(['program:id,program_code,title,category,modality'])
            // SEARCH: doc number, subject, program code, origin, type
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('document_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('program_code', 'like', "%{$search}%")
                        ->orWhere('origin', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%");
                });
            })
            // FILTERS
            ->when($filters['document_type'] ?? null, fn ($q, $type) => $q->where('document_type', $type))
            ->when($filters['document_series'] ?? null, fn ($q, $year) => $q->where('document_series', $year))
            ->when($filters['origin'] ?? null, fn ($q, $origin) => $q->where('origin', $origin))
            // SORT: series year DESC, tapos document number DESC
            ->orderByDesc('document_series')
            ->orderByDesc('document_number')
            ->paginate(LiteMode::perPage($request, 15))
            ->withQueryString();

        return Inertia::render('supporting-documents/index', [
            'documents' => $documents,
            'filters' => $filters,
            'suggestion' => SearchSuggester::forEmptyResults(
                $filters['search'] ?? null,
                $documents->total(),
                fn () => ProgramSupportingDocument::query()->get(['subject', 'document_type', 'origin', 'document_number', 'program_code'])
                    ->flatMap(fn (ProgramSupportingDocument $document) => [$document->subject, $document->document_type, $document->origin, $document->program_code]),
            ),

            // Lookups para sa filter dropdowns
            'documentTypes' => ProgramSupportingDocument::query()
                ->distinct()->orderBy('document_type')->pluck('document_type'),
            'seriesYears' => ProgramSupportingDocument::query()
                ->distinct()->orderByDesc('document_series')->pluck('document_series'),
            'origins' => ProgramSupportingDocument::query()
                ->whereNotNull('origin')->where('origin', '!=', '')
                ->distinct()->orderBy('origin')->pluck('origin'),

            // Stats para sa cards sa taas ng page
            'stats' => [
                'total' => ProgramSupportingDocument::count(),
                'thisYear' => ProgramSupportingDocument::where('document_series', now()->year)->count(),
                'types' => ProgramSupportingDocument::distinct()->count('document_type'),
                'withLinks' => ProgramSupportingDocument::whereNotNull('link')->where('link', '!=', '')->count(),
            ],
        ]);
    }

    /**
     * Store a new supporting document under a program.
     */
    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'document_type' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'document_series' => 'required|integer|min:1900|max:2100',
            'origin' => 'nullable|string|max:255',
            'document_number' => 'required|string|max:255',
            'date_issued' => 'nullable|date',
            'link' => 'nullable|string|max:2048|url',
        ]);

        $document = $program->supportingDocuments()->create(array_merge($validated, [
            'program_code' => $program->program_code,
        ]));

        return back()->with('success', FlashMessage::make(
            'Supporting document added',
            "{$document->document_type} No. {$document->document_number} is attached to \"{$program->title}\".",
        ));
    }

    /**
     * Update an existing supporting document.
     */
    public function update(Request $request, Program $program, ProgramSupportingDocument $supportingDocument)
    {
        abort_unless($supportingDocument->program_id === $program->id, 404);

        $validated = $request->validate([
            'document_type' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'document_series' => 'required|integer|min:1900|max:2100',
            'origin' => 'nullable|string|max:255',
            'document_number' => 'required|string|max:255',
            'date_issued' => 'nullable|date',
            'link' => 'nullable|string|max:2048|url',
        ]);

        $supportingDocument->update($validated);

        return back()->with('success', FlashMessage::make('Supporting document updated', "Your changes to {$supportingDocument->document_type} No. {$supportingDocument->document_number} are saved."));
    }

    /**
     * Delete a supporting document.
     */
    public function destroy(Program $program, ProgramSupportingDocument $supportingDocument)
    {
        abort_unless($supportingDocument->program_id === $program->id, 404);

        $supportingDocument->delete();

        return back()->with('success', FlashMessage::make('Supporting document deleted', "{$supportingDocument->document_type} No. {$supportingDocument->document_number} was removed from this program."));
    }
}
