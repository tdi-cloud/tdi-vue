<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; padding: 40px 16px; background: #f4f4f4;">
    <div style="max-width: 480px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden;">
        <div style="background: #1d4ed8; padding: 24px 32px;">
            <p style="margin: 0; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #bfdbfe;">TESDA Development Institute</p>
            <h2 style="margin: 6px 0 0; color: white;">About your registration</h2>
        </div>

        <div style="padding: 28px 32px;">
            <p style="color: #333; font-size: 14px;">Hello {{ $name }},</p>
            <p style="color: #555; font-size: 14px; line-height: 1.6;">
                Thank you for registering for <strong>{{ $programTitle }}</strong>@if ($batchLabel) ({{ $batchLabel }})@endif.
                After review, the organizer could not accept your registration for this batch.
            </p>

            @if ($reason)
                <div style="margin: 20px 0; border-left: 4px solid #1d4ed8; background: #f8fafc; padding: 12px 16px;">
                    <p style="margin: 0 0 4px; color: #1e3a8a; font-size: 12px; font-weight: bold;">Reason</p>
                    <p style="margin: 0; color: #333; font-size: 13px; line-height: 1.6;">{{ $reason }}</p>
                </div>
            @endif

            <p style="color: #555; font-size: 13px; line-height: 1.6;">
                If you have questions, please get in touch with the program organizer.
            </p>
        </div>
    </div>
</body>
</html>
