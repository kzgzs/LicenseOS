<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\RevenueRecord;
use App\Services\TierService;

class RecordCommissionRevenueListener
{
    public function __construct(
        protected TierService $tierService
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $tenant = $order->tenant;

        // Record commission as platform revenue
        $rate = $this->tierService->getEffectiveCommissionRate($tenant);
        $platformFee = $order->amount * ($rate / 100);

        RevenueRecord::record('commission', $platformFee, $order, 'Sales commission: Order #' . $order->id);

        // Update tenant stats
        $tenant->increment('total_sales');
        $tenant->increment('total_revenue', $order->amount * 100); // store in cents

        // Re-evaluate developer tier
        $this->tierService->evaluateDeveloperTier($tenant);
    }
}
