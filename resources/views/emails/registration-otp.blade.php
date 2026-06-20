<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; padding: 40px; background: #f4f4f4;">
    <div style="max-width: 400px; margin: 0 auto; background: white; border-radius: 12px; padding: 32px;">
        <h2 style="margin-bottom: 8px;">Registration OTP</h2>
        <p style="color: #666; font-size: 14px;">Use the code below to complete your registration:</p>
        <div style="text-align: center; margin: 24px 0;">
            <span style="font-size: 36px; font-weight: bold; letter-spacing: 12px; color: #1d4ed8;">{{ $otp }}</span>
        </div>
        <p style="color: #666; font-size: 13px;">This OTP expires in <strong>10 minutes</strong>.</p>
        <p style="color: #999; font-size: 12px;">If you did not request this, please ignore this email.</p>
    </div>
</body>
</html>