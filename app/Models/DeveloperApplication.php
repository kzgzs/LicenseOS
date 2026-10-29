<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperApplication extends Model
{
    protected $fillable = [
        'name', 'email', 'reason', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
