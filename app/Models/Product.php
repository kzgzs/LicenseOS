<?php

namespace App\Models;

use App\Services\MachineTranslateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    /**
     * All supported product types — single source of truth for the entire site.
     */
    public const TYPES = [
        'wp_plugin'        => 'WordPress Plugin',
        'wp_theme'         => 'WordPress Theme',
        'android_app'      => 'Android App',
        'ios_app'          => 'iOS App',
        'desktop_app'      => 'Desktop App',
        'web_app'          => 'Web App',
        'chrome_extension' => 'Chrome Extension',
        'shopify_app'      => 'Shopify App',
        'figma_plugin'     => 'Figma Plugin',
        'laravel_package'  => 'Laravel Package',
        'source_code'      => 'Source Code',
        'script'           => 'Script',
        'api_service'      => 'API Service',
        'template'         => 'Template',
        'ebook'            => 'eBook',
        'course'           => 'Online Course',
        'other'            => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'type',
        'price', 'pricing_tiers', 'version', 'status', 'description',
        'downloads', 'rating', 'tags', 'category_id',
        'screenshots', 'short_description', 'installation', 'faq', 'readme_raw', 'readme_sections', 'featured', 'reviews_enabled', 'cover_image',
        'banner_url', 'banner_url_2x', 'icon_url', 'icon_url_2x',
        // WP metadata fields
        'author', 'author_profile', 'plugin_homepage', 'requires_wp', 'tested_up_to', 'requires_php',
        'license', 'license_uri', 'text_domain', 'wp_tags',
        'meta_title', 'meta_description', 'keywords', 'views',
        // V4: Global enterprise fields
        'supported_locales', 'download_url', 'cdn_node', 'file_size_bytes',
        // V5: AI fields
        'ai_generated', 'ai_product_id', 'ai_price_score', 'ai_seo_score', 'last_ai_optimized_at',
        // V6: Ecosystem fields
        'autonomous', 'evolution_generation', 'evolved_from_id', 'ecosystem_score',
    ];

    protected $casts = [
        'tags' => 'array',
        'screenshots' => 'array',
        'featured' => 'boolean',
        'reviews_enabled' => 'boolean',
        'rating' => 'decimal:2',
        'keywords' => 'array',
        'views' => 'integer',
        'supported_locales' => 'array',
        'file_size_bytes' => 'integer',
        'ai_generated' => 'boolean',
        'autonomous' => 'boolean',
        'ecosystem_score' => 'decimal:2',
        'pricing_tiers' => 'array',
        'readme_sections' => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function licenses() { return $this->hasMany(License::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function versions() { return $this->hasMany(UpdatePackage::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function views() { return $this->hasMany(ProductView::class); }
    public function coupons() { return $this->hasMany(Coupon::class); }
    public function ads() { return $this->hasMany(Ad::class); }

    public function scopePublished($query) { return $query->where('status', 'published'); }
    public function scopeFeatured($query) { return $query->where('featured', true); }
    public function scopeInCategory($query, $categoryId) { return $query->where('category_id', $categoryId); }
    public function scopeMostViewed($query) { return $query->orderByDesc('views'); }

    public function incrementViews(int $count = 1): void
    {
        $this->increment('views', $count);
    }

    public function latestVersion(): ?UpdatePackage
    {
        return $this->versions()->latest()->first();
    }
    
    /**
     * Boot method to handle model events.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        // When force deleting, clean up all related data and storage files
        static::deleting(function (Product $product) {
            // Only run full cleanup on force delete
            if (method_exists($product, 'isForceDeleting') && !$product->isForceDeleting()) {
                return;
            }
            
            $productId = $product->id;

            // Collect all version ZIP file URLs BEFORE deleting records
            $versionFiles = UpdatePackage::where('product_id', $productId)
                ->pluck('file_url')
                ->filter()
                ->merge(UpdatePackage::where('product_id', $productId)->pluck('original_file_url')->filter())
                ->unique()
                ->values();

            // Delete all associated records permanently
            UpdatePackage::where('product_id', $productId)->forceDelete();
            License::where('product_id', $productId)->forceDelete();
            Review::where('product_id', $productId)->forceDelete();
            Subscription::where('product_id', $productId)->forceDelete();
            Coupon::where('product_id', $productId)->forceDelete();
            Ad::where('product_id', $productId)->forceDelete();
            ProductView::where('product_id', $productId)->forceDelete();
            
            // Note: RevenueRecord uses polymorphic association (recordable_type + recordable_id)
            // Don't delete to preserve revenue history - let's keep it for audit purposes
            // Uncomment if you want to clean up:
            // RevenueRecord::where('recordable_type', Product::class)
            //     ->where('recordable_id', $productId)
            //     ->delete();
            
            // Clean up storage files using the storage service
            try {
                $storageService = app(\App\Services\ProductStorageService::class);

                // Delete all version files (URLs collected before record deletion)
                foreach ($versionFiles as $fileUrl) {
                    $storageService->delete($fileUrl);
                }

                // Delete product image (cover_image field)
                if (!empty($product->cover_image)) {
                    $storageService->delete($product->cover_image);
                }

                // Fallback: delete the whole product directory
                $productDir = 'products/' . $product->tenant_id . '_' . $product->slug;
                \Storage::disk('public')->deleteDirectory($productDir);

                // Also clean cloud disks if configured
                foreach (['r2', 'aliyun-oss', 'tencent-cos', 'qiniu'] as $disk) {
                    try {
                        $cloudDisk = \Storage::disk($disk);
                        $hasDir = method_exists($cloudDisk, 'directoryExists')
                            ? $cloudDisk->directoryExists($productDir)
                            : $cloudDisk->exists($productDir);
                        if ($hasDir) {
                            $cloudDisk->deleteDirectory($productDir);
                        }
                    } catch (\Throwable $diskError) {
                        \Log::warning('Failed to clean disk for product: ' . $productId, [
                            'disk' => $disk,
                            'error' => $diskError->getMessage(),
                        ]);
                    }
                }

                // Delete screenshot directory
                $screenshotDir = 'screenshots/' . $product->slug;
                \Storage::disk('public')->deleteDirectory($screenshotDir);
            } catch (\Exception $e) {
                // Log but don't fail the deletion if storage cleanup fails
                \Log::warning('Failed to clean storage for product: ' . $productId, ['error' => $e->getMessage()]);
            }
        });
    }

    // V4: Global enterprise
    public function translations() { return $this->hasMany(ProductTranslation::class); }

    // === Pricing Tiers ===
    public const TIER_TYPES = [
        'free'     => 'Free',
        'trial'    => 'Trial (days)',
        '1_month'  => '1 Month',
        '3_months' => '3 Months',
        '6_months' => '6 Months',
        '1_year'   => '1 Year',
        'lifetime' => 'Lifetime',
    ];

    /**
     * Get enabled pricing tiers sorted by duration (short first).
     * Falls back to single price if no tiers configured.
     */
    public function getEnabledTiers(): array
    {
        $tiers = $this->pricing_tiers ?? [];
        if (empty($tiers)) {
            // Fallback: use legacy price field
            return [[
                'type' => '1_year',
                'enabled' => true,
                'days' => 365,
                'months' => 12,
                'price' => (float) $this->price,
                'label' => __('年付'),
                'description' => '',
                'auto_renew' => false,
            ]];
        }

        // 获取所有已启用的层级（含 trial），按天数排序
        $enabledTiers = collect($tiers)->where('enabled', true)->sortBy('days')->values()->toArray();

        if (!empty($enabledTiers)) {
            return $enabledTiers;
        }

        // 没有任何启用的层级，回退到 legacy price
        return [[
            'type' => '1_year',
            'enabled' => true,
            'days' => 365,
            'months' => 12,
            'price' => (float) $this->price,
            'label' => __('年付'),
            'description' => '',
            'auto_renew' => false,
        ]];
    }

    /**
     * Find a specific tier by type key.
     */
    public function findTier(string $type): ?array
    {
        $tiers = $this->pricing_tiers ?? [];
        foreach ($tiers as $tier) {
            if (($tier['type'] ?? '') === $type && ($tier['enabled'] ?? false)) {
                return $tier;
            }
        }
        return null;
    }

    public function hasTrial(): bool
    {
        $trial = $this->findTier('trial');
        return $trial !== null && (float)$trial['price'] >= 0;
    }

    /**
     * Check if product has an enabled free tier (no license / payment required).
     */
    public function isFreeProduct(): bool
    {
        return $this->findTier('free') !== null;
    }

    /**
     * Get product name for a specific locale.
     */
    public function getLocalizedName(string $locale): string
    {
        $translation = $this->translations()->where('locale', $locale)->first();
        return $translation?->name ?? $this->name;
    }

    /**
     * Check if a user has purchased and downloaded this product.
     */
    public function hasPurchasedAndDownload($userId): bool
    {
        return $this->orders()->where('user_id', $userId)->where('status', 'paid')->exists();
    }

    /**
     * Get translation for a specific locale.
     */
    public function getTranslation(string $locale): ?ProductTranslation
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    /**
     * Get the description in the current locale.
     * Delegates to getLocalizedDescriptionFor() with the app locale.
     */
    public function getLocalizedDescription(): string
    {
        return $this->getLocalizedDescriptionFor(app()->getLocale());
    }

    /**
     * Get the short description in the current locale.
     */
    public function getLocalizedShortDescription(): ?string
    {
        return $this->getLocalizedShortDescriptionFor(app()->getLocale());
    }

    /**
     * Get the description in a specific locale.
     * Lazily machine-translates and caches into product_translations.
     * Always falls back to the original text when translation is unavailable.
     */
    public function getLocalizedDescriptionFor(string $locale, bool $allowGenerate = true): string
    {
        return (string) ($this->resolveLocalizedFieldFor('description', $locale, $allowGenerate) ?? '');
    }

    /**
     * Get the short description in a specific locale.
     */
    public function getLocalizedShortDescriptionFor(string $locale, bool $allowGenerate = true): ?string
    {
        return $this->resolveLocalizedFieldFor('short_description', $locale, $allowGenerate);
    }

    /**
     * Get the localized readme sections (description / installation / faq / changelog)
     * for a specific locale. Lazily machine-translates and caches into
     * product_translations.readme_sections.
     *
     * @return array<string,string>|null null when no translation is available
     */
    public function getLocalizedReadmeSectionsFor(string $locale, bool $allowGenerate = true): ?array
    {
        try {
            $sections = is_array($this->readme_sections) ? $this->readme_sections : [];
            if (empty($sections)) {
                return null;
            }

            $localeLang = str_starts_with($locale, 'zh') ? 'zh' : 'en';

            $sample = trim((string) $this->description) !== ''
                ? (string) $this->description
                : implode("\n", array_map('strval', $sections));
            $sourceLang = MachineTranslateService::detectLanguage($sample);

            // Original text already matches the requested locale → nothing to translate
            if ($sourceLang === $localeLang) {
                return null;
            }

            // Existing COMPLETE translation record wins: readme_sections must
            // cover every expected key (description/installation/faq/changelog) whose
            // source text is non-empty. Legacy/null/partial rows are treated
            // as untranslated and re-translated below (self-healing).
            $translation = $this->getTranslation($locale);
            if ($this->hasCompleteTranslationSections($translation)) {
                return $translation->readme_sections;
            }

            if (!$allowGenerate) {
                // Query-only mode: no complete translation stored yet → report "not ready"
                return null;
            }

            // Lazy machine translation (only when the service is configured)
            $translated = $this->generateMachineTranslation($locale, $sourceLang, $localeLang);
            if ($translated !== null && !empty($translated['readme_sections']) && is_array($translated['readme_sections'])) {
                return $translated['readme_sections'];
            }
        } catch (\Throwable $e) {
            // Any failure (translation API, DB write, etc.) must fall back gracefully
            \Log::warning('Localized readme sections fallback: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Expected readme section keys the full-package translation covers,
     * mapped to their source-of-truth fields.
     */
    protected const README_SECTION_KEYS = ['description', 'installation', 'faq', 'changelog'];

    /**
     * Get the source text for a readme section key — mirrors exactly what
     * generateMachineTranslation() uses, so completeness never requires a
     * section that the full-package translation would skip.
     */
    protected function getReadmeSectionSource(string $key): string
    {
        $sections = is_array($this->readme_sections) ? $this->readme_sections : [];

        // description: readme_sections.description is the source of truth,
        // falling back to the description column (same as translation logic)
        if ($key === 'description') {
            $source = trim((string) ($sections['description'] ?? ''));
            return $source !== '' ? $source : trim((string) $this->description);
        }

        // installation / faq / changelog: readme_sections only (no column fallback)
        return trim((string) ($sections[$key] ?? ''));
    }

    /**
     * Whether a stored translation record has COMPLETE readme_sections.
     *
     * Every expected key whose product source text is non-empty must be
     * present with a non-empty translated value. Keys whose source text is
     * empty are not required. null / empty readme_sections (legacy rows)
     * are treated as incomplete as well.
     */
    protected function hasCompleteTranslationSections(?ProductTranslation $translation): bool
    {
        $translatedSections = $translation->readme_sections ?? null;
        if (!is_array($translatedSections) || empty($translatedSections)) {
            return false;
        }

        foreach (self::README_SECTION_KEYS as $key) {
            // Source text empty → this section was never translated, not required
            if (trim($this->getReadmeSectionSource($key)) === '') {
                continue;
            }

            if (trim((string) ($translatedSections[$key] ?? '')) === '') {
                // Missing key → incomplete (legacy partial) translation
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a localized field value for a given locale.
     */
    protected function resolveLocalizedFieldFor(string $field, string $locale, bool $allowGenerate = true): ?string
    {
        $original = $this->{$field};

        if ($original === null || trim((string) $original) === '') {
            return $original;
        }

        try {
            $localeLang = str_starts_with($locale, 'zh') ? 'zh' : 'en';

            // Detect language from the main readme description, fall back to the field itself
            $sample = trim((string) $this->description) !== '' ? (string) $this->description : (string) $original;
            $sourceLang = MachineTranslateService::detectLanguage($sample);

            // Original text already matches the current locale → no translation needed
            if ($sourceLang === $localeLang) {
                return (string) $original;
            }

            // Existing COMPLETE translation record wins.
            // A record counts as valid only when readme_sections covers every
            // expected key (description/installation/faq/changelog) whose source text is
            // non-empty; legacy/incomplete rows are treated as untranslated
            // and re-translated below (self-healing of partial rows).
            $translation = $this->getTranslation($locale);
            if ($this->hasCompleteTranslationSections($translation) && !empty($translation->{$field})) {
                return (string) $translation->{$field};
            }

            if (!$allowGenerate) {
                // Query-only mode: no valid translation stored yet → fall back to the original text
                return (string) $original;
            }

            // Lazy machine translation (only when the service is configured)
            $translated = $this->generateMachineTranslation($locale, $sourceLang, $localeLang);
            if ($translated !== null && !empty($translated[$field])) {
                return $translated[$field];
            }
        } catch (\Throwable $e) {
            // Any failure (translation API, DB write, etc.) must fall back to the original text
            \Log::warning('Machine translation fallback: ' . $e->getMessage());
        }

        return (string) $original;
    }

    /**
     * Generate the full translation package for a locale in the background
     * (after the HTTP response has been sent, e.g. via register_shutdown_function).
     *
     * Never throws — every failure is logged and swallowed so background
     * execution can never break the caller.
     */
    public function generateTranslationInBackground(string $locale): void
    {
        try {
            // Remove the PHP time limit so the full-package translation can finish
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $localeLang = str_starts_with($locale, 'zh') ? 'zh' : 'en';

            $sections = is_array($this->readme_sections) ? $this->readme_sections : [];
            $sample = trim((string) $this->description) !== ''
                ? (string) $this->description
                : implode("\n", array_map('strval', $sections));

            // Nothing to translate
            if (trim($sample) === '') {
                return;
            }

            $sourceLang = MachineTranslateService::detectLanguage($sample);

            // Original text already matches the requested locale → nothing to do
            if ($sourceLang === $localeLang) {
                return;
            }

            // A complete translation already exists → skip re-translation.
            // readme_sections must cover every expected key whose source text
            // is non-empty; incomplete legacy rows fall through to re-translate.
            if ($this->hasCompleteTranslationSections($this->getTranslation($locale))) {
                return;
            }

            // Reuse the full-package translation logic (all-or-nothing persistence)
            $this->generateMachineTranslation($locale, $sourceLang, $localeLang);
        } catch (\Throwable $e) {
            // Background translation must never bubble an error up
            \Log::warning('Background translation failed: ' . $e->getMessage());
        }
    }

    /**
     * Translate the full localization package (name, short_description,
     * description and readme sections description/installation/faq/changelog) and
     * persist it atomically via updateOrCreate.
     *
     * All-or-nothing: if any required piece fails to translate, nothing is
     * persisted and null is returned, so incomplete translations never land
     * in the database.
     *
     * Returns null when the service is not configured or translation fails.
     */
    protected function generateMachineTranslation(string $locale, string $from, string $to): ?array
    {
        $service = app(MachineTranslateService::class);

        if (!$service->isConfigured()) {
            return null;
        }

        $readmeSections = is_array($this->readme_sections) ? $this->readme_sections : [];
        $translatedSections = [];

        // Description section: readme_sections.description is the source of truth,
        // fall back to the description column. The same translation feeds both the
        // description field and readme_sections.description (single source).
        $descriptionSource = trim((string) ($readmeSections['description'] ?? '')) !== ''
            ? (string) $readmeSections['description']
            : (string) $this->description;

        $description = $service->translate($descriptionSource, $from, $to);
        if ($description === null) {
            return null;
        }
        $translatedSections['description'] = $description;

        // installation / faq / changelog sections: skip when the source text is empty,
        // abort the whole translation when a non-empty section fails.
        foreach (['installation', 'faq', 'changelog'] as $sectionKey) {
            $source = trim((string) ($readmeSections[$sectionKey] ?? ''));
            if ($source === '') {
                continue;
            }

            $translated = $service->translate($source, $from, $to);
            if ($translated === null) {
                return null;
            }
            $translatedSections[$sectionKey] = $translated;
        }

        $shortDescription = null;
        if (trim((string) $this->short_description) !== '') {
            $shortDescription = $service->translate((string) $this->short_description, $from, $to);
        }

        // name is NOT NULL in product_translations: translate it, keep original on failure
        $name = $service->translate((string) $this->name, $from, $to);
        if ($name === null || trim($name) === '') {
            $name = (string) $this->name;
        }

        ProductTranslation::updateOrCreate(
            ['product_id' => $this->id, 'locale' => $locale],
            [
                'name' => $name,
                'slug' => (string) $this->slug,
                'description' => $description,
                'short_description' => $shortDescription,
                'readme_sections' => $translatedSections,
            ]
        );

        return [
            'description' => $description,
            'short_description' => $shortDescription,
            'readme_sections' => $translatedSections,
        ];
    }
}
