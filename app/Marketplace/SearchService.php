<?php

namespace App\Marketplace;

use App\Models\Ad;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search products using database LIKE queries.
     * V3.4: Integrates bid-based ranking for commercial results.
     */
    public function search(string $query, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $q = Product::published()->with(['tenant', 'category']);

        // Full-text search across name, description, short_description, tags
        $q->where(function ($builder) use ($query) {
            $builder->where('name', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%")
                ->orWhere('short_description', 'LIKE', "%{$query}%")
                ->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", ["%{$query}%"]);
        });

        // Category filter
        if (!empty($filters['category_id'])) {
            $q->where('category_id', $filters['category_id']);
        }

        // Type filter
        if (!empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }

        // Sort (V3.4: add 'commercial' sort with bid ranking)
        $sort = $filters['sort'] ?? 'relevance';
        $q = match ($sort) {
            'popularity' => $q->orderByDesc('downloads'),
            'rating' => $q->orderByDesc('rating'),
            'price_asc' => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            'commercial' => $this->applyCommercialSort($q, $query),
            default => $q->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
                          ->orderByDesc('featured')
                          ->orderByDesc('downloads'),
        };

        return $q->paginate($perPage);
    }

    /**
     * V3.4: Apply commercial ranking sort.
     * Score = (downloads * 0.5) + (rating * 10) + (bid_price * 2)
     */
    protected function applyCommercialSort($query, string $keyword)
    {
        // Left join with active ads to get bid price
        return $query->leftJoin('ads', function ($join) use ($keyword) {
                $join->on('products.id', '=', 'ads.product_id')
                    ->where('ads.status', 'active')
                    ->where('ads.type', 'search_boost')
                    ->where('ads.start_at', '<=', now())
                    ->where('ads.end_at', '>=', now());
                if ($keyword) {
                    $join->where('ads.target_keywords', 'LIKE', "%{$keyword}%");
                }
            })
            ->selectRaw('products.*, COALESCE(ads.bid_price, 0) as bid_price')
            ->orderByRaw('(products.downloads * 0.5) + (products.rating * 10) + (COALESCE(ads.bid_price, 0) * 2) DESC');
    }

    /**
     * V3.4: Get sponsored results for a search query.
     */
    public function getSponsoredResults(string $keyword, int $limit = 3): Collection
    {
        $ads = Ad::active()
            ->byType('search_boost')
            ->where('target_keywords', 'LIKE', "%{$keyword}%")
            ->orderByDesc('bid_price')
            ->limit($limit)
            ->with('product')
            ->get();

        // Record impressions
        foreach ($ads as $ad) {
            $ad->recordImpression();
        }

        return $ads->pluck('product');
    }

    /**
     * Search categories by name.
     */
    public function searchCategories(string $query)
    {
        return \App\Models\Category::where('name', 'LIKE', "%{$query}%")
            ->ordered()
            ->withCount('products')
            ->limit(10)
            ->get();
    }
}
