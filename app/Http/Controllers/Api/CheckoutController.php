<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Get available payment methods.
     * GET /api/v2/checkout/methods
     */
    public function methods()
    {
        return response()->json([
            'methods' => $this->paymentService->availableMethods(),
        ]);
    }

    /**
     * Create a checkout session for a product.
     * POST /api/v2/checkout/create
     */
    public function create(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'method' => 'sometimes|string|in:stripe,wechat,alipay',
            'tier_type' => 'sometimes|string|in:trial,1_month,3_months,6_months,1_year,lifetime',
            'domain' => 'sometimes|string|max:255',
        ]);

        $product = Product::with('tenant')->findOrFail($request->product_id);

        if ($product->status !== 'published') {
            return response()->json(['error' => 'Product is not available.'], 400);
        }

        $method = $request->input('method', 'wechat');
        $tierType = $request->input('tier_type', '');
        $domain = $request->input('domain');

        // Developers cannot trial their own products
        if ($tierType === 'trial' && $request->user() && $request->user()->tenant_id === $product->tenant_id) {
            return response()->json(['error' => 'Developers cannot trial their own products.'], 400);
        }

        // Trial abuse prevention
        if ($tierType === 'trial' && $request->user() && $domain) {
            // 1. Per-domain limit: one trial per domain across all accounts
            $domainTrialUsed = \App\Models\License::where('product_id', $product->id)
                ->where('is_trial', true)
                ->whereHas('activations', function ($q) use ($domain) {
                    $q->where('domain', $domain);
                })
                ->exists();
            if ($domainTrialUsed) {
                return response()->json(['error' => 'Trial already used on this domain.'], 400);
            }

            // 2. Per-user limit: max 3 trials per product per user
            $userTrialCount = \App\Models\License::where('product_id', $product->id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_trial', true)
                ->count();
            if ($userTrialCount >= 3) {
                return response()->json(['error' => 'Trial limit reached (max 3 per product). Please purchase a license.'], 400);
            }
        }

        // If payment methods not configured and not free trial, error
        $available = $this->paymentService->availableMethods();
        $tier = $tierType ? $product->findTier($tierType) : null;
        $tierPrice = $tier ? (float)($tier['price'] ?? 0) : (float)$product->price;
        $isFree = $tierPrice <= 0;

        if (!$isFree && empty($available)) {
            return response()->json(['error' => 'No payment method configured. Please contact the administrator.'], 503);
        }
        if (!$isFree && !in_array($method, $available)) {
            $method = $available[0];
        }

        try {
            $result = $this->paymentService->createOrderAndCheckout(
                $product,
                $request->user()?->id,
                $method,
                $tierType,
                $domain,
            );

            return response()->json([
                'order_id' => $result['order']->id,
                'checkout_url' => $result['checkout_url'],
                'session_id' => $result['session_id'],
                'payment_method' => $isFree ? 'free' : $method,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Payment service error. Please try again later.'], 500);
        }
    }

    /**
     * Checkout success page handler.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect('/')->with('error', 'Invalid checkout session.');
        }

        // Try Stripe session first, then fall back to order ID (WeChat)
        $order = \App\Models\Order::where('checkout_session_id', $sessionId)
            ->orWhere('id', $sessionId)
            ->first();

        if (!$order) {
            return redirect('/')->with('error', 'Order not found.');
        }

        if ($order->status !== 'paid') {
            return redirect('/')->with('info', 'Payment is being processed. You will be notified once confirmed.');
        }

        return view('checkout.success', [
            'order' => $order->load('product', 'licenses'),
        ]);
    }

    /**
     * Checkout cancel page handler.
     */
    public function cancel()
    {
        return redirect('/')->with('error', 'Payment was cancelled.');
    }

    /**
     * Check order payment status (for WeChat QR polling).
     * GET /api/v2/checkout/status/{orderId}
     */
    public function status(int $orderId)
    {
        $order = \App\Models\Order::findOrFail($orderId);
        return response()->json([
            'status' => $order->status,
            'paid' => $order->status === 'paid',
        ]);
    }
}
