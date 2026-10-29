@extends('layouts.app')
@section('title', __('dev_products.create_title'))
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('developer.products.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">&rarr; {{ __('dev_products.back') }}</a>
    <h1 class="text-2xl font-bold mt-4 mb-6 dark:text-white">{{ __('dev_products.create_title') }}</h1>

    <form method="POST" action="{{ route('developer.products.store') }}" enctype="multipart/form-data" novalidate class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 space-y-4">
        @csrf

        <!-- Scan Status Display -->
        <div id="scan-status" class="hidden mt-3 rounded-lg border p-4" role="alert">
            <div class="flex items-start">
                <div id="scan-icon" class="flex-shrink-0"></div>
                <div class="ml-3 flex-1">
                    <h3 id="scan-title" class="text-sm font-medium"></h3>
                    <div id="scan-content" class="mt-1 text-sm"></div>
                    <ul id="scan-issues" class="mt-2 ml-6 list-disc text-xs"></ul>
                    <ul id="scan-warnings" class="mt-2 ml-6 list-disc text-xs"></ul>
                </div>
                <div id="scan-close" class="ml-auto pl-3 cursor-pointer text-gray-400 hover:text-gray-600">×</div>
            </div>
        </div>

        @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
            <div class="text-red-700 dark:text-red-400 font-medium mb-2">{{ __('以下字段需要修正') }}：</div>
            <ul class="list-disc list-inside text-red-600 dark:text-red-400 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Product Type (must select first to control conditional sections) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.type') }}</label>
            <select name="type" id="product-type" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" onchange="syncPlaceholders(this.value)">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Product Category --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.category') }}</label>
            <select name="category_id" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
                <option value="">{{ __('dev_products.none') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- ZIP Upload Section (WP types only) --}}
        <div id="zip-upload-section" class="border dark:border-gray-700 rounded-lg p-4 bg-indigo-50/50 dark:bg-indigo-900/10">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">{{ __('dev_products.zip_upload_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" id="zip-upload-hint">{{ __('dev_products.zip_upload_hint') }}</p>
            <div class="flex items-center gap-3">
                <label for="file-input" class="px-4 py-1.5 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-700 cursor-pointer select-none transition-colors">
                    {{ __('dev_products.select_file') }}
                </label>
                <input type="file" name="file" accept=".zip" id="file-input" class="hidden">
                <span id="file-name" class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_products.no_file') }}</span>
                <span id="upload-success-msg" class="hidden text-sm text-green-600 dark:text-green-400 font-medium">✓ 已上传</span>
                <span id="parse-status" class="hidden text-sm"></span>
            </div>
        </div>

        {{-- Basic Info --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.name') }}</label>
            <input type="text" name="name" id="field-name" value="{{ old('name') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.slug') }}</label>
            <input type="text" name="slug" id="field-slug" value="{{ old('slug') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required placeholder="my-awesome-theme">
            <span id="slug-check-msg" class="text-xs mt-1 block"></span>
            @error('slug') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.base_price') }} ({{ $currencySymbol }}) <span class="text-xs text-gray-400">{{ __('dev_products.price_fallback') }}</span></label>
                <input type="number" name="price" step="0.01" value="{{ old('price', '29.99') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
                @error('price') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.version') }}</label>
                <input type="text" name="version" id="field-version" value="{{ old('version', '1.0.0') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required placeholder="1.0.0">
                @error('version') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.description') }}</label>
            <textarea name="description" id="field-description" rows="4" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">{{ old('description') }}</textarea>
        </div>

        {{-- Metadata Section --}}
        <div id="wp-metadata-section" class="border-t dark:border-gray-700 pt-4 mt-4">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1" id="metadata-section-title">{{ __('dev_products.wp_metadata_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" id="metadata-section-hint">{{ __('dev_products.wp_metadata_hint') }}</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.author') }}</label>
                    <input type="text" name="author" id="field-author" value="{{ old('author') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="{{ __('dev_products.from_zip') }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.author_profile') }}</label>
                    <input type="url" name="author_profile" id="field-author-profile" value="{{ old('author_profile') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1" id="homepage-label">{{ __('dev_products.plugin_homepage') }}</label>
                    <input type="url" name="plugin_homepage" id="field-plugin-homepage" value="{{ old('plugin_homepage') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.requires_wp') }}</label>
                    <input type="text" name="requires_wp" id="field-requires-wp" value="{{ old('requires_wp') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10" placeholder="6.0">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.tested_up_to') }}</label>
                    <input type="text" name="tested_up_to" id="field-tested-up-to" value="{{ old('tested_up_to') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10" placeholder="6.5">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.requires_php') }}</label>
                    <input type="text" name="requires_php" id="field-requires-php" value="{{ old('requires_php') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10" placeholder="7.4">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.license') }}</label>
                    <input type="text" name="license" id="field-license" value="{{ old('license') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="100" placeholder="GPL-2.0+">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.license_uri') }}</label>
                    <input type="url" name="license_uri" id="field-license-uri" value="{{ old('license_uri') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="255" placeholder="http://www.gnu.org/licenses/gpl-2.0.html">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.text_domain') }}</label>
                    <input type="text" name="text_domain" id="field-text-domain" value="{{ old('text_domain') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="100" placeholder="my-theme">
                </div>
                <div class="wp-only-field">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.wp_tags') }}</label>
                    <input type="text" name="wp_tags" id="field-wp-tags" value="{{ old('wp_tags') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="500" placeholder="responsive, translation-ready, custom-header">
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.short_description') }}</label>
                <input type="text" name="short_description" id="field-short-description" value="{{ old('short_description') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="500" placeholder="{{ __('dev_products.from_zip') }}">
            </div>

            {{-- Installation Instructions --}}
            <div class="mt-3">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.installation_instructions') }}</label>
                <textarea name="installation" id="installation" rows="6" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">{{ old('installation') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">{{ __('dev_products.html_content_hint') }}</p>
            </div>

            {{-- FAQ --}}
            <div class="mt-3">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.faq_title') }}</label>
                <textarea name="faq" id="faq" rows="6" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">{{ old('faq') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">{{ __('dev_products.html_content_hint') }}</p>
            </div>
        </div>




        {{-- Reviews Toggle --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="reviews_enabled" value="1" class="rounded" {{ old('reviews_enabled', true) ? 'checked' : '' }}>
                <div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('启用评价') }}</span>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('允许用户对该产品提交评价和评分。') }}</p>
                </div>
            </label>
        </div>

        {{-- Pricing Tiers Section --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ __('dev_products.pricing_tiers') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('dev_products.tiers_hint') }}</p>
            </div>

            {{-- Free product toggle --}}
            <div class="mb-4 p-4 border-2 border-dashed dark:border-gray-600 rounded-lg">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="is-free-product" class="w-5 h-5 rounded text-green-600 focus:ring-green-500">
                    <div>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ __('dev_products.free_product') }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('dev_products.free_product_desc') }}</p>
                    </div>
                </label>
            </div>

            {{-- Hidden free tier inputs (submitted when checkbox is checked) --}}
            <div id="free-tier-inputs" style="display:none">
                <input type="hidden" name="pricing_tiers[0][enabled]" value="1">
                <input type="hidden" name="pricing_tiers[0][type]" value="free">
                <input type="hidden" name="pricing_tiers[0][price]" value="0">
                <input type="hidden" name="pricing_tiers[0][label]" value="{{ __('dev_tiers.free') }}">
                <input type="hidden" name="pricing_tiers[0][description]" value="{{ __('dev_products.free_description') }}">
            </div>

            {{-- Tier editor section (hidden when free is checked) --}}
            <div id="tier-editor-section">
                <div class="flex items-center justify-end mb-3">
                    <button type="button" id="add-tier-btn" class="px-3 py-1.5 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+ {{ __('dev_products.add_tier') }}</button>
                </div>
                <div id="tiers-container" class="space-y-3">
                    {{-- Tier rows added via JS --}}
                </div>
            </div>
        </div>

        {{-- SEO Section --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('dev_products.seo_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('dev_products.seo_hint') }}</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.meta_title') }}</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title') }}" maxlength="255" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="{{ __('dev_products.meta_title_ph') }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.meta_description') }}</label>
                    <textarea name="meta_description" rows="2" maxlength="500" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="{{ __('dev_products.meta_description_ph') }}">{{ old('meta_description') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.keywords') }}</label>
                    <input type="text" name="keywords" id="field-keywords" value="{{ old('keywords') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="theme, wordpress, responsive">
                </div>
            </div>
        </div>

        {{-- Featured --}}
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="featured" value="1" {{ old('featured') ? 'checked' : '' }} class="rounded">
            <span class="text-gray-700 dark:text-gray-300">{{ __('dev_products.featured') }}</span>
        </label>

        <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('dev_products.create_btn') }}</button>
    </form>
</div>

<script>
(function() {
    // === Type label mapping ===
    var TYPE_LABELS = {
        'wp_plugin': '{{ __("dev_products.type_wp_plugin") }}',
        'wp_theme': '{{ __("dev_products.type_wp_theme") }}',
        'android_app': '{{ __("dev_products.type_android_app") }}',
        'ios_app': '{{ __("dev_products.type_ios_app") }}',
        'desktop_app': '{{ __("dev_products.type_desktop_app") }}',
        'web_app': '{{ __("dev_products.type_web_app") }}',
        'chrome_extension': '{{ __("dev_products.type_chrome_extension") }}',
        'shopify_app': '{{ __("dev_products.type_shopify_app") }}',
        'figma_plugin': '{{ __("dev_products.type_figma_plugin") }}',
        'laravel_package': '{{ __("dev_products.type_laravel_package") }}',
        'source_code': '{{ __("dev_products.type_source_code") }}',
        'script': '{{ __("dev_products.type_script") }}',
        'api_service': '{{ __("dev_products.type_api_service") }}',
        'template': '{{ __("dev_products.type_template") }}',
        'ebook': '{{ __("dev_products.type_ebook") }}',
        'course': '{{ __("dev_products.type_course") }}',
        'other': '{{ __("dev_products.type_other") }}'
    };

    function getTypeLabel() {
        return TYPE_LABELS[typeSelect.value] || '{{ __("dev_products.type_other") }}';
    }

    // === Conditional visibility based on product type ===
    var WP_TYPES = ['wp_plugin', 'wp_theme'];
    var typeSelect = document.getElementById('product-type');
    var zipSection = document.getElementById('zip-upload-section');
    var wpMetaSection = document.getElementById('wp-metadata-section');
    var licenseProtectionSection = document.getElementById('license-protection-section');

    var zipParsed = false;
    var homepageLabel = document.getElementById('homepage-label');
    var metadataTitle = document.getElementById('metadata-section-title');
    var metadataHint = document.getElementById('metadata-section-hint');
    var licenseHint = document.getElementById('license-protection-hint');
    var zipUploadTitle = document.querySelector('#zip-upload-section h2');
    var zipUploadHint = document.getElementById('zip-upload-hint');

    // === File input references (declared early so type-change callback can access them) ===
    var fileInput = document.getElementById('file-input');
    var fileNameSpan = document.getElementById('file-name');
    var parseStatus = document.getElementById('parse-status');
    var noFileText = '{{ __('dev_products.no_file') }}';
    var parsingText = '{{ __('dev_products.parsing') }}';
    var parsedText = '{{ __('dev_products.parsed') }}';
    var parseFailText = '{{ __('dev_products.parse_failed') }}';
    var parseUrl = '{{ route('developer.products.parse-zip') }}';
    var scanUrl = '{{ route('developer.products.scan-zip') }}';

    function updateDynamicLabels() {
        if (!typeSelect) return;
        var typeLabel = getTypeLabel();
        var isWp = WP_TYPES.indexOf(typeSelect.value) !== -1;

        // ZIP upload section title & hint
        if (zipUploadTitle) zipUploadTitle.textContent = typeLabel + ' ZIP';
        if (zipUploadHint) zipUploadHint.textContent = typeLabel + ' ZIP';

        // Metadata section title & hint
        if (metadataTitle) metadataTitle.textContent = typeLabel + ' - ' + '{{ __("dev_products.metadata_info") }}';
        if (metadataHint) metadataHint.textContent = typeLabel + ' ' + '{{ __("dev_products.metadata_hint_suffix") }}';

        // Homepage label
        if (homepageLabel) homepageLabel.textContent = typeLabel + ' {{ __("dev_products.homepage_label") }}';

        // License protection hint
        if (licenseHint) licenseHint.textContent = '{{ __("dev_products.license_protection_hint_prefix") }}' + typeLabel + ' ZIP {{ __("dev_products.license_protection_hint_suffix") }}';

        // Keywords placeholder — 按具体类型适配
        var keywordsField = document.querySelector('[name="keywords"]');
        if (keywordsField) {
            var type = typeSelect.value;
            if (type === 'wp_plugin') {
                keywordsField.placeholder = 'plugin, wordpress, seo';
            } else if (type === 'wp_theme') {
                keywordsField.placeholder = 'theme, wordpress, responsive';
            } else {
                keywordsField.placeholder = 'digital, product, download';
            }
            // 如果当前值包含之前类型的关键词，也清空
            var kwVal = keywordsField.value || '';
            if (type === 'wp_theme' && kwVal.indexOf('plugin') !== -1) {
                keywordsField.value = '';
            } else if (type === 'wp_plugin' && kwVal.indexOf('theme') !== -1 && kwVal.indexOf('plugin') === -1) {
                keywordsField.value = '';
            }
        }

        // Slug placeholder — 按类型适配
        var slugField = document.getElementById('field-slug');
        if (slugField) {
            if (typeSelect.value === 'wp_plugin') {
                slugField.placeholder = 'my-awesome-plugin';
            } else if (typeSelect.value === 'wp_theme') {
                slugField.placeholder = 'my-awesome-theme';
            } else {
                slugField.placeholder = 'my-awesome-product';
            }
        }
    }

    function toggleWpSections() {
        if (!typeSelect) return;
        var isWp = WP_TYPES.indexOf(typeSelect.value) !== -1;
        if (zipSection) zipSection.style.display = isWp ? '' : 'none';
        if (wpMetaSection) wpMetaSection.style.display = isWp ? '' : 'none';
        if (licenseProtectionSection) licenseProtectionSection.style.display = isWp ? '' : 'none';

        // WP-only fields (requires_wp, tested_up_to)
        var wpOnlyFields = document.querySelectorAll('.wp-only-field');
        wpOnlyFields.forEach(function(el) {
            el.style.display = isWp ? '' : 'none';
        });

        updateDynamicLabels();
    }

    // === Scan status management ===
    var showScanStatus = function(type, message, issues, warnings) {
        var statusDiv = document.getElementById('scan-status');
        var iconDiv = document.getElementById('scan-icon');
        var titleEl = document.getElementById('scan-title');
        var contentEl = document.getElementById('scan-content');
        var issuesUl = document.getElementById('scan-issues');
        var warningsUl = document.getElementById('scan-warnings');
        
        // Clear previous content
        if (issuesUl) issuesUl.innerHTML = '';
        if (warningsUl) warningsUl.innerHTML = '';
        
        // Set styles based on type
        switch (type) {
            case 'scanning':
                statusDiv.className = 'mt-3 rounded-lg border p-4 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800';
                iconDiv.innerHTML = '<svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                titleEl.textContent = '正在扫描文件安全性...';
                titleEl.className = 'text-sm font-medium text-blue-800 dark:text-blue-300';
                contentEl.textContent = message;
                contentEl.className = 'mt-1 text-sm text-blue-700 dark:text-blue-400';
                break;
                
            case 'success':
                statusDiv.className = 'mt-3 rounded-lg border p-4 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800';
                iconDiv.innerHTML = '<svg class="h-5 w-5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
                titleEl.textContent = '✓ ' + message;
                titleEl.className = 'text-sm font-medium text-green-800 dark:text-green-300';
                contentEl.textContent = '';
                contentEl.className = 'mt-1 text-sm text-green-700 dark:text-green-400';
                break;
                
            case 'error':
                statusDiv.className = 'mt-3 rounded-lg border p-4 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
                iconDiv.innerHTML = '<svg class="h-5 w-5 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
                titleEl.textContent = '✗ ' + message;
                titleEl.className = 'text-sm font-medium text-red-800 dark:text-red-300';
                contentEl.textContent = '';
                contentEl.className = 'mt-1 text-sm text-red-700 dark:text-red-400';
                
                // Add critical issues to list (file-level problems)
                if (issues && issues.length > 0) {
                    issues.forEach(function(issue) {
                        var li = document.createElement('li');
                        li.textContent = issue;
                        if (issuesUl) issuesUl.appendChild(li);
                    });
                }
                
                // Disable submit button - critical errors block submission
                var submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    submitBtn.title = '请先修复安全问题后重新上传 ZIP';
                }
                break;
                
            case 'warning':
                statusDiv.className = 'mt-3 rounded-lg border p-4 bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800';
                iconDiv.innerHTML = '<svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
                titleEl.textContent = '⚠ ' + message;
                titleEl.className = 'text-sm font-medium text-yellow-800 dark:text-yellow-300';
                contentEl.textContent = '';
                contentEl.className = 'mt-1 text-sm text-yellow-700 dark:text-yellow-400';
                
                // Add warnings to list (recommendations)
                if (warnings && warnings.length > 0) {
                    warnings.forEach(function(warning) {
                        var li = document.createElement('li');
                        li.textContent = warning;
                        if (warningsUl) warningsUl.appendChild(li);
                    });
                }
                break;
        }
        
        // Show/hide div
        statusDiv.classList.toggle('hidden', type !== 'scanning' && type !== 'success' && type !== 'error' && type !== 'warning');
    };

    // Auto-hide after 5 seconds for success/warning
    if (typeof window.showScanStatusDelayed === 'undefined') {
        window.showScanStatusDelayed = false;
    }
    if (typeSelect) typeSelect.addEventListener('change', function() {
        // === 始终清空：基础信息字段（无论是否上传过 ZIP） ===
        ['name', 'slug', 'price', 'version', 'short_description'].forEach(function(fieldName) {
            var field = document.querySelector('[name="' + fieldName + '"]');
            if (field) field.value = '';
        });
        var descField = document.querySelector('textarea[name="description"]');
        if (descField) descField.value = '';

        // === 仅在 ZIP 已解析成功时：重置文件 + 清空元数据 ===
        if (zipParsed) {
            fileInput.value = '';
            fileNameSpan.textContent = noFileText;
            parseStatus.textContent = '';
            parseStatus.className = 'hidden text-sm';

            var zipMetaIds = [
                'field-author', 'field-author-profile', 'field-plugin-homepage',
                'field-requires-wp', 'field-tested-up-to', 'field-requires-php',
                'field-license', 'field-license-uri', 'field-text-domain', 'field-wp-tags'
            ];
            zipMetaIds.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            var installField = document.getElementById('installation');
            if (installField) installField.value = '';
            var faqFieldEl = document.getElementById('faq');
            if (faqFieldEl) faqFieldEl.value = '';

            zipParsed = false;
        }
        toggleWpSections();
    });
    if (typeSelect) toggleWpSections();

    // === ZIP auto-parse via AJAX ===
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            var file = this.files[0];
            zipParsed = false; // 新文件未解析前重置标记
            
            // 隐藏上传成功提示
            var uploadSuccessMsg = document.getElementById('upload-success-msg');
            if (uploadSuccessMsg) uploadSuccessMsg.classList.add('hidden');
            
            if (!file) {
                fileNameSpan.textContent = noFileText;
                parseStatus.classList.add('hidden');
                return;
            }
            
            // 立即显示文件名和"已上传"提示
            fileNameSpan.textContent = file.name;
            if (uploadSuccessMsg) {
                uploadSuccessMsg.classList.remove('hidden');
            }
            
            // Perform security scan first
            showScanStatus('scanning', '正在扫描文件安全性...');
            
            var scanFormData = new FormData();
            scanFormData.append('file', file);
            scanFormData.append('_token', '{{ csrf_token() }}');
            
            fetch(scanUrl, { method: 'POST', body: scanFormData, headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                .then(function(r) { return r.json(); })
                .then(function(scanResult) {
                    // 检查扫描结果
                    if (!scanResult.safe) {
                        // Security check failed
                        showScanStatus('error', scanResult.message || '安全检测未通过', scanResult.issues, scanResult.warnings);
                        
                        // Disable submit button
                        var submitBtn = document.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            submitBtn.title = '请先修复安全问题后重新上传 ZIP';
                        }
                        return;
                    }
                    
                    // Scan passed - show success message with green color
                    showScanStatus('success', scanResult.message || '文件扫描通过', [], scanResult.warnings);
                    console.log('✓ 扫描通过，开始解析元数据...');
                    
                    // Re-enable submit button
                    var submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        submitBtn.title = '';
                    }
                    
                    // Then parse metadata
                    parseMetadata(file);
                })
                .catch(function(err) {
                    console.error('Scan error:', err);
                    showScanStatus('error', '扫描失败：' + err.message);
                });
        });
    }
    
    // Separate metadata parsing function
    function parseMetadata(file) {
        parseStatus.classList.remove('hidden');
        parseStatus.className = 'text-sm text-yellow-600 dark:text-yellow-400';
        parseStatus.textContent = parsingText;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch(parseUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.metadata) {
                    zipParsed = true;
                    var m = data.metadata;
                    if (m.name) document.getElementById('field-name').value = m.name;
                    if (m.version) document.getElementById('field-version').value = m.version;
                    if (m.description) document.getElementById('field-description').value = m.description;
                    if (m.short_description) document.getElementById('field-short-description').value = m.short_description;
                    if (m.author) document.getElementById('field-author').value = m.author;
                    if (m.author_profile) document.getElementById('field-author-profile').value = m.author_profile;
                    if (m.plugin_homepage) document.getElementById('field-plugin-homepage').value = m.plugin_homepage;
                    if (m.requires_wp) document.getElementById('field-requires-wp').value = m.requires_wp;
                    if (m.tested_up_to) document.getElementById('field-tested-up-to').value = m.tested_up_to;
                    if (m.requires_php) document.getElementById('field-requires-php').value = m.requires_php;
                    if (m.license) document.getElementById('field-license').value = m.license;
                    if (m.license_uri) document.getElementById('field-license-uri').value = m.license_uri;
                    if (m.text_domain) document.getElementById('field-text-domain').value = m.text_domain;
                    if (m.wp_tags) document.getElementById('field-wp-tags').value = m.wp_tags;
                    // Auto-fill installation and faq from readme (only if currently empty)
                    var installField = document.getElementById('installation');
                    if (installField && !installField.value && m.installation) installField.value = m.installation;
                    var faqField = document.getElementById('faq');
                    if (faqField && !faqField.value && m.faq) faqField.value = m.faq;
                    // Auto-generate slug from name if slug is empty
                    var slugField = document.getElementById('field-slug');
                    if (!slugField.value && m.name) {
                        slugField.value = m.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                    }
                    // readme_found 反馈：区分 "解析成功" 和 "未找到 readme.txt"
                    if (m.readme_found === false) {
                        parseStatus.className = 'text-sm text-yellow-600 dark:text-yellow-400';
                        parseStatus.textContent = 'PHP headers parsed, but readme.txt not found in ZIP';
                    } else {
                        var missingFields = [];
                        // wp_theme readme typically has no Installation/FAQ sections — skip those checks
                        if (typeSelect && typeSelect.value !== 'wp_theme') {
                            if (!m.installation) missingFields.push('Installation');
                            if (!m.faq) missingFields.push('FAQ');
                        }
                        if (missingFields.length > 0) {
                            parseStatus.className = 'text-sm text-yellow-600 dark:text-yellow-400';
                            parseStatus.textContent = '解析成功 (' + missingFields.join(', ') + ' not found in readme)';
                            console.log('⚠ 解析成功，缺少:', missingFields.join(', '));
                        } else {
                            parseStatus.className = 'text-sm text-green-600 dark:text-green-400';
                            parseStatus.textContent = '解析成功 ✓';
                            console.log('✅ 所有元数据已自动填充');
                        }
                    }
                } else {

                    parseStatus.className = 'text-sm text-red-600 dark:text-red-400';

                    parseStatus.textContent = parseFailText + (data.error ? ': ' + data.error : '');

                }

            })
            .catch(function(err) {
                parseStatus.className = 'text-sm text-red-600 dark:text-red-400';
                parseStatus.textContent = parseFailText;
            });
    }

    const TIER_TYPES = {
        'trial':    '{{ __('dev_tiers.trial') }}',
        '1_month':  '{{ __('dev_tiers.1_month') }}',
        '3_months': '{{ __('dev_tiers.3_months') }}',
        '6_months': '{{ __('dev_tiers.6_months') }}',
        '1_year':   '{{ __('dev_tiers.1_year') }}',
        'lifetime': '{{ __('dev_tiers.lifetime') }}'
    };
    const T = {
        enabled:    '{{ __('dev_tiers.enabled') }}',
        remove:     '{{ __('dev_tiers.remove') }}',
        type:       '{{ __('dev_tiers.type') }}',
        select:     '{{ __('dev_tiers.select') }}',
        days:       '{{ __('dev_tiers.days') }}',
        months:     '{{ __('dev_tiers.months') }}',
        price:      '{{ __('dev_tiers.price') }}',
        label:      '{{ __('dev_tiers.label') }}',
        label_ph:   '{{ __('dev_tiers.label_ph') }}',
        desc:       '{{ __('dev_tiers.desc') }}',
        desc_ph:    '{{ __('dev_tiers.desc_ph') }}',
        auto_renew: '{{ __('dev_tiers.auto_renew') }}'
    };
    const CURRENCY = '{{ $currencySymbol }}';
    let tierIndex = 0;
    const container = document.getElementById('tiers-container');

    function addTier(type, enabled, price, label, description, days, months, autoRenew) {
        type = type || ''; enabled = enabled !== false; price = price || '';
        label = label || ''; description = description || ''; days = days || '7';
        months = months !== undefined ? months : '1'; autoRenew = autoRenew || false;

        const idx = tierIndex++;
        const div = document.createElement('div');
        div.className = 'tier-row bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border dark:border-gray-600';
        div.innerHTML =
            '<div class="flex items-center justify-between mb-3">' +
                '<label class="flex items-center gap-2 text-sm">' +
                    '<input type="hidden" name="pricing_tiers[' + idx + '][enabled]" value="0" class="tier-enabled-fallback">' +
                    '<input type="checkbox" name="pricing_tiers[' + idx + '][enabled]" value="1" ' + (enabled ? 'checked' : '') + ' class="rounded tier-enabled-input">' +
                    '<span class="font-medium text-gray-700 dark:text-gray-300">' + T.enabled + '</span>' +
                '</label>' +
                '<button type="button" class="text-red-500 hover:text-red-700 text-sm remove-tier-btn">&times; ' + T.remove + '</button>' +
            '</div>' +
            '<div class="grid grid-cols-2 md:grid-cols-4 gap-3">' +
                '<div>' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.type + '</label>' +
                    '<select name="pricing_tiers[' + idx + '][type]" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm tier-type-select">' +
                        '<option value="">-- ' + T.select + ' --</option>' +
                        Object.entries(TIER_TYPES).map(function(e) {
                            return '<option value="' + e[0] + '"' + (type===e[0]?' selected':'') + '>' + e[1] + '</option>';
                        }).join('') +
                    '</select>' +
                '</div>' +
                '<div class="tier-days-field" style="display:none">' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.days + '</label>' +
                    '<input type="number" name="pricing_tiers[' + idx + '][days]" value="' + days + '" min="1" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm">' +
                '</div>' +
                '<div class="tier-months-field">' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.months + '</label>' +
                    '<input type="number" name="pricing_tiers[' + idx + '][months]" value="' + months + '" min="0" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm">' +
                '</div>' +
                '<div>' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.price + ' (' + CURRENCY + ')</label>' +
                    '<input type="number" name="pricing_tiers[' + idx + '][price]" value="' + price + '" step="0.01" min="0" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm tier-price-input"' + ' required' + '>' +
                '</div>' +
            '</div>' +
            '<div class="grid grid-cols-2 gap-3 mt-3">' +
                '<div>' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.label + '</label>' +
                    '<input type="text" name="pricing_tiers[' + idx + '][label]" value="' + label + '" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm" placeholder="' + T.label_ph + '">' +
                '</div>' +
                '<div>' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.desc + '</label>' +
                    '<input type="text" name="pricing_tiers[' + idx + '][description]" value="' + description + '" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm" placeholder="' + T.desc_ph + '">' +
                '</div>' +
            '</div>' +
            '<div class="mt-2">' +
                '<label class="flex items-center gap-2 text-sm">' +
                    '<input type="checkbox" name="pricing_tiers[' + idx + '][auto_renew]" value="1" ' + (autoRenew ? 'checked' : '') + ' class="rounded">' +
                    '<span class="text-gray-600 dark:text-gray-400">' + T.auto_renew + '</span>' +
                '</label>' +
            '</div>';

        container.appendChild(div);
        updateTierFields(div);
        div.querySelector('.tier-type-select').addEventListener('change', function() { updateTierFields(div); });
        div.querySelector('.remove-tier-btn').addEventListener('click', function() { div.remove(); refreshTierOptions(); });
    }

    function updateTierFields(row) {
        var type = row.querySelector('.tier-type-select').value;
        row.querySelector('.tier-days-field').style.display = type === 'trial' ? '' : 'none';
        row.querySelector('.tier-months-field').style.display = (type === 'trial') ? 'none' : '';
        refreshTierOptions();
    }

    function refreshTierOptions() {
        var selects = container.querySelectorAll('.tier-type-select');
        selects.forEach(function(sel) {
            var currentVal = sel.value;
            sel.innerHTML = '<option value="">-- ' + T.select + ' --</option>';
            Object.entries(TIER_TYPES).forEach(function(e) {
                var opt = document.createElement('option');
                opt.value = e[0];
                opt.textContent = e[1];
                if (e[0] === currentVal) opt.selected = true;
                sel.appendChild(opt);
            });
        });
    }

    document.getElementById('add-tier-btn').addEventListener('click', function() { addTier(); });

    // === Free product checkbox logic ===
    var freeCheckbox = document.getElementById('is-free-product');
    var freeInputs = document.getElementById('free-tier-inputs');
    var tierSection = document.getElementById('tier-editor-section');

    freeCheckbox.addEventListener('change', function() {
        if (this.checked) {
            tierSection.style.display = 'none';
            freeInputs.style.display = 'block';
            tierSection.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = true; });
        } else {
            tierSection.style.display = '';
            freeInputs.style.display = 'none';
            tierSection.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = false; });
        }
    });

    // Pre-populate with common tiers (trial + paid tiers)
    addTier('trial',    true,  '0.00',  '{{ __('dev_tiers.pre_trial') }}',  '',                        '7', '0',  false);
    addTier('1_month',  false, '9.99',  '{{ __('dev_tiers.pre_month') }}',  '',                        '0', '1',  false);
    addTier('1_year',   true,  '29.99', '{{ __('dev_tiers.pre_year') }}',   '{{ __('dev_tiers.popular') }}', '0', '12', false);
    addTier('lifetime', false, '99.99', '{{ __('dev_tiers.pre_lifetime') }}','{{ __('dev_tiers.one_time') }}','0', '0',  false);

    // === Slug uniqueness check on blur ===
    var slugInput = document.getElementById('field-slug');
    var slugMsg = document.getElementById('slug-check-msg');
    var slugCheckUrl = '{{ route('developer.check-slug') }}';
    var csrfToken = '{{ csrf_token() }}';
    var SLUG_CHECKING = '{{ __('dev_slug.checking') }}';
    var SLUG_AVAILABLE = '{{ __('dev_slug.available') }}';
    var SLUG_TAKEN = '{{ __('dev_slug.taken') }}';
    if (slugInput) {
        slugInput.addEventListener('blur', function() {
            var slug = this.value.trim();
            if (!slug) { slugMsg.textContent = ''; return; }
            slugMsg.textContent = SLUG_CHECKING;
            slugMsg.className = 'text-xs mt-1 block text-gray-500 dark:text-gray-400';
            fetch(slugCheckUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({slug: slug})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.exists) {
                    slugMsg.textContent = SLUG_TAKEN;
                    slugMsg.className = 'text-xs mt-1 block text-red-600 dark:text-red-400';
                } else {
                    slugMsg.textContent = SLUG_AVAILABLE;
                    slugMsg.className = 'text-xs mt-1 block text-green-600 dark:text-green-400';
                }
            })
            .catch(function() {
                slugMsg.textContent = '';
            });
        });
    }
})();
</script>
<script>
function syncPlaceholders(type) {
    var sf = document.getElementById('field-slug');
    var kf = document.getElementById('field-keywords');
    if (sf) {
        if (type === 'wp_plugin') sf.placeholder = 'my-awesome-plugin';
        else if (type === 'wp_theme') sf.placeholder = 'my-awesome-theme';
        else sf.placeholder = 'my-awesome-product';
    }
    if (kf) {
        if (type === 'wp_plugin') {
            kf.placeholder = 'plugin, wordpress, seo';
            var v = kf.value || '';
            if (v.indexOf('theme') !== -1 && v.indexOf('plugin') === -1) kf.value = '';
        } else if (type === 'wp_theme') {
            kf.placeholder = 'theme, wordpress, responsive';
            var v = kf.value || '';
            if (v.indexOf('plugin') !== -1) kf.value = '';
        } else {
            kf.placeholder = 'digital, product, download';
        }
    }
}
// 页面加载时同步一次
(function(){ var s = document.getElementById('product-type'); if(s) syncPlaceholders(s.value); })();
</script>
@endsection
