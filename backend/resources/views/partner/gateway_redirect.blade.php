<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to payment…</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; display: grid; place-items: center; min-height: 100vh; }
        .box { text-align: center; background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,.08); }
        .spinner { width: 36px; height: 36px; border: 4px solid #10b981; border-top-color: transparent; border-radius: 50%; margin: 0 auto 16px; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { color: #64748b; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <p>Redirecting to the payment gateway…</p>
    </div>
    {!! $html !!}
</body>
</html>