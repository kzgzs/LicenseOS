<?php

namespace App\Console\Commands;

use App\Services\CreditScoreService;
use Illuminate\Console\Command;

class CalculateCreditScores extends Command
{
    protected $signature = 'freelance:calculate-scores {--user= : Calculate for a specific user ID}';
    protected $description = 'Calculate or update developer credit scores';

    public function handle(CreditScoreService $service): int
    {
        if ($userId = $this->option('user')) {
            $score = $service->calculate((int) $userId);
            $this->info("User {$userId}: score={$score->composite_score}, tier={$score->tier}");
            return self::SUCCESS;
        }

        $count = $service->calculateAll();
        $this->info("Calculated credit scores for {$count} developers.");
        return self::SUCCESS;
    }
}
