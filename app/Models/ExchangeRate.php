<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['from_currency', 'to_currency', 'rate', 'fetched_at'];

    protected $casts = [
        'rate' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get current exchange rate.
     */
    public static function getRate(string $from, string $to): float
    {
        if ($from === $to) return 1.0;

        $rate = self::where('from_currency', $from)
            ->where('to_currency', $to)
            ->latest('fetched_at')
            ->first();

        return $rate?->rate ?? 1.0;
    }

    /**
     * Convert amount between currencies.
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        return round($amount * self::getRate($from, $to), 4);
    }
}
