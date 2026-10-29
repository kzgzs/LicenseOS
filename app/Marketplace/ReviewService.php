<?php

namespace App\Marketplace;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    /**
     * Create a review for a product.
     * Enforces one review per user per product.
     */
    public function createReview(Product $product, User $user, int $rating, string $comment): Review
    {
        // Prevent duplicate reviews
        $existing = Review::where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            throw new \RuntimeException('You have already reviewed this product.');
        }

        return Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => max(1, min(5, $rating)),
            'comment' => $comment,
            'approved' => false,
        ]);
    }

    /**
     * Approve a review and recalculate product rating.
     */
    public function approveReview(Review $review): void
    {
        $review->update(['approved' => true]);
        $this->recalculateProductRating($review->product);
    }

    /**
     * Reject (delete) a review and recalculate product rating.
     */
    public function rejectReview(Review $review): void
    {
        $product = $review->product;
        $review->delete();
        $this->recalculateProductRating($product);
    }

    /**
     * Recalculate the cached average rating for a product.
     */
    public function recalculateProductRating(Product $product): void
    {
        $avg = Review::where('product_id', $product->id)
            ->where('approved', true)
            ->avg('rating');

        $product->update([
            'rating' => round($avg ?? 0, 2),
        ]);
    }

    /**
     * Get approved reviews for a product.
     */
    public function getProductReviews(Product $product, int $perPage = 15): LengthAwarePaginator
    {
        return $product->reviews()
            ->approved()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }
}
