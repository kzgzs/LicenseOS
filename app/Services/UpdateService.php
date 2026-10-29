<?php

namespace App\Services;

use App\Models\Product;
use App\Models\UpdatePackage;
use App\Models\License;
use App\Models\SiteSetting;
use App\Services\ProductStorageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class UpdateService
{
    /**
     * Check for the latest update for a product.
     *
     * @param string $productSlug    Product slug
     * @param string|null $currentVersion  Customer's current version
     * @param string|null $licenseKey  Optional license key for download auth
     */
    public function checkForUpdate(string $productSlug, ?string $currentVersion = null, ?string $licenseKey = null): ?array
    {
        $product = Product::where('slug', $productSlug)->where('status', 'published')->first();

        if (!$product) {
            return null;
        }

        $latest = $product->versions()->latest()->first();

        // Build full product URL
        $productUrl = url('/products/' . $product->slug);

        // Locale requested by the WP SDK — only en / zh_CN are supported
        $locale = (string) request()->input('locale', '');
        if (!in_array($locale, ['en', 'zh_CN'], true)) {
            $locale = 'en';
        }

        // Helper: resolve stored relative path to full URL
        $resolveUrl = function (?string $path): string {
            if (empty($path)) return '';
            // Already a full URL (R2 or external)
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . ltrim($path, '/'));
        };

        // Localized (translated) content for the requesting WP site locale.
        // Query-only (allowGenerate=false): only reads stored translations so the
        // API responds in milliseconds — never blocks on a full machine translation,
        // which would exceed the WP SDK request timeout.
        $localizedDescription = $product->getLocalizedDescriptionFor($locale, false);
        $localizedShortDescription = $product->getLocalizedShortDescriptionFor($locale, false);
        $localizedReadmeSections = $product->getLocalizedReadmeSectionsFor($locale, false);

        // Translation not ready yet → generate it in the background after the
        // response has been sent; the next SDK request will find it in the DB.
        try {
            if ($locale !== 'en' && empty($localizedReadmeSections)) {
                register_shutdown_function(function () use ($product, $locale) {
                    try {
                        $product->generateTranslationInBackground($locale);
                    } catch (\Throwable $e) {
                        Log::warning('[checkForUpdate] background translation failed: ' . $e->getMessage());
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[checkForUpdate] failed to register background translation: ' . $e->getMessage());
        }

        // Build base response (always include product info for plugin details popup)
        $response = [
            'product_name' => $product->name,
            'product_url' => $productUrl,
            'current_version' => $currentVersion ?? $product->version,
            'description' => $product->description ?? '',
            'short_description' => $product->short_description ?? ($product->description ? mb_substr($product->description, 0, 200) : ''),
            // Localized (translated) content for the requesting WP site locale
            'localized_description' => $localizedDescription,
            'localized_short_description' => $localizedShortDescription,
            'localized_readme_sections' => $localizedReadmeSections,
            'installation' => $product->installation ?? '',
            'faq' => $product->faq ?? '',
            'requires_wp' => $product->requires_wp ?? '5.0',
            'tested_up_to' => $product->tested_up_to ?? '',
            'requires_php' => $product->requires_php ?? '7.4',
            'author' => $product->author ?? '',
            'author_profile' => $product->author_profile ?? '',
            'banners' => [],
            'plugin_update_url' => url('/products/' . $product->slug),
            'support_url' => '',
            'icons' => [],
        ];

        // Load approved reviews for rating calculation and detail display
        // Respect global + per-product review switches
        $globalReviewsEnabled = SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true));
        $reviewsData = [];
        $avgRating = 0;

        if ($globalReviewsEnabled && $product->reviews_enabled) {
            $reviews = $product->reviews()->with('replies.user')->approved()->latest()->take(20)->get();
            $reviewsData = $reviews->map(function ($review) {
                return [
                    'author'  => $review->user?->name ?? 'Anonymous',
                    'rating'  => (int)($review->rating ?? 0),
                    'content' => $review->comment ?? '',
                    'date'    => $review->created_at?->format('Y-m-d') ?? '',
                    'replies' => $review->replies->map(function ($reply) {
                        return [
                            'author'  => $reply->user?->name ?? 'Developer',
                            'content' => $reply->content ?? '',
                            'date'    => $reply->created_at?->format('Y-m-d') ?? '',
                        ];
                    })->toArray(),
                ];
            })->toArray();

            $avgRating = $reviews->avg('rating') ?? 0;

            Log::info('[checkForUpdate] reviews with replies', [
                'product' => $productSlug,
                'reviews_count' => count($reviewsData),
                'reviews_with_replies' => collect($reviewsData)->filter(fn($r) => !empty($r['replies']))->count(),
                'sample' => $reviewsData[0] ?? null,
            ]);
        }

        // Build detail fields for plugin details popup
        $detailFields = [
            'screenshots' => is_array($product->screenshots) ? $product->screenshots : [],
            'rating' => (int)round($avgRating * 20),  // 1-5 stars -> WP standard 0-100
            'average_rating' => round($avgRating, 2),
            'num_ratings' => count($reviewsData),
            'review_count' => count($reviewsData),
            'reviews' => $reviewsData,
            'active_installs' => (int)($product->active_installs ?? 0),
            'downloaded' => (int)($product->downloads ?? 0),
            'last_updated' => $latest?->created_at?->format('Y-m-d') ?? $product->updated_at?->format('Y-m-d') ?? '',
            'added' => $product->created_at?->format('Y-m-d') ?? '',
        ];

        // If no versions uploaded yet
        if (!$latest) {
            return array_merge($response, $detailFields, ['up_to_date' => true]);
        }

        $needsUpdate = $currentVersion
            ? version_compare($latest->version, $currentVersion, '>')
            : true;

        // Build download URL (with license key for auth if provided)
        $downloadUrl = null;
        if ($latest->file_url) {
            if ($licenseKey) {
                $downloadUrl = url('/api/v2/download')
                    . '?' . http_build_query([
                        'product' => $productSlug,
                        'version' => $latest->version,
                        'license_key' => $licenseKey,
                    ]);
            } elseif ($product->isFreeProduct()) {
                // Free product — route through API endpoint to track downloads, no license_key needed
                $downloadUrl = url('/api/v2/download')
                    . '?' . http_build_query([
                        'product' => $productSlug,
                        'version' => $latest->version,
                    ]);
            } else {
                // Paid product without license — direct storage URL (existing fallback)
                $downloadUrl = url($latest->file_url);
            }
        }

        if (!$needsUpdate) {
            return array_merge($response, $detailFields, [
                'up_to_date' => true,
                'latest_version' => $currentVersion,
                'changelog' => $latest->changelog ?? '',
            ]);
        }

        return array_merge($response, $detailFields, [
            'up_to_date' => false,
            'latest_version' => $latest->version,
            'download_url' => $downloadUrl,
            'changelog' => $latest->changelog ?? '',
        ]);
    }

    /**
     * Validate license key and return the download file path if authorized.
     * Free products bypass license validation entirely.
     */
    public function authorizeDownload(string $productSlug, string $version, ?string $licenseKey = null): ?string
    {
        $product = Product::where('slug', $productSlug)->where('status', 'published')->first();
        if (!$product) return null;

        // Free product fast-path — no license required
        if ($product->isFreeProduct()) {
            $package = UpdatePackage::where('product_id', $product->id)
                ->where('version', $version)
                ->first();

            if (!$package || !$package->file_url) return null;

            // Track download count
            $product->increment('downloads');

            $storageService = app(ProductStorageService::class);
            return $storageService->resolveDownload($package->file_url);
        }

        // Paid product — license key is required
        if (!$licenseKey) return null;

        // Verify license key is valid and active for this product
        $license = License::where('license_key', $licenseKey)
            ->where('status', 'active')
            ->first();

        if (!$license) {
            return null;
        }

        // Verify license belongs to the correct product
        if ($license->product_id !== $product->id) {
            return null;
        }

        // Find the specific version
        $package = UpdatePackage::where('product_id', $product->id)
            ->where('version', $version)
            ->first();

        if (!$package || !$package->file_url) {
            return null;
        }

        // Return the full file system path or signed R2 URL
        $storageService = app(ProductStorageService::class);
        return $storageService->resolveDownload($package->file_url);
    }

    /**
     * Create a new update package for a product.
     */
    public function createPackage(Product $product, string $version, string $fileUrl, ?string $changelog = null): UpdatePackage
    {
        $package = UpdatePackage::create([
            'product_id' => $product->id,
            'version' => $version,
            'file_url' => $fileUrl,
            'changelog' => $changelog,
        ]);

        // Update the product's current version
        $product->update(['version' => $version]);

        return $package;
    }
}
