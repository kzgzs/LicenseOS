<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Access Denied</title>
    <meta http-equiv="refresh" content="3;url=/">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #1f2937;
        }
        .container {
            text-align: center;
            padding: 48px 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 90%;
        }
        .code {
            font-size: 72px;
            font-weight: 800;
            color: #dc2626;
            line-height: 1;
            margin-bottom: 8px;
        }
        h1 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #111827;
        }
        p {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .message {
            font-size: 14px;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .redirect {
            font-size: 14px;
            color: #6b7280;
        }
        .redirect a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .redirect a:hover { text-decoration: underline; }
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #e5e7eb;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">403</div>
        <h1>Access Denied</h1>
        <p>{{ $exception->getMessage() ?: 'You do not have permission to access this page.' }}</p>
        <p class="redirect">
            <span class="spinner"></span>
            Redirecting to <a href="/">homepage</a> in 3 seconds...
        </p>
    </div>
    <script>
        setTimeout(function() { window.location.href = '/'; }, 3000);
    </script>
</body>
</html>
