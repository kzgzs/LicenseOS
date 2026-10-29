<?php

namespace App\Console\Commands;

use App\Models\ProjectMilestone;
use App\Models\ProjectEscrow;
use Illuminate\Console\Command;

class AutoApproveMilestones extends Command
{
    protected $signature = 'freelance:auto-approve';
    protected $description = 'Auto-approve milestones that passed the review period';

    public function handle(): int
    {
        $milestones = ProjectMilestone::where('status', 'submitted')
            ->whereNotNull('auto_approve_at')
            ->where('auto_approve_at', '<=', now())
            ->with(['contract.milestones', 'escrow'])
            ->get();

        $count = 0;

        foreach ($milestones as $milestone) {
            // Auto-approve
            $milestone->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // Auto-release funds
            $escrow = $milestone->escrow;
            if ($escrow && $escrow->isFunded()) {
                $escrow->releaseToDeveloper();
            }

            // Check if all milestones released → complete contract
            $contract = $milestone->contract;
            $allReleased = $contract->milestones()
                ->whereNotIn('status', ['released'])
                ->count() === 0;

            if ($allReleased) {
                $contract->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                $contract->project->update(['status' => 'completed']);
            }

            $count++;
        }

        $this->info("Auto-approved {$count} milestones.");
        return self::SUCCESS;
    }
}
