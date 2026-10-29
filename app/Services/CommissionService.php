<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

class CommissionService
{
    /**
     * Tiered commission rates for developers based on monthly sales.
     */
    public const DEVELOPER_TIERS = [
        'platinum' => ['min' => 100000, 'rate' => 12, 'label' => 'Platinum'],
        'gold'     => ['min' => 20000,  'rate' => 15, 'label' => 'Gold'],
        'silver'   => ['min' => 5000,   'rate' => 18, 'label' => 'Silver'],
        'standard' => ['min' => 0,      'rate' => 20, 'label' => 'Standard'],
    ];

    /**
     * Calculate and record commission for a paid order.
     * Supports tiered developer commission and affiliate tracking.
     */
    public function calculateForOrder(Order $order): Commission
    {
        $tenant = $order->tenant;

        // 1. Determine developer tier based on current month sales
        $monthlySales = $this->getMonthlySales($tenant->id);
        $tier = $this->getDeveloperTier($monthlySales);
        $rate = $tier['rate'];

        // Use tenant's custom rate if set and higher priority
        if ($tenant->commission_rate && $tenant->commission_rate > 0) {
            $rate = (float) $tenant->commission_rate;
            $tier['rate'] = $rate;
        }

        // 2. Calculate platform fee
        $platformFee = $order->amount * ($rate / 100);

        // 3. Calculate affiliate commission if referred
        $affiliateUserId = $order->referred_by;
        $affiliateRate = 0;
        $affiliateFee = 0;

        if ($affiliateUserId) {
            $affiliateRate = (float) $this->getAffiliateRate();
            // Affiliate fee comes from platform fee, not from developer income
            $affiliateFee = $order->amount * ($affiliateRate / 100);
            // Ensure affiliate fee doesn't exceed platform fee
            $affiliateFee = min($affiliateFee, $platformFee);
        }

        // 4. Developer income = order amount - platform fee
        $developerIncome = $order->amount - $platformFee;

        // 5. Create commission record
        $commission = Commission::create([
            'order_id' => $order->id,
            'tenant_id' => $tenant->id,
            'affiliate_user_id' => $affiliateUserId,
            'platform_fee' => round($platformFee, 2),
            'developer_income' => round($developerIncome, 2),
            'affiliate_fee' => round($affiliateFee, 2),
            'affiliate_rate' => $affiliateRate,
            'rate' => $rate,
            'developer_tier' => $this->getTierKey($monthlySales),
        ]);

        // 6. Update tenant balance
        $tenant->increment('balance', $developerIncome);

        // 7. Update affiliate balance if applicable
        if ($affiliateUserId && $affiliateFee > 0) {
            $affiliateUser = User::find($affiliateUserId);
            if ($affiliateUser) {
                $affiliateUser->increment('affiliate_balance', $affiliateFee);
            }
        }

        return $commission;
    }

    /**
     * Get pending payout amount for a tenant.
     */
    public function getPendingBalance(int $tenantId): float
    {
        $tenant = Tenant::find($tenantId);
        return $tenant ? $tenant->availableBalance() : 0;
    }

    /**
     * Get current month sales for a tenant.
     */
    public function getMonthlySales(int $tenantId): float
    {
        return (float) Order::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    /**
     * Determine developer tier based on monthly sales.
     */
    public function getDeveloperTier(float $monthlySales): array
    {
        foreach (self::DEVELOPER_TIERS as $tier) {
            if ($monthlySales >= $tier['min']) {
                return $tier;
            }
        }
        return self::DEVELOPER_TIERS['standard'];
    }

    /**
     * Get tier key based on monthly sales.
     */
    public function getTierKey(float $monthlySales): string
    {
        foreach (self::DEVELOPER_TIERS as $key => $tier) {
            if ($monthlySales >= $tier['min']) {
                return $key;
            }
        }
        return 'standard';
    }

    /**
     * Get affiliate commission rate from settings.
     */
    public function getAffiliateRate(): float
    {
        return (float) \App\Models\SiteSetting::get('affiliate_rate', '10');
    }

    /**
     * Get affiliate cookie duration in days.
     */
    public function getAffiliateCookieDays(): int
    {
        return (int) \App\Models\SiteSetting::get('affiliate_cookie_days', '30');
    }
}
