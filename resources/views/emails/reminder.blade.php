<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $emailSubject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #1a1a1a;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 640px;
            margin: 32px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background: #1d4ed8;
            padding: 24px 32px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 16px;
            margin: 0;
            font-weight: 700;
        }
        .header p {
            color: #bfdbfe;
            font-size: 12px;
            margin: 4px 0 0;
        }
        .body {
            padding: 32px;
            line-height: 1.5;
        }
        .body a {
            color: #1d4ed8;
        }
        .divider {
            border: none;
            border-top: 1px dashed #e5e7eb;
            margin: 24px 0;
        }
        .signature {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.2;
            white-space: pre-line;
        }
        .footer {
            background: #f9fafb;
            padding: 16px 32px;
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>📌 TESDA Development Institute</h1>
            <p>Post-Training Requirements Reminder</p>
        </div>

        <div class="body">
            {!! $body !!}
            <hr class="divider" />
            <div class="signature">{!! $signature !!}</div>
        </div>

        <div class="footer">
            This is an automated message from TDI L&D Monitoring System.
            Please do not reply directly to this email.
        </div>
    </div>
</body>
</html>