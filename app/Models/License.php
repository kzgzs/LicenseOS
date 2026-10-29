<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = [
        'tenant_id', 'product_id', 'order_id',
        'license_key', 'status', 'max_domains', 'expire_at', 'is_trial',
        'is_tampered', 'tampered_at',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'is_trial' => 'boolean',
        'is_tampered' => 'boolean',
        'tampered_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function activations() { return $this->hasMany(Activation::class); }
}
