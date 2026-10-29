<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireAdCampaigns extends Command
{
    protected $signature = 'ads:expire';
    protected $description = 'Expire ad campaigns that have passed their end date';

    public function handle(): int
    {
        $adService = app(\App\Services\AdService::class);
        $count = $adService->expireAds();
        $this->info("Expired {$count} ad campaigns.");
        return self::SUCCESS;
    }
}
