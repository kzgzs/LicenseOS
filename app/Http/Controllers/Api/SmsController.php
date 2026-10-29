<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SmsController extends Controller
{
    public function __construct(
        protected SmsService $smsService,
    ) {}

    /**
     * Send a verification code via SMS.
     * POST /api/sms/send-code
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
        ], [
            'phone.regex' => 'Please enter a valid Chinese mobile number.',
        ]);

        $phone = $request->input('phone');

        // Rate limit: max 1 SMS per minute, 5 per hour
        $key = 'sms_send:' . $phone;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'error' => 'Please wait 1 minute before requesting another code.',
            ], 429);
        }

        $hourKey = 'sms_hour:' . $phone;
        if (RateLimiter::tooManyAttempts($hourKey, 5)) {
            return response()->json([
                'error' => 'Maximum 5 SMS per hour. Please try again later.',
            ], 429);
        }

        if (!$this->smsService->isEnabled()) {
            return response()->json([
                'error' => 'SMS service is not configured.',
            ], 503);
        }

        try {
            $this->smsService->sendVerificationCode($phone);
            RateLimiter::hit($key, 60);
            RateLimiter::hit($hourKey, 3600);

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a SMS code.
     * POST /api/sms/verify
     */
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|digits:6',
        ]);

        $valid = $this->smsService->verifyCode(
            $request->input('phone'),
            $request->input('code'),
        );

        return response()->json([
            'valid' => $valid,
        ]);
    }
}
