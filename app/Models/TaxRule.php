<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $fillable = [
        'country_code', 'state_code', 'tax_type', 'rate',
        'name', 'applies_to_digital', 'applies_to_business',
        'threshold', 'effective_from', 'effective_until', 'active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'applies_to_digital' => 'boolean',
        'applies_to_business' => 'boolean',
        'threshold' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'active' => 'boolean',
    ];

    public function scopeActive($query) { return $query->where('active', true); }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeForDigital($query)
    {
        return $query->where('applies_to_digital', true);
    }

    /**
     * Check if this rule is currently effective.
     */
    public function isEffective(): bool
    {
        $now = now();
        if ($this->effective_from && $now->lt($this->effective_from)) return false;
        if ($this->effective_until && $now->gt($this->effective_until)) return false;
        return $this->active;
    }

    /**
     * Calculate tax amount.
     */
    public function calculate(float $amount): float
    {
        return round($amount * ($this->rate / 100), 4);
    }
}
