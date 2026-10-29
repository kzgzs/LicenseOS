<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Create a purchase (checkout session).
     * POST /api/v3/purchase
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'payment_method' => 'sometimes|in:stripe,wechat,alipay',
        ]);

        $product = Product::with('tenant')->findOrFail($validated['product_id']);

        if ($product->status !== 'published') {
            return response()->json(['error' => 'Product is not available.'], 400);
        }

        if (!$product->tenant || !$product->tenant->isActive()) {
            return response()->json(['error' => 'Developer account is suspended.'], 403);
        }

        $paymentMethod = $validated['payment_method'] ?? 'stripe';

        $result = $this->paymentService->createOrderAndCheckout(
            $product,
            $request->user()?->id,
            $paymentMethod,
        );

        return response()->json([
            'order_id' => $result['order']->id,
            'checkout_url' => $result['checkout_url'],
            'session_id' => $result['session_id'],
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Get order status.
     * GET /api/v3/orders/{orderId}
     */
    public function show(Request $request, int $orderId)
    {
        $order = Order::with(['product', 'licenses'])->findOrFail($orderId);

        // Only the buyer or tenant owner can view
        $user = $request->user();
        if ($order->user_id !== $user?->id
            && $order->tenant_id !== $user?->tenant_id
            && !$user?->isSuperAdmin()
        ) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'product' => $order->product?->name,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'licenses' => $order->licenses->map(fn ($l) => [
                    'key' => $l->license_key,
                    'status' => $l->status,
                    'max_domains' => $l->max_domains,
                    'expire_at' => $l->expire_at,
                ]),
                'created_at' => $order->created_at,
            ],
        ]);
    }

    /**
     * List user's orders.
     * GET /api/v3/orders
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->with(['product' => fn ($q) => $q->select('id', 'name', 'slug', 'type')])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
