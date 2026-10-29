<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'currency', 'amount', 'amount_base',
        'exchange_rate', 'type', 'description',
        'reference_type', 'reference_id', 'region_id', 'status', 'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_base' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'settled_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reference() { return $this->morphTo(); }
    public function region() { return $this->belongsTo(Region::class); }

    public function scopeByType($query, string $type) { return $query->where('type', $type); }
    public function scopeByCurrency($query, string $currency) { return $query->where('currency', $currency); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopePending($query) { return $query->where('status', 'pending'); }

    /**
     * Record a ledger entry.
     */
    public static function record(array $data): self
    {
        $data['exchange_rate'] = $data['exchange_rate'] ?? 1.0;
        $data['amount_base'] = $data['amount'] * $data['exchange_rate'];
        $data['status'] = $data['status'] ?? 'completed';
        return self::create($data);
    }
}
