<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperScore extends Model
{
    protected $fillable = [
        'user_id', 'completion_rate', 'response_speed', 'avg_rating',
        'project_count', 'dispute_count', 'total_earned',
        'composite_score', 'tier', 'last_calculated_at',
    ];

    protected $casts = [
        'completion_rate' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'composite_score' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];

    public const TIERS = [
        'bronze'   => 'Bronze',
        'silver'   => 'Silver',
        'gold'     => 'Gold',
        'platinum' => 'Platinum',
        'diamond'  => 'Diamond',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public static function tierThresholds(): array
    {
        return [
            'bronze'   => 0,
            'silver'   => 40,
            'gold'     => 60,
            'platinum' => 80,
            'diamond'  => 95,
        ];
    }

    public static function tierFromScore(float $score): string
    {
        $thresholds = self::tierThresholds();
        $tier = 'bronze';
        foreach ($thresholds as $t => $min) {
            if ($score >= $min) $tier = $t;
        }
        return $tier;
    }

    public static function tierLabel(string $tier): string
    {
        return self::TIERS[$tier] ?? 'Bronze';
    }

    public function getTierLabelAttribute(): string
    {
        return self::tierLabel($this->tier);
    }

    public static function tierBadgeClass(string $tier): string
    {
        return match ($tier) {
            'diamond'  => 'bg-purple-100 text-purple-700 border-purple-300',
            'platinum' => 'bg-indigo-100 text-indigo-700 border-indigo-300',
            'gold'     => 'bg-yellow-100 text-yellow-700 border-yellow-300',
            'silver'   => 'bg-gray-100 text-gray-600 border-gray-300',
            default    => 'bg-orange-100 text-orange-600 border-orange-300',
        };
    }
}
