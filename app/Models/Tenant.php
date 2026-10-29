<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'email', 'commission_rate', 'status', 'stripe_account_id', 'balance',
        'alipay_account', 'wechat_account',
        'developer_level', 'total_revenue', 'total_sales',
        // V4: Global enterprise fields
        'region_id', 'kyc_status', 'tax_id', 'legal_entity_name',
        'country_code', 'primary_currency',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'balance' => 'decimal:2',
        'total_revenue' => 'integer',
        'total_sales' => 'integer',
    ];

    public function users() { return $this->hasMany(User::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function licenses() { return $this->hasMany(License::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function commissions() { return $this->hasMany(Commission::class); }
    public function apiKeys() { return $this->hasMany(ApiKey::class); }
    public function payouts() { return $this->hasMany(Payout::class); }

    public function getTitle(): string
    {
        return $this->name ?? $this->email ?? ('Tenant #' . $this->id);
    }

    public function isActive(): bool { return $this->status === 'active'; }

    // V3.4 tier helpers
    public function ads() { return $this->hasMany(Ad::class); }
    public function isTopDeveloper(): bool { return $this->developer_level === 'top'; }
    public function isPartner(): bool { return $this->developer_level === 'partner'; }

    // V4: Global enterprise
    public function region() { return $this->belongsTo(Region::class); }
    public function ledgerEntries() { return $this->hasMany(LedgerEntry::class); }

    public function availableBalance(): float
    {
        $totalIncome = $this->commissions()->sum('developer_income');
        $totalPaidOut = $this->payouts()
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->sum('amount');
        return round((float) ($totalIncome - $totalPaidOut), 2);
    }

    public function totalIncome(): float
    {
        return round((float) $this->commissions()->sum('developer_income'), 2);
    }

    public function totalPaidOut(): float
    {
        return round((float) $this->payouts()->where('status', 'paid')->sum('amount'), 2);
    }
}
