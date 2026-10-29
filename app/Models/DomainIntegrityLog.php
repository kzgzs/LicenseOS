<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainIntegrityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'license_id', 'domain', 'code_hash',
        'expected_hash', 'api_key_hash', 'is_valid', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
