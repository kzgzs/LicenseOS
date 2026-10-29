<?php

namespace App\Listeners;

use App\Events\AdClicked;
use App\Models\RevenueRecord;

class RecordAdRevenueListener
{
    public function handle(AdClicked $event): void
    {
        $ad = $event->ad;

        // Record revenue from ad click
        RevenueRecord::record('ad', $ad->bid_price, $ad, 'Ad click revenue: ' . $ad->title);
    }
}
