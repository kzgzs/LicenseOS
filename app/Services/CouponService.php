<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Str;

class CouponService
{
    /**
     * Validate and apply a coupon code to a product price.
     * Returns discount details or error.
     */
    public function apply(string $code, float $price, ?int $productId = null): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return ['success' => false, 'error' => 'Invalid coupon code.'];
        }

        if (!$coupon->isValid()) {
            if ($coupon->isExpired()) {
                return ['success' => false, 'error' => 'This coupon has expired.'];
            }
            if ($coupon->hasReachedLimit()) {
                return ['success' => false, 'error' => 'This coupon has reached its usage limit.'];
            }
            return ['success' => false, 'error' => 'This coupon is no longer active.'];
        }

        // Check product restriction
        if ($coupon->product_id && $productId && $coupon->product_id !== $productId) {
            return ['success' => false, 'error' => 'This coupon is not valid for this product.'];
        }

        // Check minimum purchase
        if ($coupon->min_purchase > 0 && $price < $coupon->min_purchase) {
            return [
                'success' => false,
                'error' => "Minimum purchase of \${$coupon->min_purchase} required.",
            ];
        }

        $discount = $coupon->calculateDiscount($price);
        $finalPrice = max(0, $price - $discount);

        return [
            'success' => true,
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'discount' => $discount,
            'original_price' => $price,
            'final_price' => $finalPrice,
        ];
    }

    /**
     * Record coupon usage after an order is paid.
     */
    public function recordUsage(int $couponId): void
    {
        Coupon::where('id', $couponId)->increment('used_count');
    }

    /**
     * Generate a unique coupon code.
     */
    public function generateCode(string $prefix = ''): string
    {
        do {
            $code = strtoupper($prefix . Str::random(8 - strlen($prefix)));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    /**
     * Create a promotional coupon.
     */
    public function create(array $data): Coupon
    {
        if (empty($data['code'])) {
            $data['code'] = $this->generateCode($data['prefix'] ?? '');
        }

        return Coupon::create([
            'code' => strtoupper($data['code']),
            'type' => $data['type'] ?? 'percentage',
            'value' => $data['value'],
            'min_purchase' => $data['min_purchase'] ?? 0,
            'max_uses' => $data['max_uses'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => $data['active'] ?? true,
        ]);
    }

    /**
     * Get all active coupons for a product (for display on product page).
     */
    public function getAvailableForProduct(int $productId): array
    {
        return Coupon::active()
            ->forProduct($productId)
            ->where('active', true)
            ->get()
            ->filter(fn(Coupon $c) => $c->isValid())
            ->values()
            ->toArray();
    }

    /**
     * Get analytics for a coupon.
     */
    public function getAnalytics(int $couponId): array
    {
        $coupon = Coupon::findOrFail($couponId);

        return [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'used_count' => $coupon->used_count,
            'max_uses' => $coupon->max_uses,
            'remaining_uses' => $coupon->max_uses ? $coupon->max_uses - $coupon->used_count : null,
            'is_valid' => $coupon->isValid(),
            'expires_at' => $coupon->expires_at?->toIso8601String(),
        ];
    }
}
