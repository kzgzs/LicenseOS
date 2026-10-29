<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px;">
    <h2 style="color: #4f46e5;">{{ __('mail.review_greeting', ['name' => $reviewerName]) }}</h2>
    <p>{{ __('mail.review_body', ['project' => $contract->project->title]) }}</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('projects.contracts.show', $contract->project) }}"
           style="background: #4f46e5; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            {{ __('mail.review_button') }}
        </a>
    </div>
    <p style="color: #666; font-size: 13px;">{{ __('mail.review_footer') }}</p>
</div>
</body>
</html>
