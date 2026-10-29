<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline - {{ \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform')) }}</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; color: #374151; }
    .box { text-align: center; padding: 2rem; }
    h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
    p { color: #6b7280; }
    a { color: #4f46e5; }
  </style>
</head>
<body>
  <div class="box">
    <h1>You are offline</h1>
    <p>Please check your network connection and try again.</p>
    <a href="/">Go to homepage</a>
  </div>
</body>
</html>
