<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ 'Declaration of Completers - ' . $program->title . ' - ' . $batch->batch }}</title>
<style>
    @page { margin: 100px 70px 60px 70px; }
    body {
        font-family: Arial, sans-serif;
        font-size: 12pt;
        color: #000;
        line-height: 1.2;
    }
    h1.title {
        text-align: center;
        font-size: 14pt;
        font-weight: bold;
        margin: 0 0 18px 0;
    }
    p.body-text {
        text-align: justify;
        text-indent: 40px;
        margin: 0 0 12px 0;
        font-size: 12pt;
        line-height: 1.2;
    }
    table.completers {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        table-layout: fixed;
    }
    table.completers th,
    table.completers td {
        border: 1px solid #000;
        padding: 6px 8px;
        font-size: 12pt;
        vertical-align: top;
        word-wrap: break-word;
    }
    table.completers th { text-align: center; font-weight: bold; }
    table.completers td.no { text-align: center; }
    table.completers th.no,
    table.completers td.no { width: 8%; }
    table.completers th.office,
    table.completers td.office,
    table.completers th.name,
    table.completers td.name,
    table.completers th.position,
    table.completers td.position { width: 30.66%; }

    .signatory-wrap {
        width: 100%;
        text-align: right;
        margin-top: 80px;
        padding-right: 40px;
    }
    .signatory .name {
        display: inline-block;
        white-space: nowrap;
        font-weight: bold !important;
        text-transform: uppercase;
        font-size: 12pt;
        margin: 0;
        margin-right: 20px;
        
    }
</style>
</head>
<body>

    <h1 class="title">DECLARATION OF COMPLETERS</h1>

    <p class="body-text">
        This Declaration of Completers is hereby issued to certify that the following
        {{ $personnelLabel }} of the Technical Education and Skills Development Authority (TESDA)
        have satisfactorily Completed the <strong>{{ $program->title }} ({{ $batch->batch }})</strong>
        held on {{ $dateText }} ({{ $batch->hours }} Hours) at the {{ $batch->venue ?: 'TESDA' }}:
    </p>

    <table class="completers">
        <thead>
            <tr>
                <th class="no">NO.</th>
                <th class="office">OFFICE</th>
                <th class="name">NAME</th>
                <th class="position">POSITION</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="no">{{ $row['no'] }}</td>
                    <td class="office">{{ $row['office'] }}</td>
                    <td class="name">{{ $row['name'] }}</td>
                    <td class="position">{{ $row['position'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="body-text">
        Issued this {{ $issuedDay }} day of {{ $issuedMonth }} {{ $issuedYear }} at the TESDA Central Office,
        East Service Road, Fort Bonifacio, Taguig City.
    </p>

    <div class="signatory-wrap signatory">
        <p class="name">{{ $signatoryName }}</p>
    </div>

</body>
</html>