<?php

namespace App\Services;

use App\Models\PlatformEvent;
use App\Models\Order;
use App\Jobs\ProcessOrderPaidJob;
use App\Jobs\ProcessPayoutJob;

class EventService
{
    /**
     * Dispatch an event and queue it for async processing.
     */
    public static function dispatch(string $type, array $payload): PlatformEvent
    {
        $event = PlatformEvent::dispatch($type, $payload);

        // Queue the event for processing
        match ($type) {
            'order.paid' => ProcessOrderPaidJob::dispatch($event),
            'payout.requested' => ProcessPayoutJob::dispatch($event),
            default => null,
        };

        return $event;
    }

    /**
     * Process a single event based on its type.
     */
    public function process(PlatformEvent $event): void
    {
        if ($event->processed) {
            return;
        }

        match ($event->type) {
            'order.paid' => $this->handleOrderPaid($event),
            'payout.requested' => $this->handlePayoutRequested($event),
            'license.created' => $this->handleLicenseCreated($event),
            default => null,
        };

        $event->markProcessed();
    }

    protected function handleOrderPaid(PlatformEvent $event): void
    {
        $payload = $event->payload;
        $order = Order::find($payload['order_id']);

        if (!$order || $order->status !== 'paid') {
            return;
        }

        // V3.1: License generation, commission calculation, and stats updates
        // are now handled by Laravel Event Listeners (OrderPaid event):
        //   - GenerateLicenseListener (queued)
        //   - CalculateCommissionListener (queued)
        //   - UpdateProductStatsListener (sync)
        //
        // This method now only handles supplementary side effects:
        // - Audit trail (PlatformEvent record)
        // - Future: webhooks, analytics
    }

    protected function handlePayoutRequested(PlatformEvent $event): void
    {
        $payload = $event->payload;
        $payout = \App\Models\Payout::find($payload['payout_id'] ?? null);

        if (!$payout || !$payout->isPending()) {
            return;
        }

        // V3.1: Payout processing is now handled by PayoutRequested event
        // -> ProcessPayoutListener -> PayoutService->process()
        // This includes real Stripe Connect transfer, Alipay, and WeChat.
        //
        // This PlatformEvent handler serves as an audit trail only.
    }

    protected function handleLicenseCreated(PlatformEvent $event): void
    {
        // Future: Send license email to customer, trigger webhook, etc.
    }
}
