<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Show forgot password form.
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset email.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'captcha' => 'required|string',
        ]);

        // Validate captcha
        $sessionCode = session('captcha_code', '');
        if (strtolower($request->input('captcha')) !== $sessionCode) {
            return back()->withErrors(['captcha' => 'The verification code is incorrect.'])->onlyInput('email');
        }
        session()->forget('captcha_code');

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email address.'])->onlyInput('email');
        }

        // Generate reset token
        $token = Str::random(64);

        // Delete old tokens
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Insert new token
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Build reset URL
        $resetUrl = route('password.reset', ['token' => $token, 'email' => $request->email]);

        // Send email
        try {
            \Illuminate\Support\Facades\Mail::send('auth.emails.reset', ['resetUrl' => $resetUrl, 'user' => $user], function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Reset Your Password - LicenseOS');
            });

            return back()->with('status', 'Password reset link has been sent to your email. Please check your inbox.');
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Unable to send email. Please contact the administrator.'])->onlyInput('email');
        }
    }

    /**
     * Show reset password form.
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->get('email', ''),
        ]);
    }

    /**
     * Reset the password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return back()->withErrors(['email' => 'Invalid or expired reset token.']);
        }

        // Check token expiration (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'Reset token has expired. Please request a new one.']);
        }

        // Verify token
        if (!Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid reset token.']);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        $user->update(['password' => $request->password]); // 'hashed' cast auto-hashes

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'Password has been reset successfully. Please sign in with your new password.');
    }
}
