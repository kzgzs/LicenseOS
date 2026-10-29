<?php

namespace App\Console\Commands;

use App\Models\PlatformEvent;
use Illuminate\Console\Command;

class ProcessPlatformEvents extends Command
{
    protected $signature = 'platform:process-events {--limit=50}';
    protected $description = 'Process unprocessed platform events';

    public function handle(): int
    {
        $service = app(\App\Services\EventService::class);
        
        $events = PlatformEvent::unprocessed()
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info("Processing {$events->count()} events...");

        foreach ($events as $event) {
            try {
                $service->process($event);
                $this->line("  [OK] {$event->type} #{$event->id}");
            } catch (\Throwable $e) {
                $this->error("  [FAIL] {$event->type} #{$event->id}: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
