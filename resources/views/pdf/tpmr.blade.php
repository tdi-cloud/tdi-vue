<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Training Program Monitoring Report</title>
    <style>
        @page {
            margin: 50pt 36pt 40pt 36pt;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #111;
        }

        .form-code {
            text-align: right;
            font-size: 10pt;
            line-height: 1;
            margin-bottom: 8pt;
        }

        .header {
            text-align: center;
            margin-bottom: 12pt;
            line-height: .9;
        }

        .header .agency {
            font-size: 9pt;
            font-weight: bold;
        }

        .header .region {
            font-size: 10pt;
            margin-top: 2pt;
        }

        .header .title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 6pt;
        }

        .header .period {
            font-size: 10pt;
            margin-top: 2pt;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10pt;
        }

        table.report th,
        table.report td {
            border: 1pt solid #000;
            padding: 5pt 6pt;
            vertical-align: middle;
            text-align: center;
            font-size: 7pt;
            line-height: 1;
        }

        table.report th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        td.center {
            text-align: center;
        }

        .empty-row td {
            text-align: center;
            font-style: italic;
            color: #777;
            padding: 16pt;
        }

        .signatories {
            width: 100%;
            margin-top: 40pt;
            border-collapse: collapse;
        }

        .signatories td {
            width: 50%;
            vertical-align: top;
            padding-right: 24pt;
            font-size: 10pt;
            line-height: 1;
        }

        .sig-name {
            font-weight: bold;
            border-bottom: 1pt solid #000;
            display: inline-block;
            min-width: 200pt;
            padding-bottom: 2pt;
        }

        .sig-label {
            font-size: 8pt;
            color: #444;
            margin-top: 2pt;
        }

        .sig-block {
            margin-top: 32pt;
        }
    </style>
</head>
<body>

    <div class="form-code">
        TESDA-OP-AS-01-F06<br>
        Rev. No. 00 &ndash; 03/01/17
    </div>

    <div class="header">
        <div class="agency">Technical Education and Skills Development Authority</div>
        <div class="region">{{ $regionLabel }}</div>
        <div class="title">Training Program Monitoring Report</div>
        <div class="period">{{ $periodLabel }}</div>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 20%;">Training Program</th>
                <th style="width: 4%;">Start</th>
                <th style="width: 4%;">End</th>
                <th style="width: 9%;">Name of Participants</th>
                <th style="width: 6%;">Office</th>
                <th style="width: 12%;">Position</th>
                <th style="width: 22%;">Status of Implementation of Program (Completed/Not Completed (NC))</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['program_title'] }}</td>
                    <td class="center">{{ $row['start'] }}</td>
                    <td class="center">{{ $row['end'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['office'] }}</td>
                    <td>{{ $row['position'] }}</td>
                    <td class="center">{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="7">No records found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatories">
        <tr>
            <td>
                <div>Prepared by:</div>
                <div class="sig-block">
                    <span class="sig-name">{{ $prepared['name'] ?: '&nbsp;' }}</span>
                    <div class="sig-label">Name (Signature over printed name)</div>
                </div>
                <div style="margin-top: 8px; font-weight: bold;">{{ $prepared['position'] }}</div>
                <div class="sig-label">Position / Office</div>
                <div style="margin-top: 8px;">Date: {{ $prepared['date'] }}</div>
            </td>
            <td>
                <div>Noted by:</div>
                <div class="sig-block">
                    <span class="sig-name">{{ $noted['name'] ?: '&nbsp;' }}</span>
                    <div class="sig-label">Name (Signature over printed name)</div>
                </div>
                <div style="margin-top: 8px; font-weight: bold;">{{ $noted['position'] }}</div>
                <div class="sig-label">Position / Office</div>
                <div style="margin-top: 8px;">Date: {{ $noted['date'] }}</div>
            </td>
        </tr>
    </table>

</body>
</html>