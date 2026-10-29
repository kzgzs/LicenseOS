<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService,
    ) {}

    /**
     * Request a refund for an order.
     * POST /api/v3/orders/{orderId}/refund
     */
    public function store(Request $request, int $orderId)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $order = Order::findOrFail($orderId);

        $user = $request->user();
        if ($order->user_id !== $user?->id
            && $order->tenant_id !== $user?->tenant_id
            && !$user?->isSuperAdmin()
        ) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if ($order->status !== 'paid') {
            return response()->json([
                'error' => 'Only paid orders can be refunded.',
            ], 422);
        }

        $refund = $this->refundService->refund($order, $validated['reason'] ?? null);

        return response()->json([
            'data' => $refund,
            'message' => 'Refund processed successfully.',
        ]);
    }
}
