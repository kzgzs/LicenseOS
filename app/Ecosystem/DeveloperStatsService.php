<?php

namespace App\Ecosystem;

use App\Models\Tenant;
use App\Models\Product;
use App\Models\Order;
use App\Models\Commission;
use App\Models\Review;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeveloperStatsService
{
    /**
     * Get a sales report for a tenant within a date range.
     */
    public function getSalesReport(Tenant $tenant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $orders = Order::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to]);

        $totalOrders = (clone $orders)->count();
        $totalRevenue = (clone $orders)->sum('amount');

        $commissions = Commission::where('tenant_id', $tenant->id)
            ->whereHas('order', function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to]);
            });

        $totalPlatformFee = (clone $commissions)->sum('platform_fee');
        $totalNetIncome = (clone $commissions)->sum('developer_income');

        // Orders per day (for chart)
        $ordersByDay = Order::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as orders'), DB::raw('SUM(amount) as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // Top products by revenue
        $topProducts = Order::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->select('product_id', DB::raw('COUNT(*) as sales'), DB::raw('SUM(amount) as revenue'))
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('product:id,name')
            ->get()
            ->toArray();

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => (float) $totalRevenue,
            'total_platform_fee' => (float) $totalPlatformFee,
            'total_net_income' => (float) $totalNetIncome,
            'orders_by_day' => $ordersByDay,
            'top_products' => $topProducts,
        ];
    }

    /**
     * Get revenue chart data for a specific period.
     */
    public function getRevenueChart(Tenant $tenant, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };

        $from = now()->subDays($days);

        return Order::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->where('created_at', '>=', $from)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get stats for a single product.
     */
    public function getProductStats(Product $product): array
    {
        $paidOrders = Order::where('product_id', $product->id)->where('status', 'paid');

        return [
            'total_sales' => (clone $paidOrders)->count(),
            'total_revenue' => (float) (clone $paidOrders)->sum('amount'),
            'avg_rating' => (float) $product->rating,
            'review_count' => Review::where('product_id', $product->id)->where('approved', true)->count(),
            'active_subscriptions' => Subscription::where('product_id', $product->id)->where('status', 'active')->count(),
            'downloads' => $product->downloads,
        ];
    }
}
