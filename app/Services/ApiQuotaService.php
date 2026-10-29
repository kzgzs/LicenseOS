<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use App\Models\RevenueRecord;
use Illuminate\Support\Facades\Cache;

class ApiQuotaService
{
    /**
     * Check if API key has remaining quota.
     */
    public function hasQuota(ApiKey $apiKey): bool
    {
        return $apiKey->hasQuotaRemaining();
    }

    /**
     * Consume one API call quota.
     */
    public function consume(ApiKey $apiKey, string $endpoint, string $method, string $ip = null, int $responseTime = 0): ApiUsageLog
    {
        $apiKey->consumeQuota();

        $log = ApiUsageLog::create([
            'api_key_id' => $apiKey->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'response_code' => 200,
            'response_time_ms' => $responseTime,
            'ip' => $ip,
        ]);

        // Check if over quota → charge overage
        if ($apiKey->used_quota > $apiKey->quota && $apiKey->overage_rate > 0) {
            $this->chargeOverage($apiKey);
        }

        return $log;
    }

    /**
     * Charge overage fee.
     */
    protected function chargeOverage(ApiKey $apiKey): void
    {
        $overageAmount = $apiKey->overage_rate;
        RevenueRecord::record('api', $overageAmount, $apiKey, 'API overage: key #' . $apiKey->id);
    }

    /**
     * Log a failed API request.
     */
    public function logFailure(ApiKey $apiKey, string $endpoint, string $method, int $responseCode, string $ip = null): ApiUsageLog
    {
        return ApiUsageLog::create([
            'api_key_id' => $apiKey->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'response_code' => $responseCode,
            'response_time_ms' => 0,
            'ip' => $ip,
        ]);
    }

    /**
     * Reset monthly quotas for all API keys.
     */
    public function resetMonthlyQuotas(): int
    {
        $keys = ApiKey::where('active', true)->get();
        $count = 0;

        foreach ($keys as $key) {
            // Calculate overage bill before reset
            if ($key->used_quota > $key->quota) {
                $overageTotal = $key->overageAmount();
                if ($overageTotal > 0) {
                    RevenueRecord::record('api', $overageTotal, $key, 'Monthly API overage settlement');
                }
            }

            $key->resetQuota();
            $count++;
        }

        return $count;
    }

    /**
     * Get usage statistics for an API key.
     */
    public function getUsageStats(ApiKey $apiKey): array
    {
        $todayUsage = ApiUsageLog::forKey($apiKey->id)->today()->count();
        $monthUsage = ApiUsageLog::forKey($apiKey->id)->thisMonth()->count();

        $avgResponseTime = ApiUsageLog::forKey($apiKey->id)
            ->thisMonth()
            ->avg('response_time_ms') ?? 0;

        $errorRate = $this->calculateErrorRate($apiKey);

        return [
            'key_id' => $apiKey->id,
            'tier' => $apiKey->tier,
            'quota' => $apiKey->quota,
            'used' => $apiKey->used_quota,
            'remaining' => max(0, $apiKey->quota - $apiKey->used_quota),
            'usage_percent' => $apiKey->quotaUsagePercent(),
            'today_calls' => $todayUsage,
            'month_calls' => $monthUsage,
            'avg_response_ms' => round($avgResponseTime),
            'error_rate' => $errorRate,
            'overage_cost' => $apiKey->overageAmount(),
        ];
    }

    /**
     * Calculate error rate for an API key this month.
     */
    protected function calculateErrorRate(ApiKey $apiKey): float
    {
        $total = ApiUsageLog::forKey($apiKey->id)->thisMonth()->count();
        if ($total === 0) return 0;

        $errors = ApiUsageLog::forKey($apiKey->id)
            ->thisMonth()
            ->where('response_code', '>=', 400)
            ->count();

        return round(($errors / $total) * 100, 2);
    }

    /**
     * Upgrade API key tier.
     */
    public function upgradeTier(ApiKey $apiKey, string $tier): void
    {
        $config = $this->getTierConfig($tier);
        $apiKey->update([
            'tier' => $tier,
            'quota' => $config['quota'],
            'overage_rate' => $config['overage_rate'],
        ]);
    }

    /**
     * Get tier configuration.
     */
    public function getTierConfig(string $tier): array
    {
        return match ($tier) {
            'free' => ['quota' => 1000, 'overage_rate' => 0.00],
            'basic' => ['quota' => 10000, 'overage_rate' => 0.005],
            'pro' => ['quota' => 100000, 'overage_rate' => 0.002],
            'enterprise' => ['quota' => 1000000, 'overage_rate' => 0.001],
            default => ['quota' => 1000, 'overage_rate' => 0.00],
        };
    }

    /**
     * Get top API endpoints by usage.
     */
    public function getTopEndpoints(int $limit = 10): array
    {
        return ApiUsageLog::thisMonth()
            ->selectRaw('endpoint, COUNT(*) as calls, AVG(response_time_ms) as avg_time')
            ->groupBy('endpoint')
            ->orderByDesc('calls')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
