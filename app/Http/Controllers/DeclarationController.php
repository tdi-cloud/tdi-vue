<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class DeclarationController extends Controller
{
    /**
     * Search ng employee na pwedeng gawing signatory.
     * GET /declarations/signatories/search?q=juan
     */
    public function searchSignatory(Request $request)
    {
        $q = $request->query('q');

        $employees = Employee::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('LASTNAME', 'LIKE', "%{$q}%")
                      ->orWhere('FIRSTNAME', 'LIKE', "%{$q}%")
                      ->orWhere('EMPCODE', 'LIKE', "%{$q}%");
                });
            })
            ->orderBy('LASTNAME')
            ->limit(10)
            ->get();

        return response()->json(
            $employees->map(fn ($e) => [
                'empcode'  => $e->EMPCODE,
                'name'     => $e->name,
                'position' => $e->POSITION,
            ])->values()
        );
    }

    /**
     * I-generate ang Declaration of Completers PDF ng isang batch.
     * GET /batches/{batch}/declaration?signatory_empcode=XXXX
     */
    public function generate(Request $request, Batch $batch)
    {
        $request->validate([
            'signatory_empcode' => 'required|string|exists:employees,EMPCODE',
        ]);

        $batch->load('program');

        $completers = $batch->participants()
            ->where('attendance', 'Complete')
            ->orderBy('sort_order')
            ->with('employee')
            ->get();

        abort_if($completers->isEmpty(), 422, 'No completers yet for this batch.');

        $signatory = Employee::where('EMPCODE', $request->signatory_empcode)->firstOrFail();

        // ----- "officials and personnel" kapag may SG 22 pataas, kung hindi "personnel" lang -----
        $hasOfficial = $completers->contains(function ($p) {
            $sg = (int) preg_replace('/\D/', '', (string) ($p->employee->SG ?? '0'));
            return $sg >= 22;
        });
        $personnelLabel = $hasOfficial ? 'officials and personnel' : 'personnel';

        // ----- Date range text ng batch -----
        $start = Carbon::parse($batch->date_start);
        $end   = Carbon::parse($batch->date_end);

        if ($start->isSameDay($end)) {
            $dateText = $start->format('d F Y');
        } elseif ($start->isSameMonth($end) && $start->isSameYear($end)) {
            $dateText = $start->format('d') . ' to ' . $end->format('d F Y');
        } elseif ($start->isSameYear($end)) {
            $dateText = $start->format('d F') . ' to ' . $end->format('d F Y');
        } else {
            $dateText = $start->format('d F Y') . ' to ' . $end->format('d F Y');
        }

        // ----- Petsa ng pag-issue (araw ng pag-generate) -----
        $today = Carbon::now();

        $rows = $completers->values()->map(function ($p, $i) {
            return [
                'no'       => $i + 1,
                'office'   => $p->employee->{'OFFICE/DIVISION'} ?? '',
                'name'     => strtoupper($p->employee->name ?? $p->empcode),
                'position' => $p->employee->POSITION ?? '',
            ];
        });

        $pdf = Pdf::loadView('pdf.declaration', [
            'program'        => $batch->program,
            'batch'          => $batch,
            'rows'           => $rows,
            'personnelLabel' => $personnelLabel,
            'dateText'       => $dateText,
            'issuedDay'      => $today->format('jS'),
            'issuedMonth'    => $today->format('F'),
            'issuedYear'     => $today->format('Y'),
            'signatoryName'  => strtoupper($signatory->name),
        ])->setPaper('letter', 'portrait');

        $safeTitle = preg_replace('/[\/\\\\:*?"<>|]/', '-', $batch->program->title);
        $filename = 'Declaration of Completers - ' . $safeTitle . ' - ' . $batch->batch . '.pdf';

        return $pdf->stream($filename);
    }
}