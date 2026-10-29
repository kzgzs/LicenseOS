<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperBadge extends Model
{
    public const TYPES = [
        'verified'     => ['label' => 'Verified', 'icon' => 'heroicon-o-check-badge', 'color' => 'blue'],
        'top_rated'    => ['label' => 'Top Rated', 'icon' => 'heroicon-o-star', 'color' => 'yellow'],
        'fast_delivery' => ['label' => 'Fast Delivery', 'icon' => 'heroicon-o-bolt', 'color' => 'green'],
        'specialist'   => ['label' => 'Specialist', 'icon' => 'heroicon-o-academic-cap', 'color' => 'purple'],
        'partner'      => ['label' => 'Platform Partner', 'icon' => 'heroicon-o-handshake', 'color' => 'indigo'],
    ];

    protected $fillable = [
        'user_id', 'badge_type', 'label', 'description',
        'icon', 'awarded_at', 'expires_at', 'awarded_by',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function awardedByUser() { return $this->belongsTo(User::class, 'awarded_by'); }

    public function isActive(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function getColorAttribute(): string
    {
        return self::TYPES[$this->badge_type]['color'] ?? 'gray';
    }

    public function getIconNameAttribute(): string
    {
        return $this->icon ?? (self::TYPES[$this->badge_type]['icon'] ?? 'heroicon-o-tag');
    }
}
