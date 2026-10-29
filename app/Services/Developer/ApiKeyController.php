<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /**
     * List API keys for the current tenant.
     */
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            $apiKeys = $request->user()->isSuperAdmin()
                ? ApiKey::latest()->get()
                : collect();
            return view('developer.api-keys.index', compact('apiKeys'));
        }
        $apiKeys = $tenant->apiKeys()->latest()->get();
        return view('developer.api-keys.index', compact('apiKeys'));
    }

    /**
     * Generate a new API key.
     */
    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return back()->with('error', __('developer.api_key_no_tenant'));
        }

        // Limit active keys per tenant
        $activeCount = $tenant->apiKeys()->where('active', true)->count();
        if ($activeCount >= 5) {
            return back()->with('error', __('developer.api_key_limit'));
        }

        $apiKey = ApiKey::generateFor($tenant);

        return back()->with('success', __('developer.api_key_created') . $apiKey->key);
    }

    /**
     * Revoke an API key.
     */
    public function destroy(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $apiKey->deactivate();

        return back()->with('success', 'API key revoked.');
    }
}
