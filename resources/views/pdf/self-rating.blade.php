<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Self-Rating - {{ $a->name }} - {{ $a->position }}</title>
<style>
    @page { margin: 0.5in 0.9in; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.15; }

    .code   { text-align: right; font-size: 10pt; line-height: 1.3; margin-bottom: 3px; }
    .bold   { font-weight: bold; }
    .blank  { display: inline-block; min-width: 130px; border-bottom: 1px solid #000; text-align: center; }

    /* ===== Title block ===== */
    .title-block { border: 1px solid #000; padding: 8px 8px 6px 8px; }
    .title-block .agency { font-size: 11pt; font-weight: bold; text-align: center; }
    .title-block .t1 { font-size: 10.5pt; font-weight: bold; text-align: center; margin-top: 10px; }
    .title-block .t2 { font-size: 10.5pt; font-weight: bold; text-align: center; }
    .title-block .period { font-size: 10pt; text-align: center; margin-top: 6px; }

    /* ===== Identity table ===== */
    table.meta { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: 0; }
    table.meta td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; vertical-align: top; }

    /* ===== Instructions ===== */
    .instructions { border: 1px solid #000; border-top: 0; padding: 6px 8px; font-size: 10pt; line-height: 1.25; text-align: justify; }

    /* ===== Scale guide ===== */
    .scale-wrap { border: 1px solid #000; border-top: 0; }
    table.scale { width: 100%; border-collapse: collapse; }
    table.scale td { vertical-align: top; }
    table.scale td.scale-title { font-size: 10pt; font-weight: bold; padding: 2px 4px 0 4px; }
    table.scale td.lbl { width: 40%; font-weight: bold; text-align: center; padding: 2px 4px 0 4px; }
    table.scale td.grp { padding: 0 0 2px 0; }
    table.scale td.grp-last { padding-bottom: 3px; }

    /* numbers sit OUTSIDE the bordered box, descriptions centered INSIDE */
    table.scale-inner { width: 100%; border-collapse: collapse; }
    table.scale-inner td.n   { width: 24px; text-align: center; font-size: 10pt; padding: 0 4px 0 0; border: 0; }
    table.scale-inner td.d   { border-left: 1px solid #000; border-right: 1px solid #000; text-align: center; font-size: 10pt; padding: 0 6px; }
    table.scale-inner tr.first td.d { border-top: 1px solid #000; }
    table.scale-inner tr.last  td.d { border-bottom: 1px solid #000; }

    /* ===== Competency grid ===== */
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 3px 5px; font-size: 10pt; line-height: 1.15; }
    table.grid thead th { text-align: center; font-weight: bold; vertical-align: middle; }
    table.grid td.unit { font-weight: bold; vertical-align: middle; text-align: center; padding: 3px 6px; }
    table.grid td.elem { vertical-align: middle; padding: 3px 6px; }
    table.grid td.num  { text-align: center; vertical-align: middle; font-weight: bold; font-family: DejaVu Sans, sans-serif; font-size: 12pt; padding: 1px 2px; }
    table.grid th.n    { padding: 2px 1px; width: 4%; }
    table.grid tbody { page-break-inside: avoid; }
    table.grid tr { page-break-inside: avoid; }

    /* Nested table per unit: tunay na merged ang unit cell, at hindi
       hinahati ng dompdf ang isang outer row sa page break */
    table.grid td.nest { padding: 0; vertical-align: top; }
    table.inner { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.inner td { border: 1px solid #000; padding: 3px 6px; font-size: 10pt; line-height: 1.15; }
    table.inner td.elem { vertical-align: middle; }
    table.inner td.num  { text-align: center; vertical-align: middle; font-weight: bold; font-family: DejaVu Sans, sans-serif; font-size: 12pt; padding: 1px 2px; }
    table.inner tr.r-first td { border-top: 0; }
    table.inner tr.r-last td  { border-bottom: 0; }
    table.inner td.c-first { border-left: 0; }
    table.inner td.c-last  { border-right: 0; }

    .elective-heading { font-size: 10pt; font-weight: bold; margin: 16px 0 0 0; }

    /* ===== Signature ===== */
    .sign { width: 100%; margin-top: 40px; }
    .sign td { font-size: 10pt; }
    .sign .name { font-weight: bold; text-transform: uppercase; text-align: center; padding-bottom: 2px; }
    .sign .line { border-top: 1px solid #000; text-align: center; padding-top: 2px; }
</style>
</head>
<body>

    @php
        $tick = fn ($val, $opt) => (! is_null($val) && (int) $val === $opt) ? '✓' : '';
        $crit = [1, 2, 3];
        $comp = [0, 1, 2, 3, 4];
        $freq = [1, 2, 3];

        $scales = [
            'CRITICALITY TO JOB' => [
                1 => 'Slightly important',
                2 => 'Moderately important',
                3 => 'Highly important',
            ],
            'LEVEL OF COMPETENCE' => [
                0 => 'Not competent',
                1 => 'Slightly competent',
                2 => 'Moderately competent',
                3 => 'Competent',
                4 => 'Highly competent',
            ],
            'FREQUENCY OF UTILIZATION' => [
                1 => 'Rarely',
                2 => 'Occasionally',
                3 => 'Frequently',
            ],
        ];
    @endphp

    <div class="code">
        TESDA-OP-AS-01-F01<br>
        Rev. No. 00 - 03/01/17
    </div>

    {{-- ===== Title ===== --}}
    <div class="title-block">
        <div class="agency">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</div>
        <div class="t1">ASSESSMENT OF CURRENT COMPETENCIES</div>
        <div class="t2">[Self-Rating]</div>
        <div class="period">
            for the period
            <span class="blank">{!! e($a->period) ?: '&nbsp;' !!}</span>
        </div>
    </div>

    {{-- ===== Identity ===== --}}
    <table class="meta">
        <tr>
            <td style="width:40%"><span class="bold">Name:</span> {{ $a->name }}</td>
            <td style="width:30%"><span class="bold">Office:</span> {{ $a->office }}</td>
            <td style="width:30%"><span class="bold">Division:</span> {{ $a->division }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="bold">Position Title:</span> {{ $a->position }}</td>
            <td><span class="bold">Designation:</span> {{ $a->designation }}</td>
        </tr>
    </table>

    {{-- ===== Instructions ===== --}}
    <div class="instructions">
        <span class="bold">INSTRUCTIONS:</span> Below are the units of competencies required in the
        performance of your job. Using the scale below, rate the competency units according to its
        CRITICALITY to your job, your level of COMPETENCY, and FREQUENCY of utilization. Please answer
        carefully as this assessment will determine your Professional Development Plan.
    </div>

    {{-- ===== Scale guide ===== --}}
    <div class="scale-wrap">
        <table class="scale">
            <tr>
                <td>&nbsp;</td>
                <td class="scale-title">
                    <table class="scale-inner">
                        <tr>
                            <td class="n">&nbsp;</td>
                            <td style="text-align:center; font-weight:bold; padding: 0 6px;">SCALE GUIDE</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @foreach($scales as $label => $items)
                @php $isLast = $loop->last; @endphp
                <tr>
                    <td class="lbl">{{ $label }}</td>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td class="grp {{ $isLast ? 'grp-last' : '' }}">
                        <table class="scale-inner">
                            @foreach($items as $n => $desc)
                                <tr class="{{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}">
                                    <td class="n">{{ $n }}</td>
                                    <td class="d">{{ $desc }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- ===== CORE GRID ===== --}}
    <table class="grid">
        <colgroup>
            <col style="width:20%">
            <col style="width:36%">
            @foreach($crit as $c)<col style="width:4%">@endforeach
            @foreach($comp as $c)<col style="width:4%">@endforeach
            @foreach($freq as $c)<col style="width:4%">@endforeach
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2" style="width:20%">Unit of<br>Competency</th>
                <th rowspan="2" style="width:36%">Elements</th>
                <th colspan="3" style="width:12%">Criticality<br>to Job</th>
                <th colspan="5" style="width:20%">Level of<br>Competence</th>
                <th colspan="3" style="width:12%">Frequency<br>of Utilization</th>
            </tr>
            <tr>
                @foreach($crit as $n)<th class="n">{{ $n }}</th>@endforeach
                @foreach($comp as $n)<th class="n">{{ $n }}</th>@endforeach
                @foreach($freq as $n)<th class="n">{{ $n }}</th>@endforeach
            </tr>
        </thead>
        @foreach($core as $unit)
            <tbody>
                <tr>
                    <td class="unit">{{ $unit['unit'] }}</td>
                    <td class="nest" colspan="12">
                        <table class="inner">
                            @foreach($unit['rows'] as $row)
                                <tr class="{{ $loop->first ? 'r-first' : '' }} {{ $loop->last ? 'r-last' : '' }}">
                                    <td class="elem c-first" style="width:45%">{{ $row['element'] }}</td>
                                    @foreach($crit as $n)<td class="num" style="width:5%">{{ $tick($row['criticality'], $n) }}</td>@endforeach
                                    @foreach($comp as $n)<td class="num" style="width:5%">{{ $tick($row['competence'], $n) }}</td>@endforeach
                                    @foreach($freq as $n)<td class="num {{ $loop->last ? 'c-last' : '' }}" style="width:5%">{{ $tick($row['frequency'], $n) }}</td>@endforeach
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </tbody>
        @endforeach
    </table>

    {{-- ===== ELECTIVE ===== --}}
    @if($elective->count())
        <div class="elective-heading">ELECTIVE COMPETENCIES</div>

        <table class="grid" style="margin-top:6px;">
            <colgroup>
                <col style="width:20%">
                <col style="width:36%">
                @foreach($crit as $c)<col style="width:4%">@endforeach
                @foreach($comp as $c)<col style="width:4%">@endforeach
                @foreach($freq as $c)<col style="width:4%">@endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" style="width:20%">Unit of<br>Competency</th>
                    <th rowspan="2" style="width:36%">Elements</th>
                    <th colspan="3" style="width:12%">Criticality<br>to Job</th>
                    <th colspan="5" style="width:20%">Level of<br>Competence</th>
                    <th colspan="3" style="width:12%">Frequency<br>of Utilization</th>
                </tr>
                <tr>
                    @foreach($crit as $n)<th class="n">{{ $n }}</th>@endforeach
                    @foreach($comp as $n)<th class="n">{{ $n }}</th>@endforeach
                    @foreach($freq as $n)<th class="n">{{ $n }}</th>@endforeach
                </tr>
            </thead>
            @foreach($elective as $unit)
                <tbody>
                    <tr>
                        <td class="unit">{{ $unit['unit'] }}</td>
                        <td class="nest" colspan="12">
                            <table class="inner">
                                @foreach($unit['rows'] as $row)
                                    <tr class="{{ $loop->first ? 'r-first' : '' }} {{ $loop->last ? 'r-last' : '' }}">
                                        <td class="elem c-first" style="width:45%">{{ $row['element'] }}</td>
                                        @foreach($crit as $n)<td class="num" style="width:5%">{{ $tick($row['criticality'], $n) }}</td>@endforeach
                                        @foreach($comp as $n)<td class="num" style="width:5%">{{ $tick($row['competence'], $n) }}</td>@endforeach
                                        @foreach($freq as $n)<td class="num {{ $loop->last ? 'c-last' : '' }}" style="width:5%">{{ $tick($row['frequency'], $n) }}</td>@endforeach
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @endif

    {{-- ===== Signature ===== --}}
    <table class="sign">
        <tr>
            <td style="width:52%">&nbsp;</td>
            <td>
                <div class="name">{{ $a->name }}</div>
                <div class="line">Signature over printed name</div>
            </td>
        </tr>
    </table>

</body>
</html>