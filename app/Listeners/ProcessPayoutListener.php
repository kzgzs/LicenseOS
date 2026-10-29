<?php

namespace App\Listeners;

use App\Events\PayoutRequested;
use App\Notifications\PayoutProcessedNotification;
use App\Services\PayoutService;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessPayoutListener implements ShouldQueue
{
    public int $tries = 5;
    public int $backoff = 120;

    public function __construct(
        protected PayoutService $payoutService,
        protected SmsService $smsService,
    ) {}

    public function handle(PayoutRequested $event): void
    {
        $payout = $event->payout;

        if (!$payout->isPending()) {
            return;
        }

        $payout->markProcessing();

        try {
            $this->payoutService->process($payout);
            $payout->markPaid();

            // Notify developer via in-app notification
            if ($payout->tenant) {
                $devUsers = $payout->tenant->users()->get();
                foreach ($devUsers as $user) {
                    $user->notify(new PayoutProcessedNotification($payout));
                }
            }

            // Send payout success SMS
            $phone = $this->getTenantPhone($payout);
            if ($phone) {
                try {
                    $this->smsService->sendPayoutSuccess($phone, (float) ($payout->amount - $payout->fee));
                } catch (\Exception $e) {
                    Log::warning('Failed to send payout success SMS: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $payout->update([
                'status' => 'failed',
                'note' => 'Processing failed: ' . $e->getMessage(),
            ]);

            // Send payout failed SMS
            $phone = $this->getTenantPhone($payout);
            if ($phone) {
                try {
                    $this->smsService->sendPayoutFailed($phone, $e->getMessage());
                } catch (\Exception $smsEx) {
                    Log::warning('Failed to send payout failure SMS: ' . $smsEx->getMessage());
                }
            }

            throw $e;
        }
    }

    protected function getTenantPhone($payout): ?string
    {
        $tenant = $payout->tenant;
        if (!$tenant) {
            return null;
        }
        // Get phone from first user with a phone number
        return $tenant->users()->whereNotNull('phone')->where('phone', '!=', '')->value('phone');
    }
}
