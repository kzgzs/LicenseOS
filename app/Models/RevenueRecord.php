<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueRecord extends Model
{
    protected $fillable = [
        'source', 'amount', 'description', 'recordable_type', 'recordable_id', 'period_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_date' => 'date',
    ];

    public function recordable() { return $this->morphTo(); }

    public function scopeBySource($query, string $source) { return $query->where('source', $source); }

    public function scopeInPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('period_date', [$from, $to]);
    }

    public function scopeToday($query)
    {
        return $query->where('period_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->where('period_date', '>=', now()->startOfMonth())
            ->where('period_date', '<=', now()->endOfMonth());
    }

    /**
     * Create a revenue record for any source.
     */
    public static function record(string $source, float $amount, Model $recordable, ?string $description = null): self
    {
        return self::create([
            'source' => $source,
            'amount' => $amount,
            'recordable_type' => get_class($recordable),
            'recordable_id' => $recordable->id,
            'description' => $description,
            'period_date' => today(),
        ]);
    }
}
