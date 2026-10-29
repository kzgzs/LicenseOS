<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBid extends Model
{
    protected $fillable = [
        'project_id', 'developer_id', 'amount', 'currency',
        'proposal', 'delivery_days', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'delivery_days' => 'integer',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function developer() { return $this->belongsTo(User::class, 'developer_id'); }
    public function contract() { return $this->hasOne(ProjectContract::class, 'bid_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isWithdrawn(): bool { return $this->status === 'withdrawn'; }

    public function scopePending($query) { return $query->where('status', 'pending'); }
}
