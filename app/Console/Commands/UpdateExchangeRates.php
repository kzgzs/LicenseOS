<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    protected $signature = 'exchange:rates-update';
    protected $description = 'Update currency exchange rates from external API';

    public function handle(): int
    {
        $ledgerService = app(\App\Services\LedgerService::class);
        $ledgerService->updateExchangeRates();
        $this->info('Exchange rates updated successfully.');
        return self::SUCCESS;
    }
}
