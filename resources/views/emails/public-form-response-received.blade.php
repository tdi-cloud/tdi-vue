<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; padding: 40px 16px; background: #f4f4f4;">
    <div style="max-width: 560px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden;">
        <div style="background: #1d4ed8; padding: 24px 32px;">
            <p style="margin: 0; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #bfdbfe;">TESDA Development Institute</p>
            <h2 style="margin: 6px 0 0; color: white;">Your response was received</h2>
        </div>

        <div style="padding: 28px 32px;">
            <p style="color: #333; font-size: 14px;">Hello {{ $name }},</p>
            <p style="color: #555; font-size: 14px; line-height: 1.6;">
                Thank you for answering the <strong>{{ $formTitle }}</strong>. Your response was accepted and recorded
                @if ($submittedAt) on {{ $submittedAt }}@endif.
            </p>

            <div style="margin: 20px 0; border-left: 4px solid #1d4ed8; background: #f8fafc; padding: 12px 16px;">
                <p style="margin: 0 0 4px; color: #1e3a8a; font-size: 12px; font-weight: bold;">Reference number</p>
                <p style="margin: 0; color: #111; font-size: 18px; font-weight: bold; letter-spacing: 1px;">{{ $reference }}</p>
            </div>

            <p style="margin: 24px 0 8px; color: #1e3a8a; font-size: 13px; font-weight: bold;">A copy of your answers</p>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 13px;">
                @foreach ($answers as $answer)
                    <tr>
                        <td style="padding: 10px 0; border-top: 1px solid #e5e7eb; vertical-align: top;">
                            <p style="margin: 0 0 4px; color: #555; line-height: 1.5;">@if ($answer['number']){{ $answer['number'] }}. @endif{{ $answer['question'] }}</p>
                            @forelse ($answer['lines'] as $line)
                                <p style="margin: 0; color: #111; font-weight: bold; line-height: 1.5;">{{ $line }}</p>
                            @empty
                                <p style="margin: 0; color: #999; line-height: 1.5;">No answer</p>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </table>

            <p style="margin-top: 24px; color: #555; font-size: 13px; line-height: 1.6;">
                Each employee can answer this form once. If you didn't send this response, or something in it needs to be corrected,
                please let the TESDA Development Institute know and mention your reference number.
            </p>
        </div>
    </div>
</body>
</html>
