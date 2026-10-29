<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDispute extends Model
{
    public const REASONS = [
        'not_delivered'  => 'Not Delivered',
        'quality_issue'  => 'Quality Issue',
        'scope_change'   => 'Scope Change',
        'no_response'    => 'No Response',
        'other'          => 'Other',
    ];

    public const STATUSES = [
        'open'                => 'Open',
        'under_review'        => 'Under Review',
        'resolved_client'     => 'Resolved (Client)',
        'resolved_developer'  => 'Resolved (Developer)',
        'resolved_split'      => 'Resolved (Split)',
        'closed'              => 'Closed',
    ];

    protected $fillable = [
        'contract_id', 'ticket_id', 'milestone_id', 'raised_by', 'reason',
        'description', 'evidence', 'status', 'admin_note',
        'resolved_by', 'client_refund', 'developer_payout', 'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'client_refund' => 'decimal:2',
        'developer_payout' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function milestone() { return $this->belongsTo(ProjectMilestone::class, 'milestone_id'); }
    public function raisedByUser() { return $this->belongsTo(User::class, 'raised_by'); }
    public function resolvedByUser() { return $this->belongsTo(User::class, 'resolved_by'); }

    public function isOpen(): bool { return $this->status === 'open'; }
    public function isUnderReview(): bool { return $this->status === 'under_review'; }
    public function isResolved(): bool { return str_starts_with($this->status, 'resolved_'); }
    public function isClosed(): bool { return $this->status === 'closed'; }

    public static function reasons(): array
    {
        $result = [];
        foreach (self::REASONS as $key => $label) {
            $result[$key] = 'dispute.reason_' . $key;
        }
        return $result;
    }

    public static function statuses(): array
    {
        $result = [];
        foreach (self::STATUSES as $key => $label) {
            $result[$key] = 'dispute.status_' . $key;
        }
        return $result;
    }
}
