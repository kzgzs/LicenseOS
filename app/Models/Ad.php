<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title', 'type', 'bid_price', 'daily_budget', 'total_spent',
        'start_at', 'end_at', 'product_id', 'tenant_id', 'status',
        'impressions', 'clicks', 'target_keywords', 'banner_image', 'landing_url',
    ];

    protected $casts = [
        'bid_price' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'start_at' => 'date',
        'end_at' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_at <= now()
            && $this->end_at >= now();
    }

    public function recordImpression(): void
    {
        $this->increment('impressions');
    }

    public function recordClick(): void
    {
        $this->increment('clicks');
        $this->increment('total_spent', $this->bid_price);
    }

    public function ctr(): float
    {
        if ($this->impressions === 0) return 0;
        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function budgetRemaining(): float
    {
        if (!$this->daily_budget) return PHP_FLOAT_MAX;
        return max(0, $this->daily_budget - $this->total_spent);
    }
}
