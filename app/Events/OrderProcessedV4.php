<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderProcessedV4
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $countryCode = null,
        public ?string $currency = 'USD',
        public ?string $gateway = null,
    ) {}
}
