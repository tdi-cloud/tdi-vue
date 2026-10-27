@php
    $pd = $report->program_description ?? [];
    $prepared = $report->prepared_by ?? [];
    $approved = $report->approved_by ?? [];
    $text = fn ($value) => nl2br(e(trim((string) $value)));
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('F j, Y') : '';
    // Part II is always printed; when a TREAP participant skips it, every field reads "N/A".
    $hasPlan = $report->hasReentryPlan();
    $planText = fn ($value) => $hasPlan ? $text($value) : 'N/A';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $report->report_type }} Report - {{ $prepared['name'] ?? '' }}</title>
<style>
    @page { margin: 0.6in 0.75in; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.25; }

    .code { text-align: right; font-size: 9.5pt; line-height: 1.4; }
    .agency { text-align: center; font-weight: bold; font-size: 11.5pt; margin-top: 14px; }
    .form-title { text-align: center; font-weight: bold; font-size: 11.5pt; margin-top: 10px; margin-bottom: 14px; }

    .section-title { font-weight: bold; font-size: 10pt; margin: 6px 0 6px 18px; }

    /* I. Program Description: label : underlined value */
    table.desc { width: 100%; border-collapse: collapse; }
    table.desc td { padding: 5px 0 3px; vertical-align: top; font-size: 10pt; }
    table.desc td.label { width: 25%; font-weight: bold; padding-right: 6px; }
    table.desc td.colon { width: 3%; font-weight: bold; text-align: center; }
    table.desc td.value { border-bottom: 1px solid #000; padding-left: 6px; }
    table.desc td.gap { width: 2%; }
    table.desc td.hours-label { width: 15%; font-weight: bold; text-align: center; }
    table.desc td.hours-value { width: 15%; border-bottom: 1px solid #000; text-align: center; }

    /* II. REAP box */
    table.box { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.box td, table.box th { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; vertical-align: top; }
    table.box .box-head { font-weight: bold; border-bottom: 0; }
    table.box .box-body { border-top: 0; min-height: 30px; padding-bottom: 8px; }
    table.box th { font-weight: bold; text-align: center; vertical-align: middle; }
    table.box tr { page-break-inside: avoid; }

    table.sign { width: 100%; border-collapse: collapse; margin-top: -1px; page-break-inside: avoid; }
    table.sign td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; width: 50%; vertical-align: top; }
    table.sign td.head { font-weight: bold; }
    /* Blank room above the name for a wet signature; name sits right on the caption */
    table.sign td.name { text-align: center; font-weight: bold; padding: 40px 6px 0; border-bottom: 0; line-height: 1.1; }
    table.sign td.caption { text-align: center; border-top: 0; font-size: 9.5pt; padding: 1px 6px 5px; }
</style>
</head>
<body>
    <div class="code">TESDA-OP-AS-01-F07<br>Rev. No. 00 – 03/01/17</div>

    <div class="agency">Technical Education and Skills Development Authority</div>
    <div class="form-title">TERMINAL AND RE-ENTRY ACTION PLAN (REAP) REPORT FORM</div>

    <div class="section-title">I.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Program Description</div>

    <table class="desc">
        <tr><td class="label">Title</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['title'] ?? '') !!}</td></tr>
        <tr>
            <td class="label">Date/Duration</td><td class="colon">:</td>
            <td class="value">{!! $text($pd['date_duration'] ?? '') !!}</td>
            <td class="gap"></td>
            <td class="hours-label">No. of Hours</td>
            <td class="hours-value">{{ $pd['hours'] ?? '' }}</td>
        </tr>
        <tr><td class="label">Venue</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['venue'] ?? '') !!}</td></tr>
        <tr><td class="label">Conducted by</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['conducted_by'] ?? '') !!}</td></tr>
        <tr><td class="label">Sponsoring Institution</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['sponsoring_institution'] ?? '') !!}</td></tr>
        <tr><td class="label">Designed for</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['designed_for'] ?? '') !!}</td></tr>
        <tr><td class="label">Objectives</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['objectives'] ?? '') !!}</td></tr>
        <tr><td class="label">Methodology</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['methodology'] ?? '') !!}</td></tr>
        <tr><td class="label">Course Outline</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['course_outline'] ?? '') !!}</td></tr>
        <tr><td class="label">List of Resource Speakers / Facilitators</td><td class="colon">:</td><td class="value" colspan="4">{!! $text($pd['resource_speakers'] ?? '') !!}</td></tr>
    </table>

    <div class="section-title" style="margin-top: 14px;">II.&nbsp;&nbsp;&nbsp;&nbsp;Re-Entry Action Plan (REAP)</div>

    <table class="box">
        <tr><td class="box-head" colspan="5">I.&nbsp;&nbsp;Competency/ies Achieved / Learning Gained</td></tr>
        <tr><td class="box-body" colspan="5">{!! $planText($report->competencies_gained) !!}&nbsp;</td></tr>
        <tr><td class="box-head" colspan="5">II.&nbsp;&nbsp;Summary of the REAP</td></tr>
        <tr><td class="box-body" colspan="5">{!! $planText($report->reap_summary) !!}&nbsp;</td></tr>
        <tr><td class="box-head" colspan="5">III.&nbsp;&nbsp;Objectives</td></tr>
        <tr><td class="box-body" colspan="5">{!! $planText($report->reap_objectives) !!}&nbsp;</td></tr>
        <tr><td class="box-head" colspan="5" style="border-bottom: 1px solid #000;">IV.&nbsp;&nbsp;Details of the Action Plan</td></tr>
        <tr>
            <th style="width: 24%;">Activities</th>
            <th style="width: 24%;">Expected Output</th>
            <th style="width: 18%;">Schedule of Implementation</th>
            <th style="width: 14%;">Budget</th>
            <th style="width: 20%;">Remarks</th>
        </tr>
        @forelse ($hasPlan ? $report->activities : [] as $activity)
            <tr>
                <td>{!! $text($activity->activity) !!}</td>
                <td>{!! $text($activity->expected_output) !!}</td>
                <td>{{ $activity->scheduleLabel() }}</td>
                {{-- "PHP" instead of the peso sign: the PDF font has no ₱ glyph --}}
                <td>{{ $activity->budget !== null ? 'PHP '.number_format((float) $activity->budget, 2) : 'None' }}</td>
                <td>{!! $text($activity->remarks) !!}</td>
            </tr>
        @empty
            <tr><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td></tr>
        @endforelse
    </table>

    <table class="sign">
        <tr>
            <td class="head">Prepared by:</td>
            <td class="head" style="text-align: center;">Discussed with and Approved by:</td>
        </tr>
        <tr>
            <td class="name">{{ strtoupper($prepared['name'] ?? '') }}</td>
            <td class="name">{{ strtoupper($approved['name'] ?? '') }}</td>
        </tr>
        <tr>
            <td class="caption">Signature over printed name</td>
            <td class="caption">Signature over printed name</td>
        </tr>
        <tr><td>Position: {{ $prepared['position'] ?? '' }}</td><td>Position: {{ $approved['position'] ?? '' }}</td></tr>
        <tr><td>Office: {{ $prepared['office'] ?? '' }}</td><td>Office: {{ $approved['office'] ?? '' }}</td></tr>
        <tr><td>Date: {{ $date($prepared['date'] ?? null) }}</td><td>Date: {{ $date($approved['date'] ?? null) }}</td></tr>
    </table>
</body>
</html>
