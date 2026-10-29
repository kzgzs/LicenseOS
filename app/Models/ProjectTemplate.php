<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    protected $fillable = [
        'title', 'description', 'category', 'skills',
        'budget_min', 'budget_max', 'currency', 'duration',
        'milestones', 'is_active', 'use_count',
    ];

    protected $casts = [
        'skills' => 'array',
        'milestones' => 'array',
        'is_active' => 'boolean',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
    ];

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function incrementUseCount(): void
    {
        $this->increment('use_count');
    }

    public static function popular(int $limit = 10)
    {
        return self::active()->orderByDesc('use_count')->limit($limit)->get();
    }
}
