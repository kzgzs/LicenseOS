<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetApiQuotas extends Command
{
    protected $signature = 'api:reset-quotas';
    protected $description = 'Reset monthly API quotas for all active keys';

    public function handle(): int
    {
        $quotaService = app(\App\Services\ApiQuotaService::class);
        $count = $quotaService->resetMonthlyQuotas();
        $this->info("Reset quotas for {$count} API keys.");
        return self::SUCCESS;
    }
}
