<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_purchase',
        'max_uses', 'used_count', 'product_id',
        'expires_at', 'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForProduct($query, ?int $productId)
    {
        return $query->where(function ($q) use ($productId) {
            $q->whereNull('product_id')->orWhere('product_id', $productId);
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasReachedLimit(): bool
    {
        return $this->max_uses && $this->used_count >= $this->max_uses;
    }

    public function isValid(): bool
    {
        return $this->active && !$this->isExpired() && !$this->hasReachedLimit();
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->type === 'percentage') {
            return round($price * ($this->value / 100), 2);
        }

        return min($this->value, $price);
    }
}
