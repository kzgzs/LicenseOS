<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
<div style="max-width: 480px; margin: 40px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
    {{-- Header --}}
    <div style="background: #4f46e5; padding: 24px; text-align: center;">
        <h1 style="margin: 0; color: #fff; font-size: 22px;">{{ $siteName }}</h1>
    </div>

    {{-- Body --}}
    <div style="padding: 32px 28px;">
        <p style="font-size: 16px; color: #333; margin: 0 0 8px;">Hello,</p>
        <p style="font-size: 15px; color: #555; margin: 0 0 24px;">Your verification code is:</p>

        {{-- Code Block --}}
        <div style="background: #f0f0ff; border: 2px dashed #4f46e5; border-radius: 8px; padding: 20px; text-align: center; margin: 0 0 24px;">
            <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #4f46e5; font-family: 'Courier New', monospace;">{{ $code }}</span>
        </div>

        <p style="font-size: 14px; color: #888; margin: 0 0 8px;">This code is valid for <strong>5 minutes</strong>.</p>
        <p style="font-size: 14px; color: #888; margin: 0;">If you did not request this code, please ignore this email.</p>
    </div>

    {{-- Footer --}}
    <div style="background: #f9fafb; padding: 16px 28px; border-top: 1px solid #eee; text-align: center;">
        <p style="font-size: 12px; color: #aaa; margin: 0;">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
