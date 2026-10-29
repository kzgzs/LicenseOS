<?php

namespace App\Console\Commands;

use App\Models\License;
use Illuminate\Console\Command;

class ExpireLicenseCheck extends Command
{
    protected $signature = 'license:expire-check';
    protected $description = 'Mark expired licenses as expired';

    public function handle(): int
    {
        $count = License::where('status', 'active')
            ->where('expire_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} licenses.");
        return self::SUCCESS;
    }
}
