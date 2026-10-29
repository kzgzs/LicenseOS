<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'api_key_id', 'endpoint', 'method', 'response_code', 'response_time_ms', 'ip',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_time_ms' => 'integer',
    ];

    public function apiKey() { return $this->belongsTo(ApiKey::class); }

    public function scopeForKey($query, int $apiKeyId)
    {
        return $query->where('api_key_id', $apiKeyId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
