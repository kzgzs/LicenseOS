<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Services\AdService;
use App\Services\RevenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicApiController extends Controller
{
    public function __construct(
        protected AdService $adService,
        protected RevenueService $revenueService
    ) {}

    /**
     * GET /api/v3/public/products - Public products with ranking.
     */
    public function products(Request $request): JsonResponse
    {
        $keyword = $request->query('q', '');
        $limit = (int) $request->query('limit', 20);

        $products = $this->adService->getRankedProducts($keyword, $limit);

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => count($products),
                'keyword' => $keyword,
            ],
        ]);
    }

    /**
     * GET /api/v3/public/search - Enhanced search with ads.
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = $request->query('q', '');
        $limit = (int) $request->query('limit', 20);

        // Get organic + paid results
        $products = $this->adService->getRankedProducts($keyword, $limit);

        // Get banner ads for search results page
        $ads = $this->adService->getSearchBoostAds($keyword);

        // Record impressions for displayed ads
        foreach ($ads as $ad) {
            $this->adService->recordImpression($ad);
        }

        return response()->json([
            'data' => [
                'results' => $products,
                'sponsored' => $ads,
            ],
            'meta' => [
                'keyword' => $keyword,
                'total_results' => count($products),
                'sponsored_count' => $ads->count(),
            ],
        ]);
    }

    /**
     * POST /api/v3/public/license/check - Public license check.
     */
    public function licenseCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'product_slug' => 'required|string',
        ]);

        $license = \App\Models\License::where('key', $validated['license_key'])
            ->whereHas('product', fn($q) => $q->where('slug', $validated['product_slug']))
            ->first();

        if (!$license) {
            return response()->json([
                'valid' => false,
                'error' => 'License not found',
            ], 404);
        }

        return response()->json([
            'valid' => $license->status === 'active',
            'license' => [
                'key' => $license->key,
                'status' => $license->status,
                'expires_at' => $license->expires_at,
                'activations' => $license->activations()->count(),
                'max_activations' => $license->max_activations,
            ],
        ]);
    }
}
