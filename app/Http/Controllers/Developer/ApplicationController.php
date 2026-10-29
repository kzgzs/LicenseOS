<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperApplication;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ApplicationController extends Controller
{
    /**
     * Show the developer application form.
     */
    public function create()
    {
        return view('developer.apply');
    }

    /**
     * Submit a developer application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
            'tenant_name' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
            'captcha' => 'required|string',
        ]);

        // Validate captcha (case-insensitive)
        $sessionCode = session('captcha_code', '');
        if (strtolower($request->input('captcha')) !== $sessionCode) {
            return back()->withErrors([
                'captcha' => 'The verification code is incorrect.',
            ])->withInput();
        }
        session()->forget('captcha_code');

        // Check for duplicate applications
        $existing = DeveloperApplication::where('email', $validated['email'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->withErrors(['email' => 'You already have a pending application.']);
        }

        // Create application for review
        $application = DeveloperApplication::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        // Store password temporarily in session for auto-approval flow
        session(['pending_registration' => [
            'application_id' => $application->id,
            'password' => Hash::make($validated['password']),
            'tenant_name' => $validated['tenant_name'],
        ]]);

        return redirect()->route('apply.submitted');
    }

    /**
     * Show the application submitted confirmation.
     */
    public function submitted()
    {
        return view('developer.apply-submitted');
    }

    /**
     * Approve a developer application (called by admin).
     */
    public static function approve(DeveloperApplication $application, string $tenantName): array
    {
        // Create tenant
        $tenant = Tenant::create([
            'name' => $tenantName,
            'email' => $application->email,
            'commission_rate' => (int) SiteSetting::get('default_commission_rate', '10'),
            'status' => 'active',
        ]);

        // Generate API key for the tenant
        $apiKey = ApiKey::generateFor($tenant);

        // Create user account
        $pendingData = session('pending_registration') ?? [];
        $password = $pendingData['password'] ?? Hash::make('changeme123');

        $user = User::create([
            'name' => $application->name,
            'email' => $application->email,
            'password' => $password,
            'role' => 'developer',
            'tenant_id' => $tenant->id,
        ]);

        // Update application status
        $application->update(['status' => 'approved']);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'api_key' => $apiKey,
        ];
    }
}
