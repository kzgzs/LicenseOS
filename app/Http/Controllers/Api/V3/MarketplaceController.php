<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceService;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(
        protected MarketplaceService $marketplace,
    ) {}

    /**
     * List all published products with filters.
     * GET /api/v3/products
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'category_id', 'type', 'featured',
            'search', 'min_price', 'max_price', 'sort',
        ]);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $products = $this->marketplace->listProducts($filters, $perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Get product detail by slug.
     * GET /api/v3/products/{slug}
     */
    public function show(string $slug)
    {
        $product = $this->marketplace->getProductBySlug($slug);

        if (!$product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * Get featured products.
     * GET /api/v3/products/featured
     */
    public function featured(Request $request)
    {
        $limit = min((int) $request->input('limit', 10), 50);
        $products = $this->marketplace->getFeaturedProducts($limit);

        return response()->json(['data' => $products]);
    }
}
