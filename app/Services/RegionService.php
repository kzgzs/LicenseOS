<?php

namespace App\Services;

use App\Models\Region;

class RegionService
{
    /**
     * Get all active regions.
     */
    public function getActiveRegions(): \Illuminate\Database\Eloquent\Collection
    {
        return Region::active()->get();
    }

    /**
     * Resolve region by country code.
     */
    public function resolveByCountry(string $countryCode): ?Region
    {
        return match (true) {
            in_array($countryCode, ['US', 'CA', 'MX']) => Region::where('code', 'us')->first(),
            in_array($countryCode, ['DE', 'FR', 'NL', 'BE', 'AT', 'IT', 'ES', 'PT', 'IE', 'FI', 'SE', 'DK', 'NO', 'CH', 'PL', 'GB', 'CZ', 'HU', 'RO', 'BG', 'HR', 'SK', 'SI', 'LT', 'LV', 'EE', 'CY', 'MT', 'LU', 'GR']) => Region::where('code', 'eu')->first(),
            in_array($countryCode, ['SG', 'MY', 'TH', 'ID', 'PH', 'VN', 'AU', 'NZ', 'IN', 'KR', 'TW', 'HK']) => Region::where('code', 'sg')->first(),
            in_array($countryCode, ['SA', 'AE', 'QA', 'KW', 'BH', 'OM', 'JO', 'LB', 'EG']) => Region::where('code', 'me')->first(),
            default => Region::where('is_primary', true)->first(),
        };
    }

    /**
     * Get primary region.
     */
    public function getPrimaryRegion(): Region
    {
        return Region::where('is_primary', true)->first()
            ?? Region::first();
    }

    /**
     * Seed default regions.
     */
    public function seedDefaultRegions(): void
    {
        $regions = [
            [
                'code' => 'us', 'name' => 'United States', 'display_name' => 'Americas',
                'timezone' => 'America/New_York', 'primary_currency' => 'USD',
                'supported_currencies' => ['USD', 'CAD', 'MXN', 'BRL'],
                'is_primary' => true,
            ],
            [
                'code' => 'eu', 'name' => 'Europe', 'display_name' => 'Europe',
                'timezone' => 'Europe/Berlin', 'primary_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN'],
                'is_primary' => false,
            ],
            [
                'code' => 'sg', 'name' => 'Singapore', 'display_name' => 'Asia Pacific',
                'timezone' => 'Asia/Singapore', 'primary_currency' => 'SGD',
                'supported_currencies' => ['SGD', 'AUD', 'NZD', 'INR', 'JPY', 'CNY', 'KRW', 'TWD', 'HKD'],
                'is_primary' => false,
            ],
            [
                'code' => 'me', 'name' => 'Middle East', 'display_name' => 'Middle East',
                'timezone' => 'Asia/Riyadh', 'primary_currency' => 'SAR',
                'supported_currencies' => ['SAR', 'AED', 'QAR', 'KWD'],
                'is_primary' => false,
            ],
        ];

        foreach ($regions as $region) {
            Region::firstOrCreate(['code' => $region['code']], $region);
        }
    }

    /**
     * Get region analytics.
     */
    public function getAnalytics(): array
    {
        return Region::withCount(['tenants', 'ledgerEntries'])
            ->withSum('ledgerEntries', 'amount_base')
            ->active()
            ->get()
            ->map(fn($r) => [
                'code' => $r->code,
                'name' => $r->display_name,
                'tenants' => $r->tenants_count,
                'transactions' => $r->ledger_entries_count,
                'revenue_usd' => $r->ledger_entries_sum_amount_base ?? 0,
            ])
            ->toArray();
    }
}
