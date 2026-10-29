<?php

namespace App\Providers;

use App\Events\AdClicked;
use App\Events\OrderPaid;
use App\Events\OrderProcessedV4;
use App\Events\OrderRefunded;
use App\Events\PayoutRequested;
use App\Listeners\CalculateCommissionListener;
use App\Listeners\GenerateLicenseListener;
use App\Listeners\ProcessOrderV4Listener;
use App\Listeners\ProcessPayoutListener;
use App\Listeners\RecordAdRevenueListener;
use App\Listeners\RecordCommissionRevenueListener;
use App\Listeners\ReverseCommissionListener;
use App\Listeners\UpdateProductStatsListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPaid::class => [
            GenerateLicenseListener::class,
            CalculateCommissionListener::class,
            UpdateProductStatsListener::class,
            RecordCommissionRevenueListener::class,
        ],

        OrderRefunded::class => [
            ReverseCommissionListener::class,
        ],

        PayoutRequested::class => [
            ProcessPayoutListener::class,
        ],

        AdClicked::class => [
            RecordAdRevenueListener::class,
        ],

        OrderProcessedV4::class => [
            ProcessOrderV4Listener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
