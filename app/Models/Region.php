<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'code', 'name', 'display_name', 'timezone',
        'primary_currency', 'supported_currencies',
        'stripe_account_id', 'adyen_account_id',
        'is_primary', 'status', 'config',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'is_primary' => 'boolean',
        'config' => 'array',
    ];

    public function tenants() { return $this->hasMany(Tenant::class); }
    public function ledgerEntries() { return $this->hasMany(LedgerEntry::class); }

    public function scopeActive($query) { return $query->where('status', 'active'); }

    public function isActive(): bool { return $this->status === 'active'; }

    public function supportsCurrency(string $currency): bool
    {
        if ($this->primary_currency === $currency) return true;
        return in_array($currency, $this->supported_currencies ?? []);
    }
}
