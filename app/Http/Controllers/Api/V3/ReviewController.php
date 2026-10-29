<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Marketplace\ReviewService;
use App\Models\Product;
use App\Notifications\NewReviewNotification;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService,
    ) {}

    /**
     * Get approved reviews for a product.
     * GET /api/v3/products/{slug}/reviews
     */
    public function index(string $slug, Request $request)
    {
        $product = Product::where('slug', $slug)->where('status', 'published')->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $reviews = $this->reviewService->getProductReviews($product, $perPage);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Create a review for a product (authenticated).
     * POST /api/v3/products/{slug}/reviews
     */
    public function store(string $slug, Request $request)
    {
        $product = Product::where('slug', $slug)->where('status', 'published')->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:2000',
        ]);

        try {
            $review = $this->reviewService->createReview(
                $product,
                $request->user(),
                $validated['rating'],
                $validated['comment'],
            );

            // Notify product owner (tenant users)
            if ($product->tenant) {
                foreach ($product->tenant->users as $devUser) {
                    $devUser->notify(new NewReviewNotification($review));
                }
            }

            return response()->json([
                'data' => $review,
                'message' => 'Review submitted. It will appear after moderation.',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
