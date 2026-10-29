<?php

namespace App\Services;

use App\Models\PlatformTier;
use App\Models\Tenant;
use App\Models\User;

class TierService
{
    /**
     * Evaluate and update developer tier based on performance.
     */
    public function evaluateDeveloperTier(Tenant $tenant): string
    {
        $totalSales = $tenant->total_sales;
        $totalRevenue = $tenant->total_revenue;

        $level = match (true) {
            $totalSales >= 1000 && $totalRevenue >= 100000 => 'partner',
            $totalSales >= 200 && $totalRevenue >= 20000 => 'top',
            $totalSales >= 20 && $totalRevenue >= 1000 => 'growth',
            default => 'new',
        };

        if ($tenant->developer_level !== $level) {
            $tenant->update(['developer_level' => $level]);
        }

        return $level;
    }

    /**
     * Evaluate and update user tier.
     */
    public function evaluateUserTier(User $user): string
    {
        $orderCount = $user->orders()->count();
        $totalSpent = $user->orders()->sum('amount');
        $isMember = $user->isMemberActive();

        $tier = match (true) {
            $isMember && $totalSpent >= 500 => 'premium',
            $isMember || $totalSpent >= 100 => 'member',
            default => 'basic',
        };

        if ($user->tier !== $tier) {
            $user->update(['tier' => $tier]);
        }

        return $tier;
    }

    /**
     * Get effective commission rate for a developer tier.
     */
    public function getEffectiveCommissionRate(Tenant $tenant): float
    {
        $baseRate = (float) $tenant->commission_rate;
        $tier = PlatformTier::developer()->where('level', $this->tierLevelNumber($tenant->developer_level))->first();

        if ($tier) {
            return $tier->effectiveCommissionRate($baseRate);
        }

        return $baseRate;
    }

    /**
     * Get API quota bonus for a developer.
     */
    public function getApiQuotaBonus(Tenant $tenant): int
    {
        $tier = PlatformTier::developer()->where('level', $this->tierLevelNumber($tenant->developer_level))->first();
        return $tier?->api_quota_bonus ?? 0;
    }

    /**
     * Check if a developer has priority ranking.
     */
    public function hasPriorityRanking(Tenant $tenant): bool
    {
        $tier = PlatformTier::developer()->where('level', $this->tierLevelNumber($tenant->developer_level))->first();
        return $tier?->priority_ranking ?? false;
    }

    /**
     * Get benefits for a specific tier level.
     */
    public function getTierBenefits(string $type, string $level): array
    {
        $tier = PlatformTier::where('type', $type)
            ->where('level', $this->tierLevelNumber($level))
            ->first();

        return $tier?->benefits ?? [];
    }

    /**
     * Convert tier name to level number.
     */
    protected function tierLevelNumber(string $level): int
    {
        return match ($level) {
            'new', 'basic' => 0,
            'growth', 'member' => 1,
            'top', 'premium' => 2,
            'partner' => 3,
            default => 0,
        };
    }

    /**
     * Batch evaluate all developers.
     */
    public function evaluateAllDevelopers(): array
    {
        $results = [];
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $oldLevel = $tenant->developer_level;
            $newLevel = $this->evaluateDeveloperTier($tenant);
            if ($oldLevel !== $newLevel) {
                $results[] = [
                    'tenant_id' => $tenant->id,
                    'old_level' => $oldLevel,
                    'new_level' => $newLevel,
                ];
            }
        }

        return $results;
    }

    /**
     * Get tier overview for dashboard.
     */
    public function getTierOverview(): array
    {
        return [
            'developer_tiers' => PlatformTier::developer()->orderBy('level')->get()->toArray(),
            'user_tiers' => PlatformTier::user()->orderBy('level')->get()->toArray(),
            'developer_distribution' => Tenant::where('status', 'active')
                ->selectRaw('developer_level, COUNT(*) as count')
                ->groupBy('developer_level')
                ->pluck('count', 'developer_level')
                ->toArray(),
        ];
    }
}
