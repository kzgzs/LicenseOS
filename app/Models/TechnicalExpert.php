<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalExpert extends Model
{
    protected $fillable = [
        'name', 'specialty', 'description', 'contact_method',
        'contact_value', 'qr_image', 'avatar', 'active', 'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
