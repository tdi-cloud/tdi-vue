<!DOCTYPE html>
<html>
<head>
<title>TESDA Order — {{ $order->subject }}</title>
<style>
    @page {
        margin: 265px 48px 90px 48px;
    }
    body { font-family: Arial, sans-serif; font-size: 12pt; color: #000; margin: 0; }

    /* Panatilihin ang color/background-color ng user — tanggalin lang ang font-size override */
    .body-content, .body-content *,
    .closure-content, .closure-content * {
        background-color: transparent;
        font-family: Arial, sans-serif !important;
        font-size: 12pt !important;
    }

    .title-area {
        position: fixed;
        top: -215px;
        left: 0;
        width: 698px;
        text-align: center;
        box-sizing: border-box;
    }
    .title {
        font-size: 32pt;
        font-weight: bold;
        letter-spacing: 1px;
        margin: 0;
    }

    .page-frame {
        position: fixed;
        top: -165px;
        left: 0;
        width: 698px;
        height: 979px;
        border: 2px solid #000;
        box-sizing: border-box;
    }

    /* ── Repeating header ── */
    .repeat-header {
        position: fixed;
        top: -165px;
        left: 0;
        width: 700px;
        height: 155px;
    }

    /* ── Header table: lahat ng content ay BOLD ── */
    table.header-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #000;
        box-sizing: border-box;
        table-layout: fixed;
    }
    table.header-table td {
        border: 1px solid #000;
        padding: 8px 10px;
        vertical-align: top;
        font-size: 11pt;
        font-weight: bold;
        line-height: 20px;
    }
    .subject-cell { width: 66.66%; }
    .page-cell { width: 33.34%; }
    .meta-cell { width: 33.33%; }
    .label-bold { font-weight: bold; }

    .content-wrap { padding: 0 .5in; }

    .body-content { margin: 0 0 14px; text-align: justify; }
    .body-content p { margin: 0 0 10px; text-indent: 30px; }
    .body-content ul, .body-content ol { margin: 0 0 10px 30px; padding: 0; }

    table.participants-table { width: 100%; border-collapse: collapse; margin: 14px 0; }
    table.participants-table th, table.participants-table td {
        border: 1px solid #000; padding: 5px 8px; font-size: 11pt; text-align: left;
        background: transparent;
    }
    table.participants-table th { font-weight: bold; background: transparent; }
    .batch-label { font-weight: bold; margin: 10px 0 4px; font-size: 12pt; }

    .closure-content { margin: 14px 0; text-align: justify; }
    .closure-content p { margin: 0 0 10px; text-indent: 30px; }

    .signatory-wrap { width: 100%; margin-top: 50px; }
    .signatory-block {
        width: 280px;
        margin-left: auto;
        text-align: center;
    }
    .signatory-name { font-weight: bold; font-size: 12pt; white-space: nowrap; }
    .signatory-position { font-size: 12pt; margin-top: 2px; }
</style>
</head>
<body>

@php
    $personnelWord = $hasDirector ? 'personnel and officials' : 'personnel';
@endphp

<!-- Title — LABAS sa border, nasa itaas nito -->
<div class="title-area">
    <div class="title">TESDA ORDER</div>
</div>

<!-- Page border frame -->
<div class="page-frame"></div>

<!-- Header table — diretsong dumidikit sa itim na border -->
<div class="repeat-header">
    <table class="header-table">
        <tr>
            <td class="subject-cell" colspan="2">
                <span class="label-bold">SUBJECT:</span> {{ $order->subject }}
            </td>
            <td class="page-cell">
                <span class="label-bold" id="page-slot"></span><br>
                Number _____ Series of {{ $order->series_year }}
            </td>
        </tr>
        <tr>
            <td class="meta-cell">
                <span class="label-bold">Date issued:</span><br>
                {{ $order->date_issued ? $order->date_issued->format('F j, Y') : '' }}
            </td>
            <td class="meta-cell">
                <span class="label-bold">Effectivity:</span><br>
                {{ $order->effectivity }}
            </td>
            <td class="meta-cell">
                <span class="label-bold">Supersedes:</span><br>
                {{ $order->supersedes }}
            </td>
        </tr>
    </table>
</div>

<div class="content-wrap">

    <div class="body-content">
        {!! $order->body !!}
    </div>

    @if ($participants)
        @foreach ($participants as $batchGroup)
            @if ($batchGroup['batch_label'])
                <div class="batch-label">{{ $batchGroup['batch_label'] }}</div>
            @endif
            <table class="participants-table">
                <thead>
                    <tr><th style="width:25%">OFFICE</th><th style="width:40%">NAME</th><th style="width:35%">POSITION</th></tr>
                </thead>
                <tbody>
                    @foreach ($batchGroup['rows'] as $row)
                        <tr>
                            <td>{{ $row['office'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['position'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="closure-content">
        {!! $order->closure !!}
    </div>

    <div class="signatory-wrap">
        <div class="signatory-block">
            <div class="signatory-name">{{ strtoupper($order->signatory_name) }}</div>
            <div class="signatory-position">{{ $order->signatory_position }}</div>
        </div>
    </div>

</div>

</body>
</html>