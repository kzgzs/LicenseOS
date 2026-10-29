<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'tenant_id', 'amount', 'fee', 'method', 'status',
        'reference_id', 'note',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markPaid(): void
    {
        $this->update(['status' => 'paid']);
    }

    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
