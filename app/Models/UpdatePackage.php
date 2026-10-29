<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpdatePackage extends Model
{
    protected $fillable = [
        'product_id', 'version', 'file_url', 'original_file_url', 'has_original', 'injection_modules', 'changelog',
    ];

    protected $casts = [
        'has_original' => 'boolean',
        'injection_modules' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }
}
