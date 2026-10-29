<?php

namespace App\Services;

use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Refund;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Stripe\Refund as StripeRefund;

class RefundService
{
    public function __construct(
        protected StripeService $stripeService,
        protected SmsService $smsService,
    ) {}

    /**
     * Process a full refund for an order.
     */
    public function refund(Order $order, ?string $reason = null): Refund
    {
        if ($order->status !== 'paid') {
            throw new \RuntimeException('Only paid orders can be refunded.');
        }

        // Prevent duplicate refunds
        $existing = Refund::where('order_id', $order->id)->first();
        if ($existing) {
            throw new \RuntimeException('This order has already been refunded.');
        }

        $refundAmount = (float) $order->amount;

        // Attempt Stripe refund if payment_intent_id exists
        $stripeRefundId = null;
        if ($order->payment_intent_id && str_starts_with($order->payment_intent_id, 'pi_')) {
            try {
                $stripeRefund = StripeRefund::create([
                    'payment_intent' => $order->payment_intent_id,
                    'reason' => 'requested_by_customer',
                ]);
                $stripeRefundId = $stripeRefund->id;
            } catch (\Exception $e) {
                throw new \RuntimeException('Stripe refund failed: ' . $e->getMessage());
            }
        }

        // Create refund record
        $refund = Refund::create([
            'order_id' => $order->id,
            'amount' => $refundAmount,
            'reason' => $reason,
            'stripe_refund_id' => $stripeRefundId,
            'status' => 'completed',
        ]);

        // Update order status
        $order->update(['status' => 'refunded']);

        // Fire event (reverses commission, revokes licenses)
        OrderRefunded::dispatch($order, $refundAmount, $reason);

        // Send refund success SMS if buyer has a phone number
        $buyerPhone = $order->buyer_phone ?? $order->user?->phone ?? null;
        if ($buyerPhone) {
            try {
                $this->smsService->sendRefundSuccess($buyerPhone, (string) $order->id, $refundAmount);
            } catch (\Exception $e) {
                Log::warning('Failed to send refund SMS: ' . $e->getMessage());
            }
        }

        return $refund;
    }

    /**
     * Get refund history for a tenant.
     */
    public function getTenantRefunds(int $tenantId)
    {
        return Refund::whereHas('order', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->with('order.product')->latest()->paginate(20);
    }
}
