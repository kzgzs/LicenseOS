<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired</title>
    <meta http-equiv="refresh" content="3;url={{ url('/login') }}">
    <style>
        body { font-family: -apple-system, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f3f4f6; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .code { font-size: 72px; font-weight: 800; color: #f59e0b; margin: 0; }
        h2 { margin: 16px 0 8px; color: #1f2937; }
        p { color: #6b7280; margin: 8px 0; }
        .spinner { margin: 20px auto; width: 30px; height: 30px; border: 3px solid #e5e7eb; border-top-color: #f59e0b; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">419</p>
        <h2>Session Expired</h2>
        <p>Your session has expired. Please log in again.</p>
        <p>Redirecting to login page in 3 seconds...</p>
        <div class="spinner"></div>
    </div>
    <script>setTimeout(function(){window.location.href="{{ url('/login') }}";},3000);</script>
</body>
</html>
