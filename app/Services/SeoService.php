<?php

namespace App\Services;

use App\Models\Product;

class SeoService
{
    /**
     * Generate complete SEO metadata for a product page.
     */
    public function generateProductMeta(Product $product): array
    {
        return [
            'title' => $this->buildTitle($product),
            'description' => $this->buildDescription($product),
            'keywords' => $this->buildKeywords($product),
            'og_type' => 'product',
            'og_title' => $product->meta_title ?: $product->name,
            'og_description' => $product->meta_description ?: $product->short_description,
            'canonical' => $this->buildCanonicalUrl($product),
            'structured_data' => $this->generateStructuredData($product),
        ];
    }

    /**
     * Build page title with fallback.
     */
    public function buildTitle(Product $product): string
    {
        if ($product->meta_title) {
            return $product->meta_title;
        }

        $parts = [$product->name];

        if ($product->short_description) {
            $parts[] = $product->short_description;
        }

        $title = implode(' - ', $parts);

        // Keep under 60 chars for SEO
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57) . '...';
        }

        return $title;
    }

    /**
     * Build meta description with fallback.
     */
    public function buildDescription(Product $product): string
    {
        if ($product->meta_description) {
            return $product->meta_description;
        }

        $desc = $product->short_description ?: $product->description ?: '';

        // Strip HTML and truncate
        $desc = strip_tags($desc);
        if (mb_strlen($desc) > 160) {
            $desc = mb_substr($desc, 0, 157) . '...';
        }

        return $desc;
    }

    /**
     * Build keyword list combining explicit keywords and tags.
     */
    public function buildKeywords(Product $product): array
    {
        $keywords = is_array($product->keywords) ? $product->keywords : [];
        $tags = is_array($product->tags) ? $product->tags : [];

        // Merge and deduplicate
        $all = array_unique(array_merge($keywords, $tags));

        if ($product->category && !in_array($product->category->name, $all)) {
            $all[] = $product->category->name;
        }

        return array_slice($all, 0, 10);
    }

    /**
     * Build canonical URL for a product.
     */
    public function buildCanonicalUrl(Product $product): string
    {
        return url("/products/{$product->slug}");
    }

    /**
     * Generate JSON-LD structured data for search engines.
     */
    public function generateStructuredData(Product $product): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $product->name,
            'description' => $this->buildDescription($product),
            'applicationCategory' => 'BusinessApplication',
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($product->price, 2, '.', ''),
                'priceCurrency' => 'USD',
                'availability' => $product->status === 'published'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($product->rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->rating,
                'bestRating' => '5',
                'worstRating' => '1',
                'ratingCount' => $product->reviews()->count(),
            ];
        }

        return $data;
    }

    /**
     * Generate sitemap entries for all published products.
     */
    public function generateSitemapEntries(): array
    {
        return Product::published()
            ->select('slug', 'updated_at')
            ->get()
            ->map(fn(Product $p) => [
                'url' => $this->buildCanonicalUrl($p),
                'lastmod' => $p->updated_at->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ])
            ->toArray();
    }

    /**
     * Share SEO meta with a Blade view.
     */
    public function shareWithView(Product $product): array
    {
        return $this->generateProductMeta($product);
    }
}
