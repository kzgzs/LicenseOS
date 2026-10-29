<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\CommissionService;

class CalculateCommissionListener
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        if (!$order->commission) {
            $this->commissionService->calculateForOrder($order);
        }
    }
}
