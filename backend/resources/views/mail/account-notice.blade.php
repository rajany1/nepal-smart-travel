<!DOCTYPE html>
<html lang="en" style="background:#f1f5f9">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<div style="max-width:560px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
        <div style="background:{{ $severity === 'critical' ? '#b91c1c' : ($severity === 'high' ? '#ea580c' : '#0f766e') }};padding:20px 24px;">
            <div style="font-size:20px;font-weight:700;color:#ffffff;">{{ $title }}</div>
        </div>
        <div style="padding:24px;">
            <div style="font-size:14px;line-height:1.7;color:#334155;">
                <p>Hello,</p>
                <p>{{ $guidance }}</p>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">
                <p style="font-size:12px;color:#64748b;">This is a system-generated notice regarding your account on Nepal Smart Travel. If you believe this is a mistake, please contact support and include your registered email address.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>