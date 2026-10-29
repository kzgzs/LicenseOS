<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectReview extends Model
{
    protected $fillable = [
        'contract_id', 'reviewer_id', 'reviewee_id',
        'rating', 'comment', 'communication', 'quality', 'timeliness',
    ];

    protected $casts = [
        'rating' => 'integer',
        'communication' => 'integer',
        'quality' => 'integer',
        'timeliness' => 'integer',
    ];

    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function reviewee() { return $this->belongsTo(User::class, 'reviewee_id'); }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('reviewee_id', $userId);
    }

    public function getAvgSubScoreAttribute(): float
    {
        return round(($this->communication + $this->quality + $this->timeliness) / 3, 1);
    }
}
