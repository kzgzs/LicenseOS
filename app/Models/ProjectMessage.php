<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMessage extends Model
{
    protected $fillable = [
        'project_id', 'contract_id', 'sender_id', 'body', 'attachment', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
