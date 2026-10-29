<?php

namespace App\Listeners;

use App\Events\OrderPaid;

class UpdateProductStatsListener
{
    public function handle(OrderPaid $event): void
    {
        $product = $event->order->product;

        if ($product) {
            $product->increment('downloads');
        }
    }
}
