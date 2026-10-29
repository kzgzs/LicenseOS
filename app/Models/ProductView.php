<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'session_id', 'ip', 'referrer', 'source',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->where('created_at', '>=', now()->startOfWeek());
    }

    public function scopeThisMonth($query)
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    /**
     * Detect traffic source from referrer URL.
     */
    public static function detectSource(?string $referrer): string
    {
        if (!$referrer) {
            return 'direct';
        }

        $host = parse_url($referrer, PHP_URL_HOST) ?? '';

        return match (true) {
            str_contains($host, 'google') => 'google',
            str_contains($host, 'bing') || str_contains($host, 'yahoo') => 'search',
            str_contains($host, 'twitter') || str_contains($host, 'facebook')
                || str_contains($host, 'linkedin') || str_contains($host, 'reddit') => 'social',
            str_contains($host, 'youtube') => 'video',
            $host !== '' => 'referral',
            default => 'direct',
        };
    }
}
