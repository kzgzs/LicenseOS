<?php

namespace App\Services;

use App\Models\RevenueRecord;
use App\Models\Ad;
use App\Models\Commission;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

class RevenueService
{
    /**
     * Get comprehensive revenue dashboard data.
     */
    public function getDashboard(string $period = 'month'): array
    {
        $startDate = $this->getPeriodStart($period);
        $endDate = now();

        return [
            'total_revenue' => $this->getTotalRevenue($startDate, $endDate),
            'revenue_by_source' => $this->getRevenueBySource($startDate, $endDate),
            'daily_revenue' => $this->getDailyRevenue($startDate, $endDate),
            'top_products' => $this->getTopRevenueProducts($startDate, $endDate),
            'growth_rate' => $this->calculateGrowthRate($period),
            'projections' => $this->getProjections(),
        ];
    }

    /**
     * Get total revenue for a period.
     */
    public function getTotalRevenue(Carbon $from, Carbon $to): float
    {
        return (float) RevenueRecord::inPeriod($from->toDateString(), $to->toDateString())->sum('amount');
    }

    /**
     * Get revenue broken down by source.
     */
    public function getRevenueBySource(Carbon $from, Carbon $to): array
    {
        return RevenueRecord::inPeriod($from->toDateString(), $to->toDateString())
            ->selectRaw('source, SUM(amount) as total, COUNT(*) as transactions')
            ->groupBy('source')
            ->get()
            ->keyBy('source')
            ->toArray();
    }

    /**
     * Get daily revenue trend.
     */
    public function getDailyRevenue(Carbon $from, Carbon $to): array
    {
        return RevenueRecord::inPeriod($from->toDateString(), $to->toDateString())
            ->selectRaw('period_date, SUM(amount) as total')
            ->groupBy('period_date')
            ->orderBy('period_date')
            ->pluck('total', 'period_date')
            ->toArray();
    }

    /**
     * Get top revenue-generating products.
     */
    public function getTopRevenueProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return Commission::whereBetween('created_at', [$from, $to])
            ->join('orders', 'commissions.order_id', '=', 'orders.id')
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->selectRaw('products.name, products.id as product_id, SUM(commissions.platform_fee) as revenue, COUNT(*) as sales')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Calculate growth rate compared to previous period.
     */
    public function calculateGrowthRate(string $period): float
    {
        $currentStart = $this->getPeriodStart($period);
        $previousStart = $this->getPeriodStart($period)->sub($this->getPeriodInterval($period));

        $currentRevenue = $this->getTotalRevenue($currentStart, now());
        $previousRevenue = $this->getTotalRevenue($previousStart, $currentStart);

        if ($previousRevenue == 0) return 100;
        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
    }

    /**
     * Revenue projections based on current trends.
     */
    public function getProjections(): array
    {
        $last30Days = $this->getTotalRevenue(now()->subDays(30), now());
        $dailyAvg = $last30Days / 30;

        return [
            'daily_avg' => round($dailyAvg, 2),
            'monthly_projected' => round($dailyAvg * 30, 2),
            'quarterly_projected' => round($dailyAvg * 90, 2),
            'annual_projected' => round($dailyAvg * 365, 2),
        ];
    }

    /**
     * Get ad revenue summary.
     */
    public function getAdRevenue(Carbon $from, Carbon $to): array
    {
        $records = RevenueRecord::bySource('ad')->inPeriod($from->toDateString(), $to->toDateString());

        return [
            'total' => $records->sum('amount'),
            'transactions' => $records->count(),
            'active_ads' => Ad::active()->count(),
            'total_impressions' => Ad::active()->sum('impressions'),
            'total_clicks' => Ad::active()->sum('clicks'),
        ];
    }

    /**
     * Get API revenue summary.
     */
    public function getApiRevenue(Carbon $from, Carbon $to): array
    {
        $records = RevenueRecord::bySource('api')->inPeriod($from->toDateString(), $to->toDateString());

        return [
            'total' => $records->sum('amount'),
            'transactions' => $records->count(),
        ];
    }

    /**
     * Get hot keywords (from ads targeting).
     */
    public function getHotKeywords(int $limit = 20): array
    {
        return Ad::active()
            ->whereNotNull('target_keywords')
            ->selectRaw('target_keywords, SUM(bid_price) as total_bid, COUNT(*) as campaigns')
            ->groupBy('target_keywords')
            ->orderByDesc('total_bid')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get bidding ranking overview.
     */
    public function getBiddingOverview(): array
    {
        return [
            'total_active_bids' => Ad::active()->byType('search_boost')->count(),
            'avg_bid_price' => Ad::active()->byType('search_boost')->avg('bid_price') ?? 0,
            'max_bid_price' => Ad::active()->byType('search_boost')->max('bid_price') ?? 0,
            'total_ad_spend_today' => RevenueRecord::bySource('ad')->today()->sum('amount'),
        ];
    }

    protected function getPeriodStart(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    protected function getPeriodInterval(string $period): \DateInterval
    {
        return match ($period) {
            'day' => new \DateInterval('P1D'),
            'week' => new \DateInterval('P1W'),
            'month' => new \DateInterval('P1M'),
            'quarter' => new \DateInterval('P3M'),
            'year' => new \DateInterval('P1Y'),
            default => new \DateInterval('P1M'),
        };
    }
}
