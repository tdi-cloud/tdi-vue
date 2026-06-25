<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f3f4f6; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: #1d4ed8; color: #fff; padding: 20px 24px; }
        .header p { margin: 0; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #bfdbfe; }
        .header h1 { margin: 4px 0 0; font-size: 18px; }
        .body { padding: 24px; }
        .row { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .row:last-child { border-bottom: 0; }
        .label { color: #6b7280; font-weight: 600; display: inline-block; width: 150px; }
        .footer { padding: 16px 24px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <p>{{ $config->organizing_sponsor ?? 'Foreign Program' }}</p>
            <h1>New Nomination Submitted</h1>
        </div>
        <div class="body">
            <p style="margin-top:0;font-size:14px;">
                A new nominee has submitted through the nomination form. Here are the details:
            </p>

            <div class="row"><span class="label">Program:</span> {{ $program->program_title ?? '—' }}</div>
            <div class="row"><span class="label">Name:</span> {{ $nominee->full_name }}</div>
            <div class="row"><span class="label">Sex / Age:</span> {{ ucfirst($nominee->sex) }} / {{ $nominee->age }}</div>
            <div class="row"><span class="label">Position:</span> {{ $nominee->position }}</div>
            <div class="row"><span class="label">Agency:</span> {{ $nominee->agency }}</div>
            <div class="row"><span class="label">Contact Number:</span> {{ $nominee->contact_number ?? '—' }}</div>
            <div class="row"><span class="label">Email:</span> {{ $nominee->email ?? '—' }}</div>
            <div class="row"><span class="label">Status:</span> {{ $nominee->status_label }}</div>
            <div class="row"><span class="label">Submitted:</span> {{ $nominee->created_at->format('M d, Y g:i A') }}</div>
        </div>
        <div class="footer">
            This is an automated notification from your nomination system.
        </div>
    </div>
</body>
</html>