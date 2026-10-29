<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Process unprocessed platform events every minute
        $schedule->command('platform:process-events')->everyMinute();

        // Auto-expire licenses daily
        $schedule->command('license:expire-check')->daily();

        // Expire finished ad campaigns daily
        $schedule->command('ads:expire')->dailyAt('00:05');

        // Reset API quotas monthly
        $schedule->command('api:reset-quotas')->monthlyOn(1, '00:00');

        // Evaluate developer tiers weekly
        $schedule->command('tiers:evaluate')->weeklyOn(1, '02:00');

        // Update exchange rates every 6 hours
        $schedule->command('exchange:rates-update')->everySixHours();

        // Auto-approve freelance milestones after review period
        $schedule->command('freelance:auto-approve')->hourly();

        // Calculate developer credit scores daily
        $schedule->command('freelance:calculate-scores')->dailyAt('03:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
