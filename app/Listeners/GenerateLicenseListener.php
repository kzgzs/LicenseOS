<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\OrderPaidNotification;
use App\Services\LicenseService;

class GenerateLicenseListener
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->licenseService->generateForOrder($event->order);

        // Notify buyer via in-app notification
        if ($event->order->user) {
            $event->order->user->notify(new OrderPaidNotification($event->order));
        }
    }
}
