<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PlatformHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Revenue metrics
        $todayRevenue = Order::paid()->whereDate('created_at', today())->sum('amount');
        $monthRevenue = Order::paid()->whereMonth('created_at', now()->month)->sum('amount');
        $totalRevenue = Order::paid()->sum('amount');

        // Refund rate
        $paidCount = Order::paid()->count();
        $refundCount = Order::refunded()->count();
        $refundRate = $paidCount > 0 ? round(($refundCount / ($paidCount + $refundCount)) * 100, 1) : 0;

        // Pending reviews
        $pendingReviews = Review::pending()->count();

        // Queue health (failed jobs)
        $failedJobs = DB::table('failed_jobs')->count();

        // Payout health
        $pendingPayouts = Payout::pending()->sum('amount');
        $failedPayouts = Payout::where('status', 'failed')->count();

        // Subscriptions
        $activeSubscriptions = Subscription::active()->count();

        return [
            Stat::make('Today Revenue', '$' . number_format($todayRevenue, 2))
                ->description('This month: $' . number_format($monthRevenue, 2)),

            Stat::make('Total Revenue', '$' . number_format($totalRevenue, 2))
                ->description('All-time paid orders: ' . $paidCount),

            Stat::make('Refund Rate', $refundRate . '%')
                ->description($refundCount . ' refunds / ' . $paidCount . ' orders')
                ->color($refundRate > 5 ? 'danger' : ($refundRate > 2 ? 'warning' : 'success')),

            Stat::make('Pending Reviews', $pendingReviews)
                ->description($pendingReviews > 10 ? 'Backlog growing' : 'Under control')
                ->color($pendingReviews > 10 ? 'warning' : 'success'),

            Stat::make('Active Subscriptions', $activeSubscriptions)
                ->description('Recurring revenue base'),

            Stat::make('Pending Payouts', '$' . number_format($pendingPayouts, 2))
                ->description($failedPayouts . ' failed')
                ->color($failedPayouts > 0 ? 'danger' : 'success'),

            Stat::make('Failed Jobs', $failedJobs)
                ->description($failedJobs > 0 ? 'Needs attention' : 'Queue healthy')
                ->color($failedJobs > 0 ? 'danger' : 'success'),

            Stat::make('Published Products', Product::published()->count())
                ->description('Featured: ' . Product::published()->where('featured', true)->count()),
        ];
    }
}
