<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EvaluateDeveloperTiers extends Command
{
    protected $signature = 'tiers:evaluate';
    protected $description = 'Evaluate and update developer tier levels based on performance';

    public function handle(): int
    {
        $tierService = app(\App\Services\TierService::class);
        $results = $tierService->evaluateAllDevelopers();

        $this->info("Evaluated all developers. " . count($results) . " tier changes:");

        foreach ($results as $change) {
            $this->line("  Tenant #{$change['tenant_id']}: {$change['old_level']} → {$change['new_level']}");
        }

        return self::SUCCESS;
    }
}
