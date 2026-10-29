<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $emailVerifyService,
    ) {}

    /**
     * Send an email verification code.
     * POST /api/email/send-code
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // Rate limit: max 1 email per minute, 5 per hour
        $key = 'email_send:' . $email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'error' => 'Please wait 1 minute before requesting another code.',
            ], 429);
        }

        $hourKey = 'email_hour:' . $email;
        if (RateLimiter::tooManyAttempts($hourKey, 5)) {
            return response()->json([
                'error' => 'Maximum 5 emails per hour. Please try again later.',
            ], 429);
        }

        if (!$this->emailVerifyService->isEnabled()) {
            return response()->json([
                'error' => 'Email verification is not enabled.',
            ], 503);
        }

        try {
            $this->emailVerifyService->sendCode($email);
            RateLimiter::hit($key, 60);
            RateLimiter::hit($hourKey, 3600);

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your email.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify an email verification code.
     * POST /api/email/verify
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|digits:6',
        ]);

        $valid = $this->emailVerifyService->verifyCode(
            $request->input('email'),
            $request->input('code'),
        );

        return response()->json([
            'valid' => $valid,
        ]);
    }
}
