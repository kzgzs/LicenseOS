<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px;">
    @if($status === 'approved')
        <h2 style="color: #16a34a;">{{ __('mail.verification_approved_title') }}</h2>
        <p>{{ __('mail.verification_approved_body', ['company' => $user->company_name]) }}</p>
    @else
        <h2 style="color: #dc2626;">{{ __('mail.verification_rejected_title') }}</h2>
        <p>{{ __('mail.verification_rejected_body') }}</p>
        @if($note)
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px; margin: 16px 0;">
                <p style="color: #991b1b; font-size: 14px; margin: 0;"><strong>{{ __('mail.verification_reason') }}:</strong> {{ $note }}</p>
            </div>
        @endif
        <p>{{ __('mail.verification_resubmit') }}</p>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('projects.verification') }}"
           style="background: #4f46e5; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            {{ __('mail.verification_button') }}
        </a>
    </div>
</div>
</body>
</html>
