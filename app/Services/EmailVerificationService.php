<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email Verification Service.
 *
 * Generates and verifies email-based verification codes.
 * Uses SMTP settings from SiteSetting/env for sending.
 */
class EmailVerificationService
{
    /**
     * Check if email verification is enabled.
     */
    public function isEnabled(): bool
    {
        return SiteSetting::get('email_verify_enabled', '0') === '1';
    }

    /**
     * Generate and send a verification code to the given email.
     */
    public function sendCode(string $email): string
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Email verification is not enabled.');
        }

        $code = (string) random_int(100000, 999999);

        // Store code in cache for 5 minutes
        Cache::put("email_code:{$email}", $code, now()->addMinutes(5));

        $siteName = SiteSetting::get('site_name', config('app.name', 'Platform'));
        $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
        $fromName = env('MAIL_FROM_NAME', $siteName);

        try {
            Mail::send('emails.verify-code', [
                'code' => $code,
                'siteName' => $siteName,
                'email' => $email,
            ], function ($message) use ($email, $fromAddress, $fromName, $siteName) {
                $message->from($fromAddress, $fromName)
                    ->to($email)
                    ->subject("[{$siteName}] Your Verification Code");
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send email verification code: ' . $e->getMessage(), ['email' => $email]);
            throw new \RuntimeException('Failed to send verification email. Please check SMTP settings.');
        }

        Log::info('Email verification code sent', ['email' => $email]);

        return $code;
    }

    /**
     * Verify an email verification code.
     */
    public function verifyCode(string $email, string $code): bool
    {
        $cached = Cache::get("email_code:{$email}");
        if (!$cached) {
            return false;
        }

        if ($cached !== $code) {
            return false;
        }

        Cache::forget("email_code:{$email}");
        return true;
    }
}
