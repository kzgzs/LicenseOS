<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAccessTier extends Model
{
    protected $fillable = [
        'name', 'code', 'rate_limit_per_minute', 'monthly_quota',
        'requires_auth', 'requires_approval', 'allowed_endpoints', 'cost_per_request',
    ];

    protected $casts = [
        'rate_limit_per_minute' => 'integer',
        'monthly_quota' => 'integer',
        'requires_auth' => 'boolean',
        'requires_approval' => 'boolean',
        'allowed_endpoints' => 'array',
        'cost_per_request' => 'decimal:4',
    ];

    public function canAccessEndpoint(string $endpoint): bool
    {
        if (empty($this->allowed_endpoints)) return true;
        return in_array($endpoint, $this->allowed_endpoints);
    }
}
