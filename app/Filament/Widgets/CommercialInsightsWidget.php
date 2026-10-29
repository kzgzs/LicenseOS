<?php

namespace App\Filament\Widgets;

use App\Models\Ad;
use App\Services\RevenueService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommercialInsightsWidget extends BaseWidget
{
    protected static ?int $sort = 0;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $revenue = app(RevenueService::class);
        $bidding = $revenue->getBiddingOverview();
        $hotKeywords = $revenue->getHotKeywords(5);

        $topKeyword = !empty($hotKeywords) ? $hotKeywords[0]['target_keywords'] ?? 'N/A' : 'N/A';

        return [
            Stat::make('Active Ad Campaigns', Ad::where('status', 'active')->count())
                ->description('Currently running')
                ->color('warning'),

            Stat::make('Avg Bid Price', '$' . number_format($bidding['avg_bid_price'], 2))
                ->description('Search boost average')
                ->color('primary'),

            Stat::make('Top Keyword', $topKeyword)
                ->description('Highest bid keyword')
                ->color('info'),

            Stat::make('Ad Spend Today', '$' . number_format($bidding['total_ad_spend_today'], 2))
                ->description('Total ad revenue today')
                ->color('success'),
        ];
    }
}
