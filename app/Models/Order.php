<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'tenant_id', 'product_id', 'user_id', 'referred_by',
        'amount', 'currency', 'status', 'payment_method',
        'payment_intent_id', 'checkout_session_id', 'refunded_at',
        // Pricing tier fields
        'tier_months', 'tier_days', 'tier_label', 'domain', 'is_trial',
        // V4: Global enterprise fields
        'tax_amount', 'tax_rate', 'tax_type', 'invoice_number', 'region_id', 'gateway',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'refunded_at' => 'datetime',
        'tier_months' => 'integer',
        'tier_days' => 'integer',
        'is_trial' => 'boolean',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function buyer() { return $this->belongsTo(User::class, 'user_id'); }
    public function affiliate() { return $this->belongsTo(User::class, 'referred_by'); }
    public function commission() { return $this->hasOne(Commission::class); }
    public function licenses() { return $this->hasMany(License::class); }
    public function refunds() { return $this->hasMany(Refund::class); }

    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isRefunded(): bool { return $this->status === 'refunded'; }
    public function scopePaid($query) { return $query->where('status', 'paid'); }
    public function scopeRefunded($query) { return $query->where('status', 'refunded'); }

    // V4: Global enterprise
    public function region() { return $this->belongsTo(Region::class); }
    public function ledgerEntries() { return $this->morphMany(LedgerEntry::class, 'reference'); }
    public function user() { return $this->belongsTo(User::class); }

    public function getTaxedTotal(): float
    {
        return (float) $this->amount + (float) $this->tax_amount;
    }
}
