<?php

namespace App\Listeners;

use App\Events\OrderProcessedV4;
use App\Services\LedgerService;
use App\Services\TaxService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessOrderV4Listener implements ShouldQueue
{
    public function __construct(
        protected TaxService $taxService,
        protected LedgerService $ledgerService,
    ) {}

    /**
     * V4: Tax calculation + ledger entry on order processed.
     */
    public function handle(OrderProcessedV4 $event): void
    {
        $order = $event->order;
        $country = $event->countryCode ?? $order->user?->country_code ?? 'US';
        $currency = $event->currency ?? $order->currency ?? 'USD';
        $amount = (float) $order->amount;

        // 1. Calculate tax
        $taxResult = $this->taxService->calculate($amount, $country);

        // 2. Update order with tax info
        $order->update([
            'tax_amount' => $taxResult['tax_amount'],
            'tax_rate' => $taxResult['tax_rate'],
            'tax_type' => $taxResult['tax_type'],
            'gateway' => $event->gateway ?? 'stripe',
        ]);

        // 3. Record income in multi-currency ledger
        $this->ledgerService->recordIncomeByTenant(
            tenantId: $order->tenant_id,
            amount: $amount,
            currency: $currency,
            description: "Order #{$order->id} income ({$taxResult['tax_type']}: {$taxResult['tax_rate']}%)",
            referenceType: 'order',
            referenceId: $order->id,
        );

        // 5. Record tax in ledger if applicable
        if ($taxResult['tax_amount'] > 0) {
            $this->ledgerService->recordEntry(
                tenantId: $order->tenant_id,
                type: 'tax',
                amount: $taxResult['tax_amount'],
                currency: $currency,
                description: "Tax collected: {$taxResult['tax_type']} for Order #{$order->id}",
                referenceType: 'order',
                referenceId: $order->id,
            );
        }
    }
}
