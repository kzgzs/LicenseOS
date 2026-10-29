<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ApiQuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiQuotaMiddleware
{
    public function __construct(
        protected ApiQuotaService $quotaService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'error' => 'API key required',
                'code' => 'missing_api_key',
            ], 401);
        }

        $apiKey = ApiKey::where('key', $key)->where('active', true)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'code' => 'invalid_api_key',
            ], 401);
        }

        // Check quota
        if (!$this->quotaService->hasQuota($apiKey)) {
            // Allow overage if rate > 0, otherwise reject
            if ($apiKey->overage_rate <= 0) {
                return response()->json([
                    'error' => 'API quota exceeded',
                    'code' => 'quota_exceeded',
                    'quota' => $apiKey->quota,
                    'used' => $apiKey->used_quota,
                    'upgrade_url' => '/api/v3/plans',
                ], 429);
            }
        }

        // Bind API key to request for downstream use
        $request->attributes->set('api_key', $apiKey);

        $startTime = microtime(true);
        $response = $next($request);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Record usage
        $this->quotaService->consume(
            $apiKey,
            $request->path(),
            $request->method(),
            $request->ip(),
            $responseTime
        );

        // Add quota headers
        $response->headers->set('X-RateLimit-Limit', $apiKey->quota);
        $response->headers->set('X-RateLimit-Remaining', max(0, $apiKey->quota - $apiKey->used_quota));
        $response->headers->set('X-RateLimit-Reset', $apiKey->quota_reset_at?->timestamp ?? '');

        return $response;
    }
}
