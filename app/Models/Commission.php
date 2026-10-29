<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'order_id', 'tenant_id', 'affiliate_user_id',
        'platform_fee', 'developer_income', 'rate',
        'affiliate_fee', 'affiliate_rate', 'developer_tier',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function affiliate() { return $this->belongsTo(User::class, 'affiliate_user_id'); }
}
