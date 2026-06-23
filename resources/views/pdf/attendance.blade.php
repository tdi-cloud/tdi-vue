<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Attendance - {{ $date }} | {{ $programTitle }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            color: #000;
            margin-top: 1in;
            margin-bottom: 0.5in;
            margin-left: 0.5in;
            margin-right: 0.5in;
        }

        .header {
            text-align: center;
            margin-bottom: 18pt;
        }

        .header .label {
            font-size: 12pt;
            margin-bottom: 2pt;
        }

        .header .title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 2pt;
        }

        .header .meta {
            font-size: 12pt;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12pt;
            font-size: 12pt;
        }

        thead tr th {
            background-color: #F5F0C8;
            border: 1pt solid #000;
            padding: 5pt 6pt;
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            text-transform: uppercase;
            vertical-align: middle;
        }

        thead tr th.col-no        { width: 5%; }
        thead tr th.col-name      { width: 28%; text-align: center; padding-left: 6pt; }
        thead tr th.col-position  { width: 28%; text-align: center; padding-left: 6pt; }
        thead tr th.col-office    { width: 22%; text-align: center; padding-left: 6pt; }
        thead tr th.col-signature { width: 17%; }

        tbody tr td {
            border: 1pt solid #000;
            padding: 4pt 6pt;
            font-size: 12pt;
            vertical-align: middle;
            height: 22pt;
        }

        tbody tr td.col-no {
            text-align: right;
            width: 5%;
            vertical-align: middle;
        }

        tbody tr td.col-signature {
            width: 17%;
        }

        .signatories {
            margin-top: 20pt;
            width: 100%;
        }

        .signatories table {
            border: none;
            margin-top: 0;
        }

        .signatories table td {
            border: none;
            padding: 0;
            vertical-align: top;
            width: 50%;
        }

        .sig-label {
            font-size: 12pt;
            margin-bottom: 18pt;
        }

        .sig-name {
            font-size: 12pt;
            font-weight: bold;
        }

        .sig-sub {
            font-size: 12pt;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="label">Attendance</div>
        <div class="title">{{ $programTitle }}</div>
        <div class="meta">{{ $date }} | {{ $venue }}</div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th class="col-no">NO.</th>
                <th class="col-name">NAME</th>
                <th class="col-position">POSITION/DESIGNATION</th>
                <th class="col-office">OFFICE</th>
                <th class="col-signature">SIGNATURE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($participants as $p)
            <tr>
                <td class="col-no">{{ $p['no'] }}</td>
                <td>{{ $p['name'] }}</td>
                <td>{{ $p['position'] }}</td>
                <td>{{ $p['office'] }}</td>
                <td class="col-signature"></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signatories -->
    <div class="signatories">
        <table>
            <tr>
                <td>
                    <div class="sig-label">Prepared by:</div>
                    <div class="sig-name">{{ $prepared_name }}</div>
                    <div class="sig-sub">{{ $prepared_position }}, {{ $prepared_office }}</div>
                </td>
                <td>
                    <div class="sig-label">Noted by:</div>
                    <div class="sig-name">{{ $noted_name }}</div>
                    <div class="sig-sub">{{ $noted_position }}, {{ $noted_office }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>