<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'key', 'active',
        'quota', 'used_quota', 'overage_rate', 'tier', 'quota_reset_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'quota' => 'integer',
        'used_quota' => 'integer',
        'overage_rate' => 'decimal:4',
        'quota_reset_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function usageLogs() { return $this->hasMany(ApiUsageLog::class); }

    public static function generateFor(Tenant $tenant, ?User $user = null): self
    {
        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'key' => 'losk_' . Str::random(40),
            'active' => true,
            'quota' => 1000,
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['active' => false]);
    }

    // V3.4 Quota management
    public function hasQuotaRemaining(): bool
    {
        return $this->used_quota < $this->quota;
    }

    public function consumeQuota(): void
    {
        $this->increment('used_quota');
    }

    public function quotaUsagePercent(): float
    {
        if ($this->quota === 0) return 100;
        return round(($this->used_quota / $this->quota) * 100, 1);
    }

    public function resetQuota(): void
    {
        $this->update([
            'used_quota' => 0,
            'quota_reset_at' => now(),
        ]);
    }

    public function overageAmount(): float
    {
        $overage = max(0, $this->used_quota - $this->quota);
        return round($overage * $this->overage_rate, 2);
    }
}
