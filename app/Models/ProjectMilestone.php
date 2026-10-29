<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    protected $fillable = [
        'contract_id', 'title', 'description', 'amount', 'status',
        'funded_at', 'submitted_at', 'auto_approve_at', 'approved_at', 'released_at',
        'deliverable_note', 'deliverable_files', 'revision_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'funded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'auto_approve_at' => 'datetime',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'deliverable_files' => 'array',
    ];

    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function escrow() { return $this->hasOne(ProjectEscrow::class, 'milestone_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isFunded(): bool { return $this->status === 'funded'; }
    public function isSubmitted(): bool { return $this->status === 'submitted'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isReleased(): bool { return $this->status === 'released'; }
    public function isDisputed(): bool { return $this->status === 'disputed'; }
}
