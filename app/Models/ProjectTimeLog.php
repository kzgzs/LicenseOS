<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTimeLog extends Model
{
    protected $fillable = [
        'contract_id', 'milestone_id', 'developer_id', 'description',
        'minutes', 'started_at', 'ended_at', 'amount', 'status',
    ];

    protected $casts = [
        'minutes' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function milestone() { return $this->belongsTo(ProjectMilestone::class, 'milestone_id'); }
    public function developer() { return $this->belongsTo(User::class, 'developer_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isBilled(): bool { return $this->status === 'billed'; }

    public function getHoursFormattedAttribute(): string
    {
        $h = floor($this->minutes / 60);
        $m = $this->minutes % 60;
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
