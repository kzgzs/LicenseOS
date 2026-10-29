<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class LedgerService
{
    /**
     * Record a generic ledger entry by tenant ID.
     */
    public function recordEntry(int $tenantId, string $type, float $amount, string $currency, string $description, ?string $referenceType = null, ?int $referenceId = null): LedgerEntry
    {
        $rate = ExchangeRate::getRate($currency, 'USD');

        $data = [
            'tenant_id' => $tenantId,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => $rate,
            'type' => $type,
            'description' => $description,
        ];

        if ($referenceType && $referenceId) {
            $data['reference_type'] = $referenceType;
            $data['reference_id'] = $referenceId;
        }

        return LedgerEntry::record($data);
    }

    /**
     * Record income entry by tenant ID.
     */
    public function recordIncomeByTenant(int $tenantId, float $amount, string $currency, string $description, ?string $referenceType = null, ?int $referenceId = null): LedgerEntry
    {
        return $this->recordEntry($tenantId, 'income', $amount, $currency, $description, $referenceType, $referenceId);
    }

    /**
     * Record income entry in ledger (original signature with Tenant model).
     */
    public function recordIncome(Tenant $tenant, float $amount, string $currency, string $description, $reference = null): LedgerEntry
    {
        $rate = ExchangeRate::getRate($currency, 'USD');

        $data = [
            'tenant_id' => $tenant->id,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => $rate,
            'type' => 'income',
            'description' => $description,
            'region_id' => $tenant->region_id,
        ];

        if ($reference) {
            $data['reference_type'] = get_class($reference);
            $data['reference_id'] = $reference->id;
        }

        return LedgerEntry::record($data);
    }

    /**
     * Record payout entry.
     */
    public function recordPayout(Tenant $tenant, float $amount, string $currency, string $description): LedgerEntry
    {
        $rate = ExchangeRate::getRate($currency, 'USD');

        return LedgerEntry::record([
            'tenant_id' => $tenant->id,
            'currency' => $currency,
            'amount' => -$amount,
            'exchange_rate' => $rate,
            'type' => 'payout',
            'description' => $description,
            'region_id' => $tenant->region_id,
        ]);
    }

    /**
     * Get tenant balance in base currency (USD).
     */
    public function getBalance(Tenant $tenant, ?string $currency = null): float
    {
        $query = LedgerEntry::where('tenant_id', $tenant->id)->completed();

        if ($currency) {
            $query->where('currency', $currency);
            return (float) $query->sum('amount');
        }

        return (float) $query->sum('amount_base');
    }

    /**
     * Get ledger entries for a tenant.
     */
    public function getEntries(Tenant $tenant, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return LedgerEntry::where('tenant_id', $tenant->id)
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($filters['currency'] ?? null, fn($q, $c) => $q->where('currency', $c))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->where('created_at', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->where('created_at', '<=', $t))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get platform-wide revenue by currency.
     */
    public function getRevenueByCurrency(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now();

        return LedgerEntry::completed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('currency, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('currency')
            ->get()
            ->toArray();
    }

    /**
     * Update exchange rates (fetch from external API).
     */
    public function updateExchangeRates(): void
    {
        $pairs = [
            ['EUR', 'USD'], ['GBP', 'USD'], ['CNY', 'USD'],
            ['JPY', 'USD'], ['SGD', 'USD'], ['INR', 'USD'],
        ];

        // Default rates (in production, fetch from forex API)
        $defaultRates = [
            'EUR' => 1.08, 'GBP' => 1.27, 'CNY' => 0.14,
            'JPY' => 0.0067, 'SGD' => 0.74, 'INR' => 0.012,
        ];

        foreach ($pairs as [$from, $to]) {
            ExchangeRate::create([
                'from_currency' => $from,
                'to_currency' => $to,
                'rate' => $defaultRates[$from] ?? 1.0,
                'fetched_at' => now(),
            ]);
        }
    }
}
