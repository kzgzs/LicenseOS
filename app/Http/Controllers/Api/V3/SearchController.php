<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Marketplace\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $searchService,
    ) {}

    /**
     * Search products.
     * GET /api/v3/search?q=keyword
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:200',
            'category_id' => 'nullable|integer',
            'type' => 'nullable|string|in:wp_plugin,saas,app,theme',
            'sort' => 'nullable|string|in:relevance,popularity,rating,price_asc,price_desc',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $results = $this->searchService->search(
            $validated['q'],
            $validated,
            (int) ($validated['per_page'] ?? 20),
        );

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'query' => $validated['q'],
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }
}
