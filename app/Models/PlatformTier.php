<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformTier extends Model
{
    protected $fillable = [
        'name', 'type', 'level', 'commission_discount', 'api_quota_bonus',
        'priority_ranking', 'featured_eligible', 'promotion_access',
        'benefits', 'requirements',
    ];

    protected $casts = [
        'level' => 'integer',
        'commission_discount' => 'decimal:2',
        'api_quota_bonus' => 'integer',
        'priority_ranking' => 'boolean',
        'featured_eligible' => 'boolean',
        'promotion_access' => 'boolean',
        'benefits' => 'array',
        'requirements' => 'array',
    ];

    public function scopeDeveloper($query) { return $query->where('type', 'developer'); }
    public function scopeUser($query) { return $query->where('type', 'user'); }

    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get the effective commission rate for this tier.
     */
    public function effectiveCommissionRate(float $baseRate): float
    {
        return max(0, $baseRate - $this->commission_discount);
    }
}
