<?php

namespace App\Services;

use App\Events\PayoutRequested;
use App\Models\Payout;
use App\Models\Tenant;

class PayoutService
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    /**
     * Create a payout request for a tenant.
     */
    public function requestPayout(Tenant $tenant, float $amount, string $method = 'stripe'): Payout
    {
        $minAmount = config('services.platform.payout_min_amount', 10);

        $cs = \App\Models\SiteSetting::get('currency_symbol', '¥');

        if ($amount < $minAmount) {
            throw new \RuntimeException("Minimum payout amount is {$cs}{$minAmount}.");
        }

        $available = $tenant->availableBalance();

        if ($amount > $available) {
            throw new \RuntimeException("Insufficient balance. Available: {$cs}{$available}");
        }

        // Calculate platform fee on withdrawal (1%)
        $fee = round($amount * 0.01, 2);

        $payout = Payout::create([
            'tenant_id' => $tenant->id,
            'amount' => $amount,
            'fee' => $fee,
            'method' => $method,
            'status' => 'pending',
        ]);

        // Dispatch event to process asynchronously
        PayoutRequested::dispatch($payout);

        return $payout;
    }

    /**
     * Process a payout via the appropriate payment gateway.
     */
    public function process(Payout $payout): void
    {
        $tenant = $payout->tenant;
        $netAmount = $payout->amount - $payout->fee;

        switch ($payout->method) {
            case 'stripe':
                $this->processStripePayout($tenant, $netAmount, $payout);
                break;

            case 'alipay':
                $this->processAlipayPayout($tenant, $netAmount, $payout);
                break;

            case 'wechat':
                $this->processWechatPayout($tenant, $netAmount, $payout);
                break;

            default:
                throw new \RuntimeException("Unsupported payout method: {$payout->method}");
        }
    }

    /**
     * Process payout via Stripe Connect transfer.
     */
    protected function processStripePayout(Tenant $tenant, float $netAmount, Payout $payout): void
    {
        if (!$tenant->stripe_account_id) {
            throw new \RuntimeException('Developer has no Stripe Connect account.');
        }

        try {
            $transfer = \Stripe\Transfer::create([
                'amount' => (int) round($netAmount * 100),
                'currency' => 'usd',
                'destination' => $tenant->stripe_account_id,
                'metadata' => [
                    'payout_id' => $payout->id,
                    'tenant_id' => $tenant->id,
                ],
            ]);

            $payout->update([
                'reference_id' => $transfer->id,
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Stripe transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Process payout via Alipay (placeholder for production SDK integration).
     */
    protected function processAlipayPayout(Tenant $tenant, float $netAmount, Payout $payout): void
    {
        // In production, integrate Alipay's transfer API:
        // alipay.fund.trans.uni.transfer
        // This requires the official Alipay SDK and proper certification.

        $payout->update([
            'reference_id' => 'alipay_' . $payout->id . '_' . time(),
            'note' => 'Alipay payout submitted for processing.',
        ]);
    }

    /**
     * Process payout via WeChat Pay (placeholder for production SDK integration).
     */
    protected function processWechatPayout(Tenant $tenant, float $netAmount, Payout $payout): void
    {
        // In production, integrate WeChat Pay's enterprise payment API:
        // POST /mch/transfer/add
        // This requires WeChat Pay merchant credentials.

        $payout->update([
            'reference_id' => 'wechat_' . $payout->id . '_' . time(),
            'note' => 'WeChat payout submitted for processing.',
        ]);
    }
}
