<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; margin: 0; padding: 40px 20px; }
        .container { max-width: 480px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a2332, #0d9488); padding: 32px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 22px; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 13px; }
        .body { padding: 32px; }
        .otp-box { background: #f0fdfa; border: 2px dashed #0d9488; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 36px; font-weight: 800; color: #0d9488; letter-spacing: 8px; margin: 0; }
        .otp-label { font-size: 12px; color: #64748b; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .note { font-size: 13px; color: #64748b; line-height: 1.6; }
        .footer { padding: 20px 32px; background: #f8fafc; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nepal Smart Travel</h1>
            <p>Partner Verification</p>
        </div>
        <div class="body">
            <p style="color: #334155; font-size: 15px;">Hi <strong>{{ $name }}</strong>,</p>
            <p class="note">Use the code below to verify your email address and complete your partner registration:</p>
            <div class="otp-box">
                <p class="otp-code">{{ $otp }}</p>
                <p class="otp-label">Verification Code</p>
            </div>
            <p class="note">This code expires in <strong>15 minutes</strong>. If you didn't create a partner account, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            Nepal Smart Travel & Local Intelligence Platform &copy; {{ date('Y') }}
        </div>
    </div>
</body>
</html>
