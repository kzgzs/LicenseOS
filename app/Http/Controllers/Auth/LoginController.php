<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'captcha' => 'required|string',
        ]);

        // Validate captcha (case-insensitive)
        $sessionCode = session('captcha_code', '');
        if (strtolower($request->input('captcha')) !== $sessionCode) {
            return back()->withErrors([
                'captcha' => __('auth.captcha_incorrect'),
            ])->onlyInput('email');
        }

        // Clear used captcha
        session()->forget('captcha_code');

        $credentials = $request->only('email', 'password');

        // Check if user exists by email first
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return back()->withErrors([
                'email' => __('auth.email_not_registered'),
            ])->onlyInput('email');
        }

        // Check password
        if (!Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'password' => __('auth.incorrect_password'),
            ])->onlyInput('email');
        }

        // Log the user in
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Store credential fingerprint for CredentialGuard middleware
        $request->session()->put('credential_fingerprint', hash('sha256', $user->email . '|' . $user->getAuthPassword()));

        if ($user->isSuperAdmin()) {
            return redirect('/admin');
        }
        if ($user->isDeveloper()) {
            return redirect()->route('developer.dashboard');
        }

        return redirect()->route('marketplace');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
