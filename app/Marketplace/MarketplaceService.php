<?php

namespace App\Marketplace;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MarketplaceService
{
    /**
     * List published products with filters and sorting.
     */
    public function listProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::published()->with(['tenant', 'category']);

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Type filter
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Featured filter
        if (!empty($filters['featured'])) {
            $query->featured();
        }

        // Search (name or description)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Price range
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        $query = match ($sort) {
            'popular' => $query->orderByDesc('downloads'),
            'rating' => $query->orderByDesc('rating'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->latest(),
        };

        return $query->paginate($perPage);
    }

    /**
     * Get a single product by slug with full details.
     */
    public function getProductBySlug(string $slug): ?Product
    {
        return Product::where('slug', $slug)
            ->where('status', 'published')
            ->with(['tenant', 'category', 'versions'])
            ->with(['reviews' => function ($q) {
                $q->approved()->with('user')->latest()->take(5);
            }])
            ->first();
    }

    /**
     * Get featured products for the marketplace homepage.
     */
    public function getFeaturedProducts(int $limit = 10): Collection
    {
        return Product::published()
            ->featured()
            ->with(['tenant', 'category'])
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();
    }
}
