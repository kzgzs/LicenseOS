<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (!$tenant) {
            $reviews = $request->user()->isSuperAdmin()
                ? Review::with(['product', 'user'])->latest()->paginate(20)
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            return view('developer.reviews', ['reviews' => $reviews]);
        }

        $products = $tenant->products()->pluck('id');

        $reviews = Review::whereIn('product_id', $products)
            ->with(['product', 'user'])
            ->latest()
            ->paginate(20);

        return view('developer.reviews', [
            'reviews' => $reviews,
        ]);
    }

    public function approve(Review $review)
    {
        if ($review->product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $review->update(['approved' => true]);

        // Recalculate product rating
        $product = $review->product;
        $avg = $product->reviews()->approved()->avg('rating');
        $product->update(['rating' => round($avg ?? 0, 2)]);

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review)
    {
        if ($review->product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $review->delete();

        // Recalculate product rating
        $product = $review->product;
        $avg = $product->reviews()->approved()->avg('rating');
        $product->update(['rating' => round($avg ?? 0, 2)]);

        return back()->with('success', 'Review rejected and deleted.');
    }
}
