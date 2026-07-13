<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>TNA Result - {{ $a->name }} - {{ $a->position }}</title>
<style>
    @page { margin: 0.5in 0.6in; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.15; }

    .code { text-align: right; font-size: 10pt; font-weight: bold; line-height: 1.3; margin-bottom: 3px; }
    .bold { font-weight: bold; }

    /* Header */
    table.head { width: 100%; border-collapse: collapse; border: 1px solid #000; }
    table.head td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; vertical-align: top; line-height: 1.25; }
    table.head td.title { text-align: center; font-weight: bold; border-bottom: 0; }
    table.head td.sub   { text-align: center; font-weight: bold; border-top: 0; }

    /* Grid */
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 3px 5px; font-size: 9.5pt; line-height: 1.15; }
    table.grid thead th { text-align: center; font-weight: bold; vertical-align: middle; font-size: 7pt; padding: 3px 1px; line-height: 1.15; }
    table.grid th.sf { width: 7.5%; font-size: 8pt; font-style: normal; font-weight: bold; padding: 1px 0; }
    table.grid td.unit { width: 20%; font-weight: normal; vertical-align: middle; text-align: center; padding: 4px 4px; }
    table.grid td.unit-cont { border-top: 0; }
    table.grid th.h-unit { width: 20%; }
    table.grid th.h-elem { width: 25%; }
    table.grid th.h-grp  { width: 15%; }
    table.grid th.h-res  { width: 10%; }
    table.grid td.num  { width: 7.5%; text-align: center; vertical-align: middle; padding: 4px 0; }
    table.grid td.res  { width: 10%; text-align: center; vertical-align: middle; font-weight: bold; font-size: 9.5pt; padding: 4px 2px; }
    table.grid tr { page-break-inside: avoid; }
    table.grid tbody { page-break-inside: avoid; }

    table.grid td.elem { width: 25%; vertical-align: middle; padding: 4px 5px; }

    table.grid tr.sizer td {
        padding: 0 !important;
        border: 0 !important;
        height: 0 !important;
        line-height: 0 !important;
        font-size: 0 !important;
    }

    .elective-heading { font-size: 10pt; font-weight: bold; margin: 12px 0 4px 0; }

    /* Priority + legend */
    table.summary { width: 100%; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid; }
    table.summary td.prio-cell {
        border: 1px solid #000; padding: 0; vertical-align: top; width: 50%;
    }
    table.summary td.legend-cell {
        border: 1px solid #000; padding: 6px 8px; vertical-align: middle; width: 50%;
        font-size: 9pt; text-align: center; line-height: 1.4;
    }
    .prio-hd { text-align: center; font-weight: bold; font-size: 9.5pt; border-bottom: 1px solid #000; padding: 3px; }
    .prio-bd { padding: 6px 8px; font-size: 9pt; text-align: justify; }
    .prio-list { margin-top: 4px; }
    .prio-list div { margin-bottom: 1px; line-height: 1.3; text-align: left; }

    /* Signatories */
    table.sign { width: 100%; border-collapse: collapse; margin-top: 16px; page-break-inside: avoid; }
    table.sign td { border: 1px solid #000; padding: 6px 8px; font-size: 10pt; vertical-align: top; height: 110px; }
    .sign-label { font-weight: normal; }
    .sign-img-wrap { text-align: center; margin-top: 10px; }
    .sign-img-wrap img { height: 55px; }
    .sign-name { margin-top: 4px; font-weight: bold; text-transform: uppercase; }
    .sign-name.no-img { margin-top: 56px; }
    .sign-pos { font-size: 9pt; }
</style>
</head>
<body>

@php
    $n = fn ($v) => is_null($v) ? '' : $v;
    $fmt = fn ($v) => number_format((float) $v, 1, '.', '');
@endphp

<div class="code">
    TESDA-OP-AS-01-F03<br>
    Rev. No. 00 - 03/01/17
</div>

{{-- Header --}}
<table class="head">
    <tr>
        <td colspan="2" class="title">TRAINING NEEDS ANALYSIS OF CURRENT COMPETENCIES</td>
    </tr>
    <tr>
        <td colspan="2" class="sub">({{ $a->position }})</td>
    </tr>
    <tr>
        <td style="width:52%">Name of Employee: <span class="bold">{{ $a->name }}</span></td>
        <td rowspan="2" style="width:48%; vertical-align: middle; text-align: center;">Name of Supervisor:<br><span class="bold">{{ $form['name'] ?? $a->supervisor_name }}</span></td>
    </tr>
    <tr>
        <td>Office/Division/Unit: <span class="bold">{{ $a->office }}{{ $a->division ? ' / ' . $a->division : '' }}</span></td>
    </tr>
</table>

@foreach (['core' => null, 'elective' => 'ELECTIVE COMPETENCIES'] as $type => $heading)
    @php
        $group = $units->map(fn ($u) => [
            'unit' => $u['unit'],
            'rows' => collect($u['rows'])->filter(fn ($r) => $r['type'] === $type)->values(),
        ])->filter(fn ($u) => count($u['rows']))->values();
    @endphp

    @if ($group->count())
        @if ($heading)
            <div class="elective-heading">{{ $heading }}</div>
        @endif

        <table class="grid" @if($heading) style="margin-top:4px;" @endif>
            <colgroup>
                <col style="width:20%">
                <col style="width:25%">
                <col style="width:7.5%"><col style="width:7.5%">
                <col style="width:7.5%"><col style="width:7.5%">
                <col style="width:7.5%"><col style="width:7.5%">
                <col style="width:10%">
            </colgroup>
            <thead>
                <tr class="sizer">
                    <td style="width:20%"></td>
                    <td style="width:25%"></td>
                    <td style="width:7.5%"></td><td style="width:7.5%"></td>
                    <td style="width:7.5%"></td><td style="width:7.5%"></td>
                    <td style="width:7.5%"></td><td style="width:7.5%"></td>
                    <td style="width:10%"></td>
                </tr>
                <tr>
                    <th rowspan="2" class="h-unit">UNIT OF<br>COMPETENCY</th>
                    <th rowspan="2" class="h-elem">ELEMENTS OF UNIT</th>
                    <th colspan="2" class="h-grp">CRITICALITY<br>TO JOB</th>
                    <th colspan="2" class="h-grp">COMPETENCY<br>LEVEL</th>
                    <th colspan="2" class="h-grp">FREQUENCY OF<br>APPLICATION</th>
                    <th rowspan="2" class="h-res">Competency<br>Profile<br>Results</th>
                </tr>
                <tr>
                    <th class="sf">Sf</th><th class="sf">Sp</th>
                    <th class="sf">Sf</th><th class="sf">Sp</th>
                    <th class="sf">Sf</th><th class="sf">Sp</th>
                </tr>
            </thead>

            @foreach ($group as $unit)
                <tbody>
                    @foreach ($unit['rows'] as $row)
                        <tr>
                            {{-- Walang rowspan: iniiwasan ang dompdf bug kapag
                                 nahati ang unit sa page break. Blangko + walang
                                 top border = mukhang merged pa rin. --}}
                            @if ($loop->first)
                                <td class="unit">{{ $unit['unit'] }}</td>
                            @else
                                <td class="unit unit-cont"></td>
                            @endif
                            <td class="elem">{{ $row['element'] }}</td>
                            <td class="num">{{ $n($row['crit_self']) }}</td>
                            <td class="num">{{ $n($row['crit_sup']) }}</td>
                            <td class="num">{{ $n($row['comp_self']) }}</td>
                            <td class="num">{{ $n($row['comp_sup']) }}</td>
                            <td class="num">{{ $n($row['freq_self']) }}</td>
                            <td class="num">{{ $n($row['freq_sup']) }}</td>
                            <td class="res">{{ $fmt($row['score']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    @endif
@endforeach

{{-- Training Priority List + legend --}}
<table class="summary">
    <tr>
        <td class="prio-cell">
            <div class="prio-hd">TRAINING PRIORITY LIST</div>
            <div class="prio-bd">
                Based on the ratings provided by the respondents, the employee/official must
                undergo training programs that will address deficiencies on the following
                competencies:
                @if ($priority->count())
                    <div class="prio-list">
                        @foreach ($priority as $p)
                            <div class="bold">{{ $loop->iteration }}. {{ $p['unit'] }}</div>
                        @endforeach
                    </div>
                @else
                    <div class="prio-list">
                        <div>1.</div><div>2.</div><div>3.</div>
                    </div>
                @endif
            </div>
        </td>
        <td class="legend-cell">
            0-4: Not Competent<br>
            5-12: Slightly Competent<br>
            13-20: Moderately Competent<br>
            21-28: Competent<br>
            29-36: Highly competent<br>
            Not Competent, Slightly Competent, and Moderately<br>
            Competent results AUTOMATICALLY require trainings
        </td>
    </tr>
</table>

{{-- Signatories --}}
<table class="sign">
    <tr>
        <td style="width:50%">
            <div class="sign-label">Prepared by:</div>
            @if ($form['signature'] ?? null)
                <div class="sign-img-wrap"><img src="{{ $form['signature'] }}"></div>
            @endif
            <div class="sign-name @if (! ($form['signature'] ?? null)) no-img @endif">{{ $form['name'] ?? $a->supervisor_name }}</div>
            <div class="sign-pos">{{ $a->supervisor_position }}{{ ($form['office'] ?? null) ? ', ' . $form['office'] : '' }}</div>
        </td>
        <td style="width:50%">
            <div class="sign-label">Noted by:</div>
            <div class="sign-name no-img">{{ $form['fasd_name'] ?? '' }}</div>
            <div class="sign-pos">{{ $form['fasd_position'] ?? '' }}{{ ($form['fasd_office'] ?? null) ? ', ' . $form['fasd_office'] : '' }}</div>
        </td>
    </tr>
</table>

</body>
</html>