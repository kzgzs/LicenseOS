<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = $request->header('X-API-KEY') ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'API key is required. Pass it via X-API-KEY header.',
            ], 401);
        }

        $apiKey = ApiKey::where('key', $key)
            ->where('active', true)
            ->with('tenant')
            ->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid or inactive API key.',
            ], 403);
        }

        if (!$apiKey->tenant->isActive()) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Tenant account is suspended.',
            ], 403);
        }

        $request->merge(['_api_key' => $apiKey]);
        app()->instance('current_tenant', $apiKey->tenant);

        return $next($request);
    }
}
