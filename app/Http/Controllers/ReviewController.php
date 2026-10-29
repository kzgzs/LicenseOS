<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, $slug)
    {
        // 检查全局评价开关
        if (!SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true))) {
            return back()->with('error', __('产品评价功能当前已关闭。'));
        }

        $product = Product::where('slug', $slug)->where('reviews_enabled', true)->firstOrFail();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:2000',
        ]);

        // Prevent duplicate reviews
        if ($product->reviews()->where('user_id', auth()->id())->exists()) {
            return back()->with('error', __('你已经评价过该产品了。'));
        }

        Review::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'approved' => false, // Require admin approval
        ]);

        return back()->with('success', __('你的评价已提交，等待管理员审核。'));
    }

    /**
     * Reply to a review (all authenticated users can reply).
     */
    public function reply(Request $request, Review $review)
    {
        if (!SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true))) {
            return back()->with('error', __('产品评价功能当前已关闭。'));
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        ReviewReply::create([
            'review_id' => $review->id,
            'user_id'   => auth()->id(),
            'content'   => $validated['content'],
        ]);

        return back()->with('success', __('回复已提交。'));
    }
}
