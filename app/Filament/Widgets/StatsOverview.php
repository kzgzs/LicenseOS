<?php

namespace App\Filament\Widgets;

use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Commission;
use App\Models\DeveloperApplication;
use App\Models\Payout;
use App\Models\ApiKey;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Order::where('status', 'paid')->sum('amount');
        $platformFee = Commission::sum('platform_fee');
        $pendingApps = DeveloperApplication::where('status', 'pending')->count();
        $pendingPayouts = Payout::where('status', 'pending')->sum('amount');

        return [
            Stat::make('Tenants', Tenant::count())
                ->description('Active API keys: ' . ApiKey::where('active', true)->count()),
            Stat::make('Products', Product::count())
                ->description('Published: ' . Product::where('status', 'published')->count()),
            Stat::make('Orders', Order::count())
                ->description('Paid: ' . Order::where('status', 'paid')->count()),
            Stat::make('Revenue', '$' . number_format($totalRevenue, 2))
                ->description('Platform fee: $' . number_format($platformFee, 2)),
            Stat::make('Licenses', License::count())
                ->description('Active: ' . License::where('status', 'active')->count()),
            Stat::make('Pending Applications', $pendingApps)
                ->description($pendingApps > 0 ? 'Action required' : 'All clear')
                ->color($pendingApps > 0 ? 'warning' : 'success'),
            Stat::make('Pending Payouts', '$' . number_format($pendingPayouts, 2))
                ->description(Payout::where('status', 'pending')->count() . ' requests')
                ->color($pendingPayouts > 0 ? 'warning' : 'success'),
        ];
    }
}
