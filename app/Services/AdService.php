<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Product;
use App\Models\RevenueRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class AdService
{
    /**
     * Get active banner ads for homepage/category pages.
     */
    public function getBannerAds(int $limit = 3): Collection
    {
        return Cache::remember('ads:banners', 300, function () use ($limit) {
            return Ad::active()
                ->byType('banner')
                ->orderByDesc('bid_price')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get featured/promoted products for a given position.
     */
    public function getFeaturedAds(int $limit = 5): Collection
    {
        return Ad::active()
            ->byType('featured')
            ->orderByDesc('bid_price')
            ->limit($limit)
            ->get();
    }

    /**
     * Get search boost ads for a keyword.
     */
    public function getSearchBoostAds(string $keyword, int $limit = 3): Collection
    {
        return Ad::active()
            ->byType('search_boost')
            ->where('target_keywords', 'LIKE', '%' . $keyword . '%')
            ->orderByDesc('bid_price')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate ranking score with bid boost.
     * score = (downloads * 0.5) + (rating * 10) + (bid_price * 2)
     */
    public function calculateRankingScore(Product $product, float $bidPrice = 0): float
    {
        return ($product->downloads * 0.5)
            + ($product->rating * 10)
            + ($bidPrice * 2);
    }

    /**
     * Get products ranked by commercial score (organic + paid).
     */
    public function getRankedProducts(string $keyword = '', int $limit = 20): array
    {
        $products = Product::published()
            ->when($keyword, fn($q) => $q->where('name', 'LIKE', "%{$keyword}%"))
            ->get();

        // Get active search boost ads
        $activeAds = Ad::active()
            ->byType('search_boost')
            ->when($keyword, fn($q) => $q->where('target_keywords', 'LIKE', "%{$keyword}%"))
            ->pluck('bid_price', 'product_id');

        // Calculate scores
        $scored = $products->map(function ($product) use ($activeAds) {
            $bidPrice = $activeAds[$product->id] ?? 0;
            $product->ranking_score = $this->calculateRankingScore($product, $bidPrice);
            $product->is_promoted = $bidPrice > 0;
            return $product;
        });

        return $scored->sortByDesc('ranking_score')->take($limit)->values()->all();
    }

    /**
     * Record an ad impression.
     */
    public function recordImpression(Ad $ad): void
    {
        $ad->recordImpression();
    }

    /**
     * Record an ad click and charge the developer.
     */
    public function recordClick(Ad $ad): void
    {
        $ad->recordClick();

        // Record revenue from this click
        RevenueRecord::record('ad', $ad->bid_price, $ad, 'Ad click: ' . $ad->title);
    }

    /**
     * Create a new ad campaign.
     */
    public function createCampaign(array $data): Ad
    {
        return Ad::create(array_merge($data, [
            'status' => 'pending',
            'impressions' => 0,
            'clicks' => 0,
            'total_spent' => 0,
        ]));
    }

    /**
     * Pause an ad campaign.
     */
    public function pauseCampaign(Ad $ad): void
    {
        $ad->update(['status' => 'paused']);
    }

    /**
     * Activate an ad campaign.
     */
    public function activateCampaign(Ad $ad): void
    {
        $ad->update(['status' => 'active']);
        Cache::forget('ads:banners');
    }

    /**
     * Get ad performance stats for a tenant.
     */
    public function getTenantAdStats(int $tenantId): array
    {
        $ads = Ad::where('tenant_id', $tenantId)->get();

        return [
            'total_campaigns' => $ads->count(),
            'active_campaigns' => $ads->where('status', 'active')->count(),
            'total_impressions' => $ads->sum('impressions'),
            'total_clicks' => $ads->sum('clicks'),
            'total_spent' => $ads->sum('total_spent'),
            'avg_ctr' => $ads->avg(fn($ad) => $ad->ctr()),
        ];
    }

    /**
     * Expire ads that have passed their end date.
     */
    public function expireAds(): int
    {
        return Ad::where('status', 'active')
            ->where('end_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
