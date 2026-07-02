<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\TesdaOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TesdaOrderController extends Controller
{
    public function index(Program $program)
    {
        return response()->json(
            $program->tesdaOrders()->latest()->get()
        );
    }

    /**
     * I-return ang batches + participants ng program para sa preview/reorder sa modal.
     */
    public function participants(Program $program)
    {
        $batches = $program->batches()
            ->with(['participants.employee'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(
            $batches->map(fn ($batch) => [
                'id'           => $batch->id,
                'batch'        => $batch->batch,
                'participants' => $batch->participants
                    ->filter(fn ($p) => $p->employee)
                    ->map(fn ($p) => [
                        'id'       => $p->id,
                        'office'   => $p->employee->{'OFFICE/DIVISION'} ?? '',
                        'name'     => $p->employee->name,
                        'position' => $p->employee->POSITION ?? '',
                    ])
                    ->values(),
            ])
        );
    }

    public function store(Request $request, Program $program)
    {
        $data = $request->validate([
            'subject'               => 'required|string|max:5000',
            'date_issued'           => 'nullable|date',
            'effectivity'           => 'nullable|string',
            'supersedes'            => 'nullable|string',
            'series_year'           => 'nullable|integer|min:2000|max:2099',
            'body'                  => 'required|string',
            'include_participants'  => 'boolean',
            'include_batch_data'    => 'boolean',
            'closure'               => 'required|string',
            'signatory_empcode'     => 'nullable|string',
            'signatory_name'        => 'required|string',
            'signatory_position'    => 'required|string',
            'ordered_batches'       => 'nullable|string', // JSON — custom ordering mula sa modal
        ]);

        $data['program_id']   = $program->id;
        $data['effectivity']  = $data['effectivity'] ?: 'As indicated';
        $data['series_year']  = !empty($data['series_year']) ? (int) $data['series_year'] : now()->year;
        $data['generated_by'] = auth()->user()->name ?? 'system';

        // ── Auto-map signatory position ────────────────────────────────────────
        $data['signatory_position'] = $this->mapSignatoryPosition(
            $data['signatory_position'],
            $data['signatory_empcode'] ?? null
        );

        // Load participants — kung may custom ordering mula sa modal, gamitin iyon
        $participants = [];
        if (!empty($data['include_participants'])) {

            if (!empty($data['ordered_batches'])) {
                // Gamitin ang custom ordering na ginawa ng user sa modal
                $orderedBatches = json_decode($data['ordered_batches'], true) ?? [];
                foreach ($orderedBatches as $batch) {
                    $rows = collect($batch['participants'])->map(fn ($p) => [
                        'office'   => $p['office'],
                        'name'     => $p['name'],
                        'position' => $p['position'],
                    ])->values();

                    $participants[] = [
                        'batch_label' => $data['include_batch_data'] ? ($batch['batch'] ?? null) : null,
                        'rows'        => $rows,
                    ];
                }
            } else {
                // Fallback: default DB ordering
                $batches = $program->batches()
                    ->with(['participants.employee'])
                    ->orderBy('sort_order')
                    ->get();

                foreach ($batches as $batch) {
                    $rows = $batch->participants
                        ->filter(fn ($p) => $p->employee)
                        ->map(fn ($p) => [
                            'office'   => $p->employee->{'OFFICE/DIVISION'} ?? '',
                            'name'     => $p->employee->name,
                            'position' => $p->employee->POSITION ?? '',
                        ])
                        ->sortBy('office')
                        ->values();

                    $participants[] = [
                        'batch_label' => $data['include_batch_data'] ? $batch->batch : null,
                        'rows'        => $rows,
                    ];
                }
            }
        }

        $hasDirector = collect($participants)
            ->flatMap(fn ($b) => $b['rows'])
            ->contains(fn ($r) => str_contains(strtolower($r['position']), 'director'));

        $data['body']    = $this->sanitizeRichText($data['body']);
        $data['closure'] = $this->sanitizeRichText($data['closure']);

        // Huwag i-save ang ordered_batches sa DB (hindi ito column)
        unset($data['ordered_batches']);

        $tesdaOrder = TesdaOrder::create($data);

        $pdf = Pdf::loadView('tesda-orders.pdf', [
            'order'        => $tesdaOrder,
            'program'      => $program,
            'participants' => $participants,
            'hasDirector'  => $hasDirector,
        ])->setPaper('a4', 'portrait');

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(394, 81, 'Page {PAGE_NUM} of {PAGE_COUNT} page/s', null, 11, [0, 0, 0]);

        $pageCount = $canvas->get_page_count();

        $filename = "tesda-orders/{$program->program_code}_TO_{$tesdaOrder->id}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        $tesdaOrder->update([
            'total_pages' => $pageCount,
            'pdf_path'    => $filename,
        ]);

        return back()->with([
            'success'      => 'TESDA Order generated successfully.',
            'new_order_id' => $tesdaOrder->id,
        ]);
    }

    public function download(TesdaOrder $tesdaOrder)
    {
        abort_unless($tesdaOrder->pdf_path, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($tesdaOrder->pdf_path), 404);

        $tesdaOrder->load('program');
        $programTitle = $tesdaOrder->program->title ?? 'Program';
        $safeName     = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $programTitle);
        $safeName     = trim(preg_replace('/\s+/', '_', $safeName));
        $safeName     = substr($safeName, 0, 80);
        $pdfFilename  = "TESDA_Order_{$safeName}.pdf";

        return new StreamedResponse(function () use ($disk, $tesdaOrder) {
            $stream = $disk->readStream($tesdaOrder->pdf_path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdfFilename . '"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function destroy(TesdaOrder $tesdaOrder)
    {
        if ($tesdaOrder->pdf_path) {
            Storage::disk('public')->delete($tesdaOrder->pdf_path);
        }
        $tesdaOrder->delete();

        return back()->with('success', 'TESDA Order deleted.');
    }

    public function searchSignatory(Request $request)
    {
        $q = $request->query('q');

        $employees = \App\Models\Employee::query()
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('LASTNAME', 'LIKE', "%{$q}%")
                  ->orWhere('FIRSTNAME', 'LIKE', "%{$q}%")
                  ->orWhere('EMPCODE', 'LIKE', "%{$q}%");
            }))
            ->limit(10)
            ->get();

        return response()->json(
            $employees->map(fn ($e) => [
                'empcode'  => $e->EMPCODE,
                'name'     => $e->name,
                'position' => $e->POSITION ?? '',
            ])
        );
    }

    /**
     * I-map ang signatory position sa opisyal na titulo:
     * - "Executive Director V"       → "Director General/Secretary, TESDA"
     * - "Deputy Executive Director V" → "Deputy Director General, {OFFICE}"
     */
    private function mapSignatoryPosition(string $position, ?string $empcode): string
    {
        $trimmed = trim($position);

        if (strcasecmp($trimmed, 'Executive Director V') === 0) {
            return 'Director General/Secretary, TESDA';
        }

        if (strcasecmp($trimmed, 'Deputy Executive Director V') === 0) {
            $office = null;

            if ($empcode) {
                $employee = \App\Models\Employee::where('EMPCODE', $empcode)->first();
                $office   = $employee?->SECTION ?? null;
            }

            return 'Deputy Director General' . ($office ? ', ' . $office : '');
        }

        return $trimmed;
    }

    private function sanitizeRichText(string $html): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR);

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@style]') as $node) {
            $style = $node->getAttribute('style');
            $style = preg_replace('/\s*font-size\s*:[^;]+;?/i', '', $style);
            $style = trim($style);
            if ($style === '') {
                $node->removeAttribute('style');
            } else {
                $node->setAttribute('style', $style);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }
}