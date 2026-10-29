<?php

namespace App\Services;

use App\Models\TaxRule;

class TaxService
{
    /**
     * Calculate tax for a transaction.
     */
    public function calculate(float $price, string $countryCode, ?string $stateCode = null, bool $isDigital = true): array
    {
        $rule = $this->getApplicableRule($countryCode, $stateCode, $isDigital);

        if (!$rule) {
            return ['tax_amount' => 0, 'tax_rate' => 0, 'tax_type' => null, 'rule_id' => null];
        }

        $taxAmount = $rule->calculate($price);

        return [
            'tax_amount' => $taxAmount,
            'tax_rate' => $rule->rate,
            'tax_type' => $rule->tax_type,
            'rule_id' => $rule->id,
        ];
    }

    /**
     * Get applicable tax rule for a country/state.
     */
    protected function getApplicableRule(string $countryCode, ?string $stateCode, bool $isDigital): ?TaxRule
    {
        $query = TaxRule::active()
            ->forCountry($countryCode)
            ->when($isDigital, fn($q) => $q->forDigital());

        // Try state-specific first
        if ($stateCode) {
            $rule = (clone $query)->where('state_code', $stateCode)->first();
            if ($rule && $rule->isEffective()) return $rule;
        }

        // Fallback to country-level
        $rule = $query->whereNull('state_code')->first();
        return ($rule && $rule->isEffective()) ? $rule : null;
    }

    /**
     * Get total price with tax included.
     */
    public function getPriceWithTax(float $price, string $countryCode, ?string $stateCode = null): float
    {
        $tax = $this->calculate($price, $countryCode, $stateCode);
        return $price + $tax['tax_amount'];
    }

    /**
     * Seed default tax rules for major regions.
     */
    public function seedDefaultRules(): void
    {
        $rules = [
            // EU VAT
            ['country_code' => 'DE', 'tax_type' => 'vat', 'rate' => 19, 'name' => 'German VAT'],
            ['country_code' => 'FR', 'tax_type' => 'vat', 'rate' => 20, 'name' => 'French VAT'],
            ['country_code' => 'NL', 'tax_type' => 'vat', 'rate' => 21, 'name' => 'Dutch VAT'],
            ['country_code' => 'IE', 'tax_type' => 'vat', 'rate' => 23, 'name' => 'Irish VAT'],
            ['country_code' => 'SE', 'tax_type' => 'vat', 'rate' => 25, 'name' => 'Swedish VAT'],
            ['country_code' => 'GB', 'tax_type' => 'vat', 'rate' => 20, 'name' => 'UK VAT'],
            // Singapore GST
            ['country_code' => 'SG', 'tax_type' => 'gst', 'rate' => 9, 'name' => 'Singapore GST'],
            // Australia GST
            ['country_code' => 'AU', 'tax_type' => 'gst', 'rate' => 10, 'name' => 'Australian GST'],
            // US Sales Tax (California example)
            ['country_code' => 'US', 'state_code' => 'CA', 'tax_type' => 'sales_tax', 'rate' => 7.25, 'name' => 'California Sales Tax'],
            ['country_code' => 'US', 'state_code' => 'NY', 'tax_type' => 'sales_tax', 'rate' => 8, 'name' => 'New York Sales Tax'],
            ['country_code' => 'US', 'state_code' => 'TX', 'tax_type' => 'sales_tax', 'rate' => 6.25, 'name' => 'Texas Sales Tax'],
            // Japan
            ['country_code' => 'JP', 'tax_type' => 'consumption_tax', 'rate' => 10, 'name' => 'Japan Consumption Tax'],
        ];

        foreach ($rules as $rule) {
            TaxRule::firstOrCreate(
                ['country_code' => $rule['country_code'], 'state_code' => $rule['state_code'] ?? null, 'tax_type' => $rule['tax_type']],
                $rule
            );
        }
    }
}
