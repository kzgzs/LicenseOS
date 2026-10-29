<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\RevenueRecord;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UpdatePackage;
use App\Models\View;
use App\Services\LicenseProtectionInjector;
use App\Services\ProductStorageService;
use App\Services\WpPluginParserService;
use App\Services\ZipSecurityScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    protected ?string $tempInjectPath = null; // Temporary injection file path for cleanup

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            if ($request->user()->isSuperAdmin()) {
                $products = Product::withCount('licenses', 'orders')->latest()->paginate(20);
                return view('developer.products.index', compact('products'));
            }
            return back()->with('error', 'No tenant assigned.');
        }
        $products = $tenant->products()->withCount('licenses', 'orders')->latest()->paginate(20);
        return view('developer.products.index', compact('products'));
    }

    public function create()
    {
        $types = Product::TYPES;
        $currencySymbol = SiteSetting::get('currency_symbol', '¥');
        $categories = Category::orderBy('sort_order')->get();
        return view('developer.products.create', compact('types', 'currencySymbol', 'categories'));
    }

    public function store(Request $request)
    {
        // DEBUG: Log raw upload data
        \Log::info('Product creation request', [
            'has_file' => $request->hasFile('file'),
            'files_array' => print_r($_FILES, true),
            'post_keys' => array_keys($request->all())
        ]);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'type' => 'required|in:' . implode(',', array_keys(Product::TYPES)),
            'category_id' => 'nullable|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'version' => 'required|string|max:20',
            'file' => 'nullable|file|mimes:zip|max:102400',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            // WP metadata fields (from ZIP auto-parse)
            'author' => 'nullable|string|max:255',
            'author_profile' => 'nullable|url|max:500',
            'plugin_homepage' => 'nullable|url|max:500',
            'requires_wp' => 'nullable|string|max:10',
            'tested_up_to' => 'nullable|string|max:10',
            'requires_php' => 'nullable|string|max:10',
            'license' => 'nullable|string|max:100',
            'license_uri' => 'nullable|url|max:255',
            'text_domain' => 'nullable|string|max:100',
            'wp_tags' => 'nullable|string',
            'short_description' => 'nullable|string',
            'installation' => 'nullable|string',
            'faq' => 'nullable|string',
            'pricing_tiers' => 'nullable|array',
            'pricing_tiers.*.type' => 'nullable|string|in:free,trial,1_month,3_months,6_months,1_year,lifetime',
            'pricing_tiers.*.price' => 'nullable|numeric|min:0',
            'pricing_tiers.*.label' => 'nullable|string|max:100',
            'featured' => 'nullable|boolean',
            'reviews_enabled' => 'nullable|boolean',
            // SEO fields
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'keywords' => 'nullable|string|max:500',
        ]);

        $tenant = $request->user()->tenant;

        if (!$tenant) {
            return redirect()->route('developer.products.create')
                ->with('error', __('请先完善开发者账户信息'));
        }

        $fileUrl = null;
        $fileSize = 0;

        // Parse ZIP metadata before creating product
        $metadata = [];
        if ($request->hasFile('file')) {
            // SECURITY CHECK: Validate ZIP file before any processing
            $scanner = app(ZipSecurityScannerService::class);
            $scanResult = $scanner->scan($request->file('file'));
            
            if (!$scanResult['safe']) {
                return back()->withErrors([
                    'file' => 'ZIP 文件检测到安全问题，请修复后重新上传：' . implode('; ', $scanResult['issues'])
                ])->withInput();
            }
            
            // If has warnings, log them but continue
            if (!empty($scanResult['warnings'])) {
                Log::warning('ZIP 文件存在警告信息', [
                    'product_slug' => $validated['slug'],
                    'warnings' => $scanResult['warnings']
                ]);
            }
            
            // Then parse metadata
            $type = $validated['type'] ?? 'wp_plugin';
            if (in_array($type, ['wp_plugin', 'wp_theme'])) {
                try {
                    $parser = app(WpPluginParserService::class);
                    $metadata = $parser->parse($request->file('file'));

                    // readme 解析的 installation/faq 补充（用户未填写时使用解析值）
                    foreach (['installation', 'faq'] as $field) {
                        if (!empty($metadata[$field]) && empty($validated[$field])) {
                            $validated = array_merge($validated, [$field => $metadata[$field]]);
                        }
                    }

                    // readme_sections 和 readme_raw 存入数据库
                    if (!empty($metadata['readme_sections'])) {
                        $validated['readme_sections'] = json_encode($metadata['readme_sections']);
                        // 产品 description 使用 readme == Description == 段落内容（更完整）
                        if (!empty($metadata['readme_sections']['description'])) {
                            $validated['description'] = $metadata['readme_sections']['description'];
                        }
                    }
                    if (!empty($metadata['readme_raw'])) {
                        $validated['readme_raw'] = $metadata['readme_raw'];
                    }
                    // 同步 readme 解析的 tags（确保为数组格式）
                    if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
                        $validated['tags'] = $metadata['tags'];
                    }
                } catch (\Exception $e) {
                    // ZIP 解析失败不阻止创建，记录日志
                    Log::warning('ZIP 解析失败', ['error' => $e->getMessage()]);
                }
            }
        }

        // Merge parsed metadata into product data (user input takes priority)
        $productData = array_merge($metadata, $validated);

        unset($productData['file'], $productData['pricing_tiers'], $productData['cover_image'], $productData['featured'], $productData['reviews_enabled']);

        // Upload cover image if provided
        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            $coverImagePath = $request->file('cover_image')->store('covers', 'public');
        }

        // Build pricing_tiers JSON from form input
        $pricingTiers = $this->buildPricingTiers($request->input('pricing_tiers', []));

        // 如果只有 trial 层级，产品强制免费
        if ($this->hasOnlyTrialTier($pricingTiers)) {
            $productData['price'] = 0;
        }

        // Convert keywords string to array
        if (!empty($productData['keywords'])) {
            $productData['keywords'] = array_map('trim', explode(',', $productData['keywords']));
        }

        $product = $tenant->products()->create(array_merge($productData, [
            'download_url' => null, // set after storage
            'file_size_bytes' => 0,
            'status' => 'published',
            'pricing_tiers' => $pricingTiers ?: null,
            'cover_image' => $coverImagePath,
            'featured' => $validated['featured'] ?? false,
            'reviews_enabled' => $validated['reviews_enabled'] ?? true,
        ]));

        // Upload ZIP file if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            try {
                // Use parsed version from metadata, fallback to '1.0.0'
                $version = !empty($metadata['version']) ? $metadata['version'] : '1.0.0';
                $storageService = app(ProductStorageService::class);
                $result = $storageService->store($file, $product->slug, $version, $tenant->id);
        
                $fileUrl = $result['url'];
                $fileSize = $result['size'];
        
                UpdatePackage::create([
                    'product_id' => $product->id,
                    'version' => $version,
                    'file_url' => $fileUrl,
                    'original_file_url' => $originalFileUrl ?? $fileUrl,
                    'has_original' => !empty($originalFileUrl),
                    'injection_modules' => [], // Do not auto-inject during product creation
                    'changelog' => 'Initial release.',
                ]);
                
                // Clean up temp injection file
                if (isset($this->tempInjectPath) && file_exists($this->tempInjectPath)) {
                    @unlink($this->tempInjectPath);
                    $this->tempInjectPath = null;
                }
            } catch (\Exception $e) {
                Log::error('产品创建上传失败', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
                return back()->withErrors(['file' => '上传失败：' . $e->getMessage()]);
            }
        }

        return redirect()->route('developer.products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }
    
        // Safely get tenant and API key
        $tenant = auth()->user()->tenant ?? Tenant::find($product->tenant_id);
        if (!$tenant) {
            return back()->with('error', __('developer.tenant_not_found'));
        }
            
        $apiKey = $tenant->apiKeys()->where('active', true)->first()?->key ?? '';
        $currencySymbol = \App\Models\SiteSetting::get('currency_symbol', '¥');
    
        $licenses = $product->licenses()->latest()->paginate(10);
        $versions = $product->versions()->latest()->get();
        $categories = \App\Models\Category::orderBy('name')->get();
        $latestVersion = UpdatePackage::where('product_id', $product->id)->max('version');
        $latestPackage = UpdatePackage::where('product_id', $product->id)->latest('id')->first();
    
        return view('developer.products.show', compact(
            'product',
            'licenses',
            'versions',
            'categories',
            'latestVersion',
            'latestPackage',
            'currencySymbol'
        ));
    }

    /**
     * Scan uploaded ZIP file for security issues
     */
    public function scanZip(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:zip|max:52428800', // 50MB
            ]);
            
            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => '未找到上传的文件',
                    'issues' => [],
                    'warnings' => []
                ], 422);
            }
            
            $scanner = app(ZipSecurityScannerService::class);
            $scanResult = $scanner->scan($request->file('file'));
            
            if (!$scanResult['safe']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ZIP 文件检测到安全问题，请修复后重新上传',
                    'issues' => $scanResult['issues'],
                    'warnings' => $scanResult['warnings'],
                ], 422);
            }
            
            return response()->json([
                'safe' => true,
                'status' => 'success',
                'message' => '文件扫描通过',
                'issues' => [],
                'warnings' => $scanResult['warnings']
            ]);
            
        } catch (\Exception $e) {
            Log::error('ZIP 扫描失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => '扫描服务异常：' . $e->getMessage(),
                'issues' => [],
                'warnings' => []
            ], 500);
        }
    }

    /**
     * Update basic product info.
     */
    public function update(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'slug'              => 'required|string|max:255|unique:products,slug,' . $product->id,
            'price'             => 'required|numeric|min:0',
            'status'            => 'required|in:draft,published,archived',
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'installation'      => 'nullable|string',
            'faq'               => 'nullable|string',
            'category_id'       => 'nullable|exists:categories,id',
            'cover_image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'author'            => 'nullable|string|max:255',
            'author_profile'    => 'nullable|url|max:500',
            'plugin_homepage'   => 'nullable|url|max:500',
            'requires_wp'       => 'nullable|string|max:10',
            'tested_up_to'      => 'nullable|string|max:10',
            'requires_php'      => 'nullable|string|max:10',
            'license'           => 'nullable|string|max:100',
            'license_uri'       => 'nullable|url|max:255',
            'text_domain'       => 'nullable|string|max:100',
            'wp_tags'           => 'nullable|string',
            'featured'          => 'nullable|boolean',
            'reviews_enabled'   => 'nullable|boolean',
            // SEO fields
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'keywords'          => 'nullable|string|max:500',
            'file'              => 'nullable|file|mimes:zip|max:102400',
        ]);

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            if ($product->cover_image) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        } else {
            unset($validated['cover_image']);
        }

        // Handle featured checkbox (unchecked = false)
        $validated['featured'] = $request->boolean('featured');

        // Handle reviews_enabled checkbox (unchecked = false)
        $validated['reviews_enabled'] = $request->boolean('reviews_enabled');

        // Convert keywords string to array
        if (!empty($validated['keywords'])) {
            $validated['keywords'] = array_map('trim', explode(',', $validated['keywords']));
        } else {
            unset($validated['keywords']);
        }

        // Handle screenshots
        if ($request->has('screenshots')) {
            $screenshotsJson = $request->input('screenshots');
            $screenshots = json_decode($screenshotsJson, true);
            if (is_array($screenshots)) {
                $screenshots = array_slice($screenshots, 0, 8);
                $screenshots = array_filter($screenshots, function($s) {
                    return !empty($s['url']);
                });
                $validated['screenshots'] = array_values($screenshots);
            }
        }

        unset($validated['cover_image_file']); // cleanup non-DB field if exists
        unset($validated['file']); // remove ZIP file from product update data

        // 如果有上传 ZIP 文件，解析 readme 并补充 installation/faq/readme_raw
        if ($request->hasFile('file') && in_array($product->type, ['wp_plugin', 'wp_theme'])) {
            try {
                $parser = app(WpPluginParserService::class);
                $metadata = $parser->parse($request->file('file'));
                foreach (['installation', 'faq'] as $field) {
                    if (empty($validated[$field]) && !empty($metadata[$field])) {
                        $validated[$field] = $metadata[$field];
                    }
                }
                // 始终更新 readme_sections 和 readme_raw（保留最新版本的完整 readme）
                if (!empty($metadata['readme_sections'])) {
                    $validated['readme_sections'] = json_encode($metadata['readme_sections']);
                    if (!empty($metadata['readme_sections']['description'])) {
                        $validated['description'] = $metadata['readme_sections']['description'];
                    }
                }
                if (!empty($metadata['readme_raw'])) {
                    $validated['readme_raw'] = $metadata['readme_raw'];
                }
                // 同步 readme 解析的 tags（确保为数组格式）
                if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
                    $validated['tags'] = $metadata['tags'];
                }
            } catch (\Exception $e) {
                Log::warning('ZIP 解析失败', ['error' => $e->getMessage()]);
            }
        }

        // trial-only 产品强制免费：如果当前 pricing_tiers 只有 trial，忽略用户输入的 price
        $currentTiers = $product->pricing_tiers ?? [];
        if ($this->hasOnlyTrialTier($currentTiers)) {
            $validated['price'] = 0;
        }

        $product->update($validated);

        return back()->with('success', 'Product updated successfully.');
    }

    /**
     * Upload a new version for an existing product.
     */
    public function uploadVersion(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'version' => 'required|string|max:20',
            'file' => 'required|file|mimes:zip|max:102400',
            'changelog' => 'nullable|string|max:2000',
        ]);

        // PRIORITY: Security scan before any processing
        try {
            $scanner = app(ZipSecurityScannerService::class);
            $scanResult = $scanner->scan($request->file('file'));
            
            if (!$scanResult['safe']) {
                return back()->withErrors([
                    'file' => 'ZIP 文件检测到安全问题：' . implode('; ', array_slice($scanResult['issues'], 0, 3))
                ])->withInput();
            }
            
            // If has warnings, log them but continue
            if (!empty($scanResult['warnings'])) {
                Log::warning('版本上传存在警告信息', [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'warnings' => $scanResult['warnings']
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('版本上传安全扫描失败', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['file' => '安全扫描服务异常']);
        }

        $tenant = auth()->user()->tenant;
        $file = $request->file('file');
        $version = $validated['version'];

        // 版本号校验：新版本号必须高于当前最新版本号
        $latestVersion = UpdatePackage::where('product_id', $product->id)->max('version');
        if ($latestVersion && version_compare($version, $latestVersion, '<=')) {
            return back()->withErrors(['version' => "版本号必须高于当前版本 {$latestVersion}"])->withInput();
        }

        // Parse ZIP metadata for version info
        $metadata = [];
        if (in_array($product->type, ['wp_plugin', 'wp_theme'])) {
            try {
                $parser = app(WpPluginParserService::class);
                $metadata = $parser->parse($file);
            } catch (\Exception $e) {
                Log::warning('ZIP 解析失败', ['error' => $e->getMessage()]);
            }
        }

        // Use parsed version if available, fallback to user input
        $version = $validated['version'] ?? ($metadata['version'] ?? $version);

        // Store ZIP file without any injection (user will use one-click inject)
        $storageService = app(ProductStorageService::class);
        $result = $storageService->store($file, $product->slug, $version, $product->tenant_id);

        $fileUrl = $result['url'];

        // Clean up temp injection file if it exists
        if (isset($tempPath) && file_exists($tempPath)) {
            @unlink($tempPath);
        }

        // Create version record
        UpdatePackage::create([
            'product_id' => $product->id,
            'version' => $version,
            'file_url' => $fileUrl,
            'injection_modules' => null, // No injection during version upload
            'changelog' => $validated['changelog'] ?? null,
        ]);

        // Update product current version + download URL
        $productUpdate = [
            'version' => $version,
            'download_url' => $fileUrl,
            'file_size_bytes' => $result['size'],
        ];

        // readme 解析的 installation/faq 补充（产品字段为空时使用解析值）
        foreach (['installation', 'faq'] as $field) {
            if (empty($product->$field) && !empty($metadata[$field])) {
                $productUpdate[$field] = $metadata[$field];
            }
        }

        // WP 版本元数据自动识别：新版本 ZIP 中的 Tested up to / Requires WP / Requires PHP 同步更新到产品记录
        foreach (['requires_wp', 'tested_up_to', 'requires_php'] as $field) {
            if (!empty($metadata[$field])) {
                $productUpdate[$field] = $metadata[$field];
            }
        }

        // 始终更新 readme_sections 和 readme_raw（保留最新版本的完整 readme）
        if (!empty($metadata['readme_sections'])) {
            $productUpdate['readme_sections'] = $metadata['readme_sections'];
            if (!empty($metadata['readme_sections']['description'])) {
                $productUpdate['description'] = $metadata['readme_sections']['description'];
            }
        }
        if (!empty($metadata['readme_raw'])) {
            $productUpdate['readme_raw'] = $metadata['readme_raw'];
        }
        // 同步 readme 解析的 tags（确保为数组格式）
        if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
            $productUpdate['tags'] = $metadata['tags'];
        }

        $product->update($productUpdate);

        return back()->with('success', "Version {$version} uploaded successfully.");
    }

    /**
     * Update pricing tiers for a product.
     */
    public function updatePricing(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $request->validate([
            'pricing_tiers' => 'nullable|array',
            'pricing_tiers.*.type' => 'nullable|string|in:free,trial,1_month,3_months,6_months,1_year,lifetime',
            'pricing_tiers.*.price' => 'nullable|numeric|min:0',
            'pricing_tiers.*.label' => 'nullable|string|max:100',
        ]);

        $pricingTiers = $this->buildPricingTiers($request->input('pricing_tiers', []));

        $updateData = ['pricing_tiers' => $pricingTiers ?: null];
        // 如果只有 trial 层级，产品强制免费
        if ($this->hasOnlyTrialTier($pricingTiers)) {
            $updateData['price'] = 0;
        }
        $product->update($updateData);

        return back()->with('success', 'Pricing tiers updated successfully.');
    }

    /**
     * AJAX endpoint: parse ZIP and return WP plugin metadata.
     */
    public function parseZip(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:102400',
        ]);

        try {
            $parser = app(WpPluginParserService::class);
            $metadata = $parser->parse($request->file('file'));

            Log::info('parseZip AJAX result', [
                'readme_found' => $metadata['readme_found'] ?? 'N/A',
                'has_installation' => !empty($metadata['installation']),
                'has_faq' => !empty($metadata['faq']),
            ]);

            return response()->json(['success' => true, 'metadata' => $metadata]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * AJAX endpoint: parse ZIP readme and return structured sections.
     * Also updates product's readme_raw, readme_sections, description, installation, faq.
     */
    public function parseReadme(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:zip|max:102400',
        ]);

        try {
            $parser = app(WpPluginParserService::class);
            $result = $parser->parseAllSections($request->file('file'));

            if (!($result['readme_found'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => 'readme.txt not found in ZIP archive',
                ], 422);
            }

            $sections = $result['sections'] ?? [];
            $updateData = [];

            // 更新 readme_raw 和 readme_sections
            if ($result['readme_raw'] !== null) {
                $updateData['readme_raw'] = $result['readme_raw'];
            }
            if (!empty($sections)) {
                $updateData['readme_sections'] = $sections;

                // 回写 description（使用 readme == Description == 段落）
                if (!empty($sections['description'])) {
                    $updateData['description'] = $sections['description'];
                }

                // 回写 installation 和 faq（仅当产品字段为空时）
                if (!empty($sections['installation']) && empty($product->installation)) {
                    $updateData['installation'] = $sections['installation'];
                }
                if (!empty($sections['faq']) && empty($product->faq)) {
                    $updateData['faq'] = $sections['faq'];
                }
            }

            if (!empty($updateData)) {
                $product->update($updateData);
            }

            return response()->json([
                'success'  => true,
                'sections' => $sections,
                'updated'  => array_keys($updateData),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * One-click inject: download original ZIP, inject protection modules, re-upload.
     */
    public function injectNow(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        // Validate request - redirect back if not a POST
        if (!$request->isMethod('POST')) {
            return redirect()->back()->with('error', __('developer.invalid_request'));
        }

        $validated = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*' => 'in:integrity_guard,secondary_check,license_gate,auto_guardian',
        ]);

        $modules = $validated['modules'];
        $latestPackage = UpdatePackage::where('product_id', $product->id)->latest('id')->first();

        if (!$latestPackage) {
            return back()->with('error', __('developer.no_version_found'));
        }

        $originalUrl = $latestPackage->original_file_url ?: $latestPackage->file_url;

        // Download original ZIP to temp file
        $tempOriginal = storage_path('app/tmp_reinject_' . uniqid() . '.zip');
        $storageService = app(ProductStorageService::class);

        try {
            $storageService->downloadTo($originalUrl, $tempOriginal);

            // 如果没有原始备份，先保存当前文件作为原始备份
            if (!$latestPackage->has_original || !$latestPackage->original_file_url) {
                $originalUploaded = new \Illuminate\Http\UploadedFile($tempOriginal, 'original.zip', 'application/zip', null, true);
                $originalResult = $storageService->storeOriginal($originalUploaded, $product->slug, $latestPackage->version, $product->tenant_id);
                $latestPackage->update([
                    'original_file_url' => $originalResult['url'],
                    'has_original' => true,
                ]);
            }

            $injector = app(LicenseProtectionInjector::class);
            $siteUrl = SiteSetting::get('site_url', config('app.url'));
            $apiUrl = rtrim($siteUrl, '/') . '/api/v2';
            
            // Safely get tenant by ID to avoid relationship queries
            $tenantId = $product->tenant_id;
            $tenant = Tenant::find($tenantId);
            
            if (!$tenant) {
                return back()->with('error', __('developer.tenant_not_found'));
            }
            
            $apiKey = $tenant->apiKeys()->where('active', true)->first()?->key ?? '';

            if (!$apiUrl || !$apiKey) {
                return back()->with('error', __('developer.api_config_missing'));
            }

            // Full injection (inject() handles all modules internally)
            $injector->inject($tempOriginal, $apiUrl, $apiKey, $product->slug, $product->type);

            // [NEW] Verify injection success before persisting
            $verification = $injector->verifyInjection($tempOriginal);
            if ($verification['status'] !== 'success') {
                Log::error('[injectNow] Injection verification failed', [
                    'product_slug' => $product->slug,
                    'verification' => $verification,
                ]);
                return back()->with('error', __('developer.inject_verification_failed') . ': ' . $verification['reason']);
            }

            // Store the verified injected ZIP
            $injectedFile = new \Illuminate\Http\UploadedFile($tempOriginal, 'injected.zip', 'application/zip', null, true);
            $result = $storageService->store($injectedFile, $product->slug, $latestPackage->version, $product->tenant_id);

            // Update version record
            $latestPackage->update([
                'file_url' => $result['url'],
                'injection_modules' => json_encode(['sdk', 'integrity_guard', 'license_gate', 'secondary_cross_check', 'self_guard']), // Complete injection modules
            ]);

            // Update product download link
            $product->update(['download_url' => $result['url']]);

            return back()->with('success', __('developer.inject_success_message'));
        } catch (\Exception $e) {
            Log::warning('[injectNow] Injection failed', ['error' => $e->getMessage()]);
            return back()->with('error', __('developer.inject_failed') . ': ' . $e->getMessage());
        } finally {
            if (file_exists($tempOriginal)) {
                @unlink($tempOriginal);
            }
        }
    }

    /**
     * Handle product actions (inject/uninject) from product detail page
     */
    public function handleAction(Request $request, Product $product)
    {
        $action = $request->input('action');
        
        if ($action === 'inject') {
            return $this->injectNow($request, $product);
        } elseif ($action === 'uninject') {
            return $this->uninjectNow($request, $product);
        }
        
        return back()->with('error', __('developer.invalid_action'));
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        try {
            // Force delete the product (this triggers the boot() method cleanup)
            $product->forceDelete();
            
            return back()->with('success', __('dev_products.product_deleted'));
        } catch (\Exception $e) {
            \Log::error('Failed to delete product: {product_id}', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            return back()->with('error', __('dev_products.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 一键复原 — 恢复原始 ZIP 并通知 SDK 删除 mu-plugin
     *
     * 平台侧逻辑：
     * 1. 若存在原始 ZIP（has_original && original_file_url），恢复 download_url 和 file_url 为原始文件地址
     * 2. 清除注入记录（injection_modules）
     * 3. Best-effort 调用 SDK 端点通知删除 mu-plugin
     */
    public function uninjectNow(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        // Validate request - redirect back if not a POST
        if (!$request->isMethod('POST')) {
            return redirect()->back()->with('error', __('developer.invalid_request'));
        }

        $latestPackage = UpdatePackage::where('product_id', $product->id)->latest('id')->first();

        if (!$latestPackage) {
            return back()->with('error', __('developer.no_version_found'));
        }

        // 恢复原始 ZIP 下载链接
        if ($latestPackage->has_original && $latestPackage->original_file_url) {
            $product->update(['download_url' => $latestPackage->original_file_url]);
            $latestPackage->update([
                'file_url' => $latestPackage->original_file_url,
                'injection_modules' => null,
            ]);
        } else {
            // 没有原始文件，尝试用 uninject() 清理当前 ZIP
            $injector = app(\App\Services\LicenseProtectionInjector::class);
            $tempPath = storage_path('app/tmp_uninject_' . uniqid() . '.zip');
            $storageService = app(\App\Services\ProductStorageService::class);

            try {
                $storageService->downloadTo($latestPackage->file_url, $tempPath);
                $injector->uninject($tempPath);

                $cleanFile = new \Illuminate\Http\UploadedFile($tempPath, 'clean.zip', 'application/zip', null, true);
                $result = $storageService->store($cleanFile, $product->slug, $latestPackage->version, $product->tenant_id);

                $product->update(['download_url' => $result['url']]);
                $latestPackage->update([
                    'file_url' => $result['url'],
                    'injection_modules' => null,
                ]);
            } catch (\Exception $e) {
                \Log::warning('uninject fallback failed', ['error' => $e->getMessage()]);
                $latestPackage->update(['injection_modules' => null]);
            } finally {
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        // 清理 integrity hash
        SiteSetting::set("integrity_hash_{$product->slug}", '');
        SiteSetting::set("integrity_api_key_hash_{$product->slug}", '');

        // Best-effort: notify all activated domains to delete mu-plugin
        $siteUrl = SiteSetting::get('site_url', config('app.url'));
        $apiUrl = rtrim($siteUrl, '/') . '/api/v2';
        $apiKey = $product->tenant->apiKeys()->where('active', true)->first()?->key ?? '';
        $notified = 0;

        if ($apiUrl && $apiKey) {
            $activations = $product->licenses()
                ->with('activations')
                ->get()
                ->flatMap(fn($l) => $l->activations)
                ->unique('domain');

            foreach ($activations as $activation) {
                $domain = $activation->domain;
                if (!$domain) continue;

                try {
                    $scheme = str_starts_with($domain, 'http') ? '' : 'https://';
                    $endpoint = rtrim($scheme . $domain, '/') . '/wp-json/licenseos/v1/delete-mu-plugin';

                    @file_get_contents($endpoint . '?' . http_build_query([
                        'product_slug' => $product->slug,
                        'api_key' => $apiKey,
                        'api_url' => $apiUrl,
                    ]));
                    $notified++;
                } catch (\Exception $e) {
                    Log::warning('通知 SDK 删除 mu-plugin 失败', [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return back()->with('success', __('developer.uninject_success', ['count' => $notified]));
    }

    /**
     * AJAX endpoint: upload a single screenshot image.
     */
    public function uploadScreenshot(Request $request, Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|image|max:5120', // 5MB
        ]);

        $file = $request->file('file');
        $path = $file->store('screenshots/' . $product->slug, 'public');
        $url = Storage::url($path);

        return response()->json(['url' => $url]);
    }

    /**
     * AJAX endpoint: check slug uniqueness.
     */
    public function checkSlug(Request $request)
    {
        $request->validate([
            'slug' => 'required|string|max:255',
            'exclude_id' => 'nullable|integer',
        ]);

        $query = Product::where('slug', $request->input('slug'));
        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', $request->input('exclude_id'));
        }

        return response()->json(['exists' => $query->exists()]);
    }

    /**
     * Build normalized pricing_tiers array from form input.
     */
    private function buildPricingTiers(array $raw): array
    {
        $tiers = [];
        foreach ($raw as $row) {
            $type = $row['type'] ?? '';
            if (!$type) continue;
            $tiers[] = [
                'type'        => $type,
                'enabled'     => !empty($row['enabled']),
                'days'        => (int) ($row['days'] ?? 0),
                'months'      => (int) ($row['months'] ?? 0),
                'price'       => $type === 'trial' ? 0.00 : (float) ($row['price'] ?? 0), // trial 强制免费
                'label'       => $row['label'] ?? '',
                'description' => $row['description'] ?? '',
                'auto_renew'  => !empty($row['auto_renew']),
            ];
        }

        // pricing_tiers 为空时（用户删掉了所有层级），默认填充 trial 层级，产品变为免费
        if (empty($tiers)) {
            $tiers[] = [
                'type'        => 'trial',
                'enabled'     => true,
                'days'        => 7,
                'months'      => 0,
                'price'       => 0.00,
                'label'       => 'Free Trial',
                'description' => '',
                'auto_renew'  => false,
            ];
        }

        return $tiers;
    }

    /**
     * Check if pricing tiers contain only trial (no paid tiers).
     */
    private function hasOnlyTrialTier(array $tiers): bool
    {
        foreach ($tiers as $tier) {
            if (($tier['type'] ?? '') !== 'trial') {
                return false;
            }
        }
        return !empty($tiers);
    }
}
