<?php

namespace App\Listeners;

use App\Events\OrderRefunded;
use App\Models\Commission;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReverseCommissionListener implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 30;

    public function handle(OrderRefunded $event): void
    {
        $order = $event->order;
        $commission = $order->commission;

        if ($commission) {
            $commission->delete();

            $tenant = $order->tenant;
            $tenant->decrement('balance', $commission->developer_income);
        }

        // Revoke associated licenses
        $order->licenses()->update(['status' => 'revoked']);
    }
}
