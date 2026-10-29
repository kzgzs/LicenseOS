<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
    protected $fillable = [
        'product_id', 'locale', 'name', 'slug',
        'short_description', 'description',
        'meta_title', 'meta_description', 'keywords', 'readme_sections',
    ];

    protected $casts = [
        'keywords' => 'array',
        'readme_sections' => 'array',
    ];

    public function product() { return $this->belongsTo(Product::class); }

    public function scopeForLocale($query, string $locale) { return $query->where('locale', $locale); }

    /**
     * Get translation for a product in a specific locale.
     */
    public static function getForProduct(Product $product, string $locale): ?self
    {
        return self::where('product_id', $product->id)
            ->where('locale', $locale)
            ->first();
    }
}
