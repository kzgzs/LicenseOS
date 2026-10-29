<?php

namespace App\Filament\Widgets;

use App\Services\RevenueService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends BaseWidget
{
    protected static ?int $sort = -1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $revenue = app(RevenueService::class);
        $dashboard = $revenue->getDashboard('month');

        $projections = $dashboard['projections'];
        $growthRate = $dashboard['growth_rate'];

        return [
            Stat::make('Monthly Revenue', '$' . number_format($dashboard['total_revenue'], 2))
                ->description($growthRate >= 0 ? "+{$growthRate}% vs last month" : "{$growthRate}% vs last month")
                ->descriptionIcon($growthRate >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growthRate >= 0 ? 'success' : 'danger'),

            Stat::make('Ad Revenue', '$' . number_format($this->getSourceTotal($dashboard, 'ad'), 2))
                ->description('From ad clicks & impressions')
                ->color('warning'),

            Stat::make('API Revenue', '$' . number_format($this->getSourceTotal($dashboard, 'api'), 2))
                ->description('From API overage charges')
                ->color('primary'),

            Stat::make('Commission Revenue', '$' . number_format($this->getSourceTotal($dashboard, 'commission'), 2))
                ->description('From product sales')
                ->color('info'),

            Stat::make('Annual Projection', '$' . number_format($projections['annual_projected'], 2))
                ->description('Based on current trends')
                ->color('gray'),
        ];
    }

    protected function getSourceTotal(array $dashboard, string $source): float
    {
        return (float) ($dashboard['revenue_by_source'][$source]['total'] ?? 0);
    }
}
