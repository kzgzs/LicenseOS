<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Unified registration with role selection (customer or developer).
     */
    public function register(Request $request)
    {
        $request->validate([
            'role' => 'required|in:customer,developer',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'tenant_name' => 'required_if:role,developer|nullable|string|max:255',
            'captcha' => 'required|string',
            'agree_terms' => 'accepted',
        ], [
            'agree_terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy.',
        ]);

        // Validate captcha
        $sessionCode = session('captcha_code', '');
        if (strtolower($request->input('captcha')) !== $sessionCode) {
            return back()->withErrors(['captcha' => 'The verification code is incorrect.'])->onlyInput('email', 'name', 'role', 'tenant_name');
        }
        session()->forget('captcha_code');

        $role = $request->input('role');
        $tenantId = null;

        // If developer, create a Tenant record
        if ($role === 'developer' && $request->filled('tenant_name')) {
            $tenant = Tenant::create([
                'name' => $request->input('tenant_name'),
                'email' => $request->input('email'),
                'commission_rate' => (int) SiteSetting::get('default_commission_rate', '10'),
                'status' => 'active',
            ]);
            $tenantId = $tenant->id;
        }

        // Create user (password auto-hashed by model cast)
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'role' => $role,
            'tenant_id' => $tenantId,
        ]);

        // Auto-login after registration
        Auth::login($user);
        $request->session()->regenerate();

        // Store credential fingerprint for CredentialGuard middleware
        $request->session()->put('credential_fingerprint', hash('sha256', $user->email . '|' . $user->getAuthPassword()));

        // Redirect based on role
        if ($user->isDeveloper()) {
            return redirect()->route('developer.dashboard')->with('success', __('auth.welcome_developer'));
        }

        return redirect()->route('marketplace')->with('success', __('auth.welcome_customer'));
    }
}
