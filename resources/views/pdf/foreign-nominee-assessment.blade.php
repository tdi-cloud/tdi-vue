<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Nominee Assessment Sheet - {{ $program->program_title }}</title>
<style>
    @page { margin: 0.4in 0.5in; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.2; }

    .title { text-align: center; font-weight: bold; font-size: 14pt; margin-bottom: 10px; }

    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.meta td { padding: 1px 0; font-size: 10pt; vertical-align: top; }
    table.meta td.label { width: 16%; font-weight: bold; }
    table.meta td.value { width: 84%; }

    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid th, table.grid td {
        border: 1px solid #000; padding: 3px 4px; font-size: 6pt;
        overflow: hidden;
    }
    table.grid thead th { text-align: center; font-weight: bold; vertical-align: middle; line-height: 1.15; font-size: 5pt; }
    table.grid tbody td { vertical-align: middle; }
    table.grid td.num { text-align: center; font-size: 8pt; }
    table.grid td.total { text-align: center; font-weight: bold; font-size: 8pt; }
    table.grid td.name { font-weight: bold; text-transform: uppercase; }
    table.grid tr { page-break-inside: avoid; }

    table.grid tr.sizer td {
        padding: 0 !important; border: 0 !important; height: 0 !important;
        line-height: 0 !important; font-size: 0 !important;
    }

    table.sign { width: 40%; border-collapse: collapse; margin-top: 34px; }
    table.sign td { border: 0; padding: 0; font-size: 10pt; }
    .sign-line { border-top: 1px solid #000; padding-top: 3px !important; text-align: center; }
    .sign-name { font-weight: bold; text-transform: uppercase; text-align: center; }
    .sign-pos { text-align: center; }
</style>
</head>
<body>

<div class="title">NOMINEE ASSESSMENT SHEET</div>

<table class="meta">
    <tr>
        <td class="label">TITLE OF THE COURSE:</td>
        <td class="value">{{ $program->program_title }}</td>
    </tr>
    <tr>
        <td class="label">DURATION:</td>
        <td class="value">{{ $duration }}</td>
    </tr>
    <tr>
        <td class="label">SPONSORING DONOR:</td>
        <td class="value">{{ strtoupper($sponsorDisplay) }}</td>
    </tr>
    <tr>
        <td class="label">SLOT(S):</td>
        <td class="value">{{ $slotsLabel }}</td>
    </tr>
</table>

@php
    // NAME / DESIGNATION / AGENCY are sized in points, weighted by the
    // longest value actually present in each column, but capped to ~42% of
    // the page — these columns are allowed to wrap onto 2–3 lines rather
    // than forcing a single line, so they don't need to fit the full value
    // on one line. Whatever width remains is handed to the requirements/
    // interview columns, split in their relative proportions (RELEVANCE
    // gets a bigger share since it has by far the longest header label).
    $pageWidthPt = 770; // A4 landscape (841.89pt) minus 0.5in left/right margins
    $charWidth = 3.8;   // approx. pt per character at 6pt Arial
    $cellPad = 10;      // padding + border allowance per cell

    $hashPt = 7.5; // fixed ~10px, doesn't grow/shrink with the other columns
    $nameLen = max(4, $nominees->max(fn ($n) => strlen($n->full_name)));
    $designationLen = max(4, $nominees->max(fn ($n) => strlen($n->position)));
    $agencyLen = max(4, $nominees->max(fn ($n) => strlen($n->agency)));

    $nameWidthPt = $nameLen * $charWidth + $cellPad;
    $designationWidthPt = $designationLen * $charWidth + $cellPad;
    $agencyWidthPt = $agencyLen * $charWidth + $cellPad;
    $profileWidthPt = $hashPt + $nameWidthPt + $designationWidthPt + $agencyWidthPt;

    $maxProfileWidthPt = $pageWidthPt * 0.42;
    if ($profileWidthPt > $maxProfileWidthPt) {
        $scale = ($maxProfileWidthPt - $hashPt) / ($profileWidthPt - $hashPt);
        $nameWidthPt *= $scale;
        $designationWidthPt *= $scale;
        $agencyWidthPt *= $scale;
        $profileWidthPt = $maxProfileWidthPt;
    }

    // need, relevance, donor, docs, total(70), 6 interview criteria, total score, grand total
    $otherColsRatio = [4, 60, 4, 5, 3, 3, 3, 3, 3, 3, 3, 3, 4];
    $remainingWidthPt = $pageWidthPt - $profileWidthPt;
    $otherColsSum = array_sum($otherColsRatio);
    $otherColsPt = array_map(fn ($p) => round($p / $otherColsSum * $remainingWidthPt, 1), $otherColsRatio);

    [$needPt, $relevancePt, $donorPt, $docsPt, $total70Pt,
        $commPt, $alertPt, $judgePt, $selfPt, $emoPt, $appearancePt,
        $totalScorePt, $grandPt] = $otherColsPt;
@endphp

<table class="grid">
    <colgroup>
        <col style="width:{{ $hashPt }}pt">
        <col style="width:{{ $nameWidthPt }}pt"><col style="width:{{ $designationWidthPt }}pt"><col style="width:{{ $agencyWidthPt }}pt">
        <col style="width:{{ $needPt }}pt"><col style="width:{{ $relevancePt }}pt"><col style="width:{{ $donorPt }}pt"><col style="width:{{ $docsPt }}pt"><col style="width:{{ $total70Pt }}pt">
        <col style="width:{{ $commPt }}pt"><col style="width:{{ $alertPt }}pt"><col style="width:{{ $judgePt }}pt"><col style="width:{{ $selfPt }}pt"><col style="width:{{ $emoPt }}pt"><col style="width:{{ $appearancePt }}pt">
        <col style="width:{{ $totalScorePt }}pt"><col style="width:{{ $grandPt }}pt">
    </colgroup>
    <thead>
        <tr class="sizer">
            <td style="width:{{ $hashPt }}pt"></td>
            <td style="width:{{ $nameWidthPt }}pt"></td><td style="width:{{ $designationWidthPt }}pt"></td><td style="width:{{ $agencyWidthPt }}pt"></td>
            <td style="width:{{ $needPt }}pt"></td><td style="width:{{ $relevancePt }}pt"></td><td style="width:{{ $donorPt }}pt"></td><td style="width:{{ $docsPt }}pt"></td><td style="width:{{ $total70Pt }}pt"></td>
            <td style="width:{{ $commPt }}pt"></td><td style="width:{{ $alertPt }}pt"></td><td style="width:{{ $judgePt }}pt"></td><td style="width:{{ $selfPt }}pt"></td><td style="width:{{ $emoPt }}pt"></td><td style="width:{{ $appearancePt }}pt"></td>
            <td style="width:{{ $totalScorePt }}pt"></td><td style="width:{{ $grandPt }}pt"></td>
        </tr>
        <tr>
            <th colspan="4">NOMINEE'S PROFILE</th>
            <th colspan="5">REQUIREMENTS</th>
            <th colspan="8">INTERVIEW</th>
        </tr>
        <tr>
            <th style="width:{{ $hashPt }}pt">#</th>
            <th style="width:{{ $nameWidthPt }}pt">NAME</th>
            <th style="width:{{ $designationWidthPt }}pt">DESIGNATION</th>
            <th style="width:{{ $agencyWidthPt }}pt">AGENCY</th>
            <th style="width:{{ $needPt }}pt">NOMINEE'S NEED FOR TRAINING (20)</th>
            <th style="width:{{ $relevancePt }}pt; font-size:4.5pt;">RELEVANCE OF THE COURSE TO THE PRESENT DUTIES AND RESPONSIBILITIES (30)</th>
            <th style="width:{{ $donorPt }}pt">NOMINEE MEETS DONOR REQUIREMENTS (10)</th>
            <th style="width:{{ $docsPt }}pt">COMPLETION OF DOCUMENTARY REQUIREMENTS (10)</th>
            <th style="width:{{ $total70Pt }}pt">TOTAL<br>(70)</th>
            <th style="width:{{ $commPt }}pt">COMMUNICATION SKILLS (5)</th>
            <th style="width:{{ $alertPt }}pt">ALERTNESS (5)</th>
            <th style="width:{{ $judgePt }}pt">JUDGEMENT (5)</th>
            <th style="width:{{ $selfPt }}pt">SELF CONFIDENCE (5)</th>
            <th style="width:{{ $emoPt }}pt">EMOTIONAL STABILITY (5)</th>
            <th style="width:{{ $appearancePt }}pt">APPEARANCE (5)</th>
            <th style="width:{{ $totalScorePt }}pt">TOTAL<br>SCORE<br>(30)</th>
            <th style="width:{{ $grandPt }}pt">GRAND<br>TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($nominees as $nominee)
            @php
                $a = $nominee->assessment;
                $r = $nominee->rating;
                $grand = ($a && $r) ? ($a->requirements_total + $r->total) : null;
            @endphp
            <tr>
                <td class="num" @if($loop->first) style="width:{{ $hashPt }}pt" @endif>{{ $loop->iteration }}</td>
                <td class="name" @if($loop->first) style="width:{{ $nameWidthPt }}pt" @endif>{{ $nominee->full_name }}</td>
                <td @if($loop->first) style="width:{{ $designationWidthPt }}pt" @endif>{{ $nominee->position }}</td>
                <td @if($loop->first) style="width:{{ $agencyWidthPt }}pt" @endif>{{ $nominee->agency }}</td>
                <td class="num" @if($loop->first) style="width:{{ $needPt }}pt" @endif>{{ $a->need_for_training ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $relevancePt }}pt" @endif>{{ $a->relevance_to_duties ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $donorPt }}pt" @endif>{{ $a->meets_donor_requirements ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $docsPt }}pt" @endif>{{ $a->completion_of_documents ?? '' }}</td>
                <td class="total" @if($loop->first) style="width:{{ $total70Pt }}pt" @endif>{{ $a->requirements_total ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $commPt }}pt" @endif>{{ $r->communication_skills ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $alertPt }}pt" @endif>{{ $r->alertness ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $judgePt }}pt" @endif>{{ $r->judgement ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $selfPt }}pt" @endif>{{ $r->self_confidence ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $emoPt }}pt" @endif>{{ $r->emotional_stability ?? '' }}</td>
                <td class="num" @if($loop->first) style="width:{{ $appearancePt }}pt" @endif>{{ $r->appearance ?? '' }}</td>
                <td class="total" @if($loop->first) style="width:{{ $totalScorePt }}pt" @endif>{{ $r->total ?? '' }}</td>
                <td class="total" @if($loop->first) style="width:{{ $grandPt }}pt" @endif>{{ $grand ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="sign">
    <tr>
        <td class="sign-line">&nbsp;</td>
    </tr>
    <tr>
        <td class="sign-name">{{ $signatoryName }}</td>
    </tr>
    <tr>
        <td class="sign-pos">{{ $signatoryPosition }}</td>
    </tr>
    <tr>
        <td class="sign-pos">{{ $signatoryRole }}</td>
    </tr>
</table>

</body>
</html>
