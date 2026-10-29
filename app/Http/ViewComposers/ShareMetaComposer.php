<?php

namespace App\Http\ViewComposers;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShareMetaComposer
{
    /**
     * Inject social-share / SEO meta data into the main layout.
     *
     * Rules:
     * - meta description: always injected (product short_description -> default description)
     * - Open Graph: only when share_enabled setting is on
     * - JSON-LD: always injected (SoftwareApplication for product pages, WebSite otherwise)
     */
    public function compose(View $view): void
    {
        try {
            $data = $view->getData();
            $product = ($data['product'] ?? null) instanceof Product ? $data['product'] : null;
            $isProduct = $product !== null && request()->routeIs('product.show');

            $siteName = SiteSetting::get('site_name', config('app.name', 'Platform'));
            $siteName = (__($siteName) !== $siteName) ? __($siteName) : $siteName;

            $shareEnabled = SiteSetting::getBoolean('share_enabled', true);

            // Default description (fallback chain: share_default_description -> seo_description)
            $defaultDescription = trim((string) SiteSetting::get('share_default_description', ''));
            if ($defaultDescription === '') {
                $defaultDescription = trim((string) SiteSetting::get('seo_description', ''));
            }

            // Description chain: product short_description (localized when available) -> default
            $description = $defaultDescription;
            if ($isProduct) {
                $localized = trim((string) ($data['localizedShortDescription'] ?? ''));
                $raw = trim((string) ($product->short_description ?? ''));
                $candidate = $localized !== '' ? $localized : $raw;
                if ($candidate !== '') {
                    $description = $candidate;
                }
            }
            $description = Str::limit(strip_tags($description), 200, '');

            // Image chain: product cover (absolute URL) -> default share image
            $defaultImage = $this->toAbsoluteUrl(trim((string) SiteSetting::get('share_default_image', '')));
            $image = $defaultImage;
            if ($isProduct && !empty($product->cover_image)) {
                $cover = $this->toAbsoluteUrl($product->cover_image);
                if ($cover !== null) {
                    $image = $cover;
                }
            }

            // Title: product page = product name; other pages resolved in layout from @yield('title')
            $title = $isProduct ? $product->name : null;

            $view->with('shareEnabled', $shareEnabled);
            $view->with('shareIsProduct', $isProduct);
            $view->with('shareTitle', $title);
            $view->with('shareDescription', $description);
            $view->with('shareImage', $image);
            $view->with('shareUrl', request()->fullUrl());
            $view->with('shareType', $isProduct ? 'article' : 'website');

            // JSON-LD structured data (always injected)
            if ($isProduct) {
                $jsonLd = [
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => $product->name,
                    'description' => $description,
                ];
                if ($image !== null) {
                    $jsonLd['image'] = $image;
                }
            } else {
                $jsonLd = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $siteName,
                    'description' => $description,
                    'url' => request()->fullUrl(),
                ];
            }
            $view->with('shareJsonLd', $jsonLd);
        } catch (\Throwable $e) {
            // Share meta is non-critical; provide safe fallbacks.
            $view->with('shareEnabled', false);
            $view->with('shareIsProduct', false);
            $view->with('shareTitle', null);
            $view->with('shareDescription', '');
            $view->with('shareImage', null);
            $view->with('shareUrl', request()->fullUrl());
            $view->with('shareType', 'website');
            $view->with('shareJsonLd', null);
        }
    }

    /**
     * Convert a stored path (or full URL) to an absolute URL.
     */
    protected function toAbsoluteUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }
}
