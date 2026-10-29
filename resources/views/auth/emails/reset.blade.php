<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 32px;">
        <h2 style="color: #4f46e5; margin-bottom: 16px;">Password Reset</h2>
        <p>Hi {{ $user->name }},</p>
        <p>You requested to reset your password. Click the button below to set a new password:</p>
        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ $resetUrl }}"
               style="background: #4f46e5; color: white; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                Reset Password
            </a>
        </div>
        <p style="font-size: 13px; color: #666;">This link will expire in 60 minutes. If you didn't request this, please ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #999;">If the button doesn't work, copy this link:<br>{{ $resetUrl }}</p>
    </div>
</body>
</html>
