<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; padding: 40px 16px; background: #f4f4f4;">
    <div style="max-width: 480px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden;">
        <div style="background: #1d4ed8; padding: 24px 32px;">
            <p style="margin: 0; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #bfdbfe;">TESDA Development Institute</p>
            <h2 style="margin: 6px 0 0; color: white;">{{ $resent ? 'Your new login details' : 'Your registration is approved' }}</h2>
        </div>

        <div style="padding: 28px 32px;">
            <p style="color: #333; font-size: 14px;">Hello {{ $name }},</p>

            @if ($resent)
                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    Here is a new temporary password for your TDI account. The one we sent before no longer works.
                </p>
            @else
                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    Good news: you are now a participant of <strong>{{ $programTitle }}</strong>@if ($batchLabel) ({{ $batchLabel }})@endif.
                </p>
            @endif

            @if ($temporaryPassword)
                <div style="margin: 20px 0; border: 1px solid #dbeafe; background: #eff6ff; border-radius: 10px; padding: 16px 20px;">
                    <p style="margin: 0 0 8px; color: #1e3a8a; font-size: 13px; font-weight: bold;">Your login details</p>
                    <p style="margin: 0; color: #333; font-size: 13px;">Email: <strong>{{ $loginEmail }}</strong></p>
                    <p style="margin: 6px 0 0; color: #333; font-size: 13px;">
                        Temporary password:
                        <span style="font-family: Consolas, monospace; font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #1d4ed8;">{{ $temporaryPassword }}</span>
                    </p>
                </div>
                <p style="color: #555; font-size: 13px; line-height: 1.6;">
                    When you log in for the first time, you'll be asked to set your own password.
                    This temporary password works for {{ $validDays }} day(s)@if ($expiresAt), until {{ $expiresAt }}@endif.
                    After that, use <a href="{{ $forgotPasswordUrl }}" style="color: #1d4ed8;">Forgot password</a> on the login page.
                </p>
            @else
                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    You already have a TDI account, so log in with it as usual. You'll find this program under My Programs.
                </p>
            @endif

            <div style="text-align: center; margin: 24px 0 8px;">
                <a href="{{ $loginUrl }}" style="display: inline-block; background: #1d4ed8; color: white; text-decoration: none; font-weight: bold; font-size: 14px; padding: 12px 28px; border-radius: 8px;">Log in to TDI</a>
            </div>

            <p style="color: #999; font-size: 12px; margin-top: 24px;">
                Keep this email private. TDI staff will never ask you for your password.
            </p>
        </div>
    </div>
</body>
</html>
