<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectContract extends Model
{
    protected $fillable = [
        'project_id', 'client_id', 'developer_id', 'bid_id',
        'total_amount', 'currency', 'platform_fee', 'developer_income',
        'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'developer_income' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function developer() { return $this->belongsTo(User::class, 'developer_id'); }
    public function bid() { return $this->belongsTo(ProjectBid::class, 'bid_id'); }
    public function milestones() { return $this->hasMany(ProjectMilestone::class, 'contract_id'); }
    public function escrows() { return $this->hasMany(ProjectEscrow::class, 'contract_id'); }
    public function disputes() { return $this->hasMany(ProjectDispute::class, 'contract_id'); }
    public function timeLogs() { return $this->hasMany(ProjectTimeLog::class, 'contract_id'); }
    public function activeDispute() { return $this->hasOne(ProjectDispute::class, 'contract_id')->whereNotIn('status', ['closed']); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isDisputed(): bool { return $this->status === 'disputed'; }

    /**
     * Calculate commission and developer income based on platform rate.
     */
    public function calculateCommission(float $platformRate): void
    {
        $fee = round((float) $this->total_amount * ($platformRate / 100), 2);
        $this->update([
            'platform_fee' => $fee,
            'developer_income' => (float) $this->total_amount - $fee,
        ]);
    }
}
