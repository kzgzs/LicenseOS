<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
    ) {}

    /**
     * Validate a coupon code and get discount details.
     * POST /api/v3/coupons/validate
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
        ]);

        $result = $this->couponService->apply(
            $request->input('code'),
            $request->input('price'),
            $request->input('product_id'),
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result);
    }

    /**
     * Get available coupons for a product.
     * GET /api/v3/coupons/available?product_id=1
     */
    public function available(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $coupons = $this->couponService->getAvailableForProduct(
            $request->input('product_id'),
        );

        return response()->json(['coupons' => $coupons]);
    }
}
