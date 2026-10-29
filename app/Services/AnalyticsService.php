<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get conversion rate for a product: (paid orders / unique views) * 100.
     */
    public function getConversionRate(int $productId, ?int $days = 30): float
    {
        $since = now()->subDays($days);

        $views = ProductView::where('product_id', $productId)
            ->where('created_at', '>=', $since)
            ->distinct('session_id')
            ->count('session_id');

        if ($views === 0) {
            return 0.0;
        }

        $orders = Order::paid()
            ->where('product_id', $productId)
            ->where('created_at', '>=', $since)
            ->count();

        return round(($orders / $views) * 100, 2);
    }

    /**
     * Get platform-wide conversion rate.
     */
    public function getPlatformConversionRate(?int $days = 30): float
    {
        $since = now()->subDays($days);

        $views = ProductView::where('created_at', '>=', $since)
            ->distinct('session_id')
            ->count('session_id');

        if ($views === 0) {
            return 0.0;
        }

        $orders = Order::paid()
            ->where('created_at', '>=', $since)
            ->count();

        return round(($orders / $views) * 100, 2);
    }

    /**
     * Get traffic source breakdown for a product.
     */
    public function getTrafficSources(int $productId, ?int $days = 30): array
    {
        $since = now()->subDays($days);

        return ProductView::where('product_id', $productId)
            ->where('created_at', '>=', $since)
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->pluck('count', 'source')
            ->toArray();
    }

    /**
     * Get daily view trends for a product (last N days).
     */
    public function getDailyViews(int $productId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $views = ProductView::where('product_id', $productId)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as views'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('views', 'date')
            ->toArray();

        // Fill missing dates with 0
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $result[$date] = $views[$date] ?? 0;
        }

        return $result;
    }

    /**
     * Get top products by conversion rate.
     */
    public function getTopConvertingProducts(int $limit = 10, ?int $days = 30): array
    {
        $since = now()->subDays($days);

        // Get products with both views and orders
        $productIds = Product::published()->pluck('id');
        $results = [];

        foreach ($productIds as $pid) {
            $views = ProductView::where('product_id', $pid)
                ->where('created_at', '>=', $since)
                ->distinct('session_id')
                ->count('session_id');

            $orders = Order::paid()
                ->where('product_id', $pid)
                ->where('created_at', '>=', $since)
                ->count();

            if ($views > 0) {
                $results[] = [
                    'product_id' => $pid,
                    'views' => $views,
                    'orders' => $orders,
                    'conversion_rate' => round(($orders / $views) * 100, 2),
                ];
            }
        }

        // Sort by conversion rate descending
        usort($results, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Get platform-level analytics summary.
     */
    public function getPlatformSummary(?int $days = 30): array
    {
        $since = now()->subDays($days);

        $totalViews = ProductView::where('created_at', '>=', $since)->count();
        $uniqueVisitors = ProductView::where('created_at', '>=', $since)
            ->distinct('session_id')
            ->count('session_id');
        $totalOrders = Order::paid()->where('created_at', '>=', $since)->count();
        $totalRevenue = Order::paid()->where('created_at', '>=', $since)->sum('amount');

        return [
            'period_days' => $days,
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'conversion_rate' => $uniqueVisitors > 0
                ? round(($totalOrders / $uniqueVisitors) * 100, 2)
                : 0.0,
            'avg_order_value' => $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0.0,
        ];
    }

    /**
     * Get daily revenue trend.
     */
    public function getDailyRevenue(int $days = 30): array
    {
        $since = now()->subDays($days);

        $revenue = Order::paid()
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill missing dates
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $result[$date] = [
                'revenue' => $revenue[$date]['revenue'] ?? 0,
                'orders' => $revenue[$date]['orders'] ?? 0,
            ];
        }

        return $result;
    }
}
