<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get trending products based on recent views and orders.
     * Weighted: 60% views (last 7 days) + 40% orders (last 30 days).
     */
    public function getTrending(int $limit = 8): Collection
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        // View scores (last 7 days)
        $viewScores = ProductView::select('product_id', DB::raw('COUNT(*) as view_score'))
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy('product_id')
            ->pluck('view_score', 'product_id');

        // Order scores (last 30 days)
        $orderScores = Order::paid()
            ->select('product_id', DB::raw('COUNT(*) as order_score'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('product_id')
            ->pluck('order_score', 'product_id');

        // Combine scores
        $scores = [];
        $allProductIds = $viewScores->keys()->merge($orderScores->keys())->unique();

        foreach ($allProductIds as $pid) {
            $vScore = $viewScores->get($pid, 0);
            $oScore = $orderScores->get($pid, 0);
            $scores[$pid] = ($vScore * 0.6) + ($oScore * 5 * 0.4); // Orders weighted higher
        }

        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);

        if (empty($topIds)) {
            return Product::published()->orderByDesc('views')->limit($limit)->get();
        }

        return Product::published()
            ->whereIn('id', $topIds)
            ->get()
            ->sortByDesc(fn($p) => $scores[$p->id] ?? 0)
            ->values();
    }

    /**
     * Get related products based on category and tag similarity.
     */
    public function getRelated(Product $product, int $limit = 4): Collection
    {
        $query = Product::published()
            ->where('id', '!=', $product->id);

        // Same category gets priority
        if ($product->category_id) {
            $query->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id);

                // Also include products with overlapping tags
                if (is_array($product->tags) && count($product->tags) > 0) {
                    $q->orWhere(function ($q2) use ($product) {
                        foreach ($product->tags as $tag) {
                            $q2->orWhere('tags', 'like', "%{$tag}%");
                        }
                    });
                }
            });
        } elseif (is_array($product->tags) && count($product->tags) > 0) {
            $query->where(function ($q) use ($product) {
                foreach ($product->tags as $tag) {
                    $q->orWhere('tags', 'like', "%{$tag}%");
                }
            });
        }

        return $query
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * Get newest published products.
     */
    public function getNewArrivals(int $limit = 8): Collection
    {
        return Product::published()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get personalized recommendations based on user's purchase history.
     * Strategy: find products in same categories as purchased + exclude already owned.
     */
    public function getForUser(int $userId, int $limit = 8): Collection
    {
        // Products the user already owns
        $purchasedIds = Order::paid()
            ->where('user_id', $userId)
            ->pluck('product_id')
            ->unique()
            ->toArray();

        // Categories of purchased products
        $purchasedCategories = Product::whereIn('id', $purchasedIds)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique()
            ->toArray();

        if (empty($purchasedCategories)) {
            // No purchase history, return trending
            return $this->getTrending($limit);
        }

        return Product::published()
            ->whereNotIn('id', $purchasedIds)
            ->whereIn('category_id', $purchasedCategories)
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * Get "frequently bought together" products.
     * Based on co-purchase patterns in the same orders.
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 3): Collection
    {
        // Get order IDs that contain this product
        $orderIds = Order::paid()
            ->where('product_id', $product->id)
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return $this->getRelated($product, $limit);
        }

        // Find other products in those orders
        $companionIds = Order::paid()
            ->whereIn('id', $orderIds)
            ->where('product_id', '!=', $product->id)
            ->select('product_id', DB::raw('COUNT(*) as co_count'))
            ->groupBy('product_id')
            ->orderByDesc('co_count')
            ->limit($limit)
            ->pluck('product_id');

        if ($companionIds->isEmpty()) {
            return $this->getRelated($product, $limit);
        }

        return Product::published()
            ->whereIn('id', $companionIds)
            ->get();
    }

    /**
     * Get best sellers by total order count.
     */
    public function getBestSellers(int $limit = 8): Collection
    {
        $topIds = Order::paid()
            ->select('product_id', DB::raw('COUNT(*) as sales'))
            ->groupBy('product_id')
            ->orderByDesc('sales')
            ->limit($limit)
            ->pluck('product_id');

        if ($topIds->isEmpty()) {
            return Product::published()->orderByDesc('views')->limit($limit)->get();
        }

        return Product::published()
            ->whereIn('id', $topIds)
            ->get()
            ->sortByDesc(fn($p) => array_search($p->id, $topIds->toArray()))
            ->values();
    }
}
