<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenantId = $request->header('X-Tenant-ID')
            ?? $request->input('tenant_id')
            ?? $request->user()?->tenant_id;

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant not specified'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant || !$tenant->isActive()) {
            return response()->json(['message' => 'Invalid or inactive tenant'], 403);
        }

        app()->instance('current_tenant', $tenant);

        return $next($request);
    }
}
