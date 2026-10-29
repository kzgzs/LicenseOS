@extends('layouts.app')
@section('title', $product->name)
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
    
    // Get tenant and API key from the view data passed by controller
    $tenant = \App\Models\Tenant::find($product->tenant_id) ?? auth()->user()->tenant;
    $apiKey = $tenant?->apiKeys()->where('active', true)->first()?->key ?? '';
    $isFreeProduct = collect($product->pricing_tiers ?? [])->contains(fn($t) => ($t['type'] ?? '') === 'free');
@endphp
@section('content')
<a href="{{ route('developer.products.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">&larr; {{ __('dev_products.back') }}</a>

<div class="flex justify-between items-center mt-4 mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ $product->name }} <span class="text-sm text-gray-400">v{{ $product->version }}</span></h1>
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 text-sm rounded-full {{ $product->status === 'published' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 dark:text-gray-300' }}">{{ $product->status }}</span>
        <button type="button" onclick="document.getElementById('edit-form').classList.toggle('hidden')" class="px-3 py-1.5 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50">{{ __('dev_products.edit_product') }}</button>
    </div>
</div>

<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_products.base_price') }}</p>
        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $currencySymbol }}{{ $product->price }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_products.type') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ \App\Models\Product::TYPES[$product->type] ?? $product->type }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_products.licenses') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $licenses->total() }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_products.file_size') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $product->file_size_bytes ? number_format($product->file_size_bytes / 1048576, 1) . ' MB' : '-' }}</p>
    </div>
</div>

@if($product->description)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-2 dark:text-white">{{ __('dev_products.description') }}</h2>
    <p class="text-gray-700 dark:text-gray-300">{{ $product->description }}</p>
</div>
@endif

@php
    // Check injection status for WP plugins/themes
    $hasProtection = false;
    if (in_array($product->type, ['wp_plugin', 'wp_theme'])) {
        $latestPackage = \App\Models\UpdatePackage::where('product_id', $product->id)->latest('id')->first();
        // Check if product has injection (regardless of whether it was auto-injected)
        $hasProtection = $latestPackage !== null;
    }
@endphp

{{-- Show guide if license protection enabled but not injected --}}
@if(in_array($product->type, ['wp_plugin', 'wp_theme']) && $hasProtection)
    @php
        $isInjected = false;
        if ($latestPackage !== null) {
            $isInjected = !empty($latestPackage->injection_modules);
        }
    @endphp
    @if(!$isInjected)
<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
    <div class="flex items-start gap-3">
        <div class="text-2xl">⚠️</div>
        <div>
            <h3 class="font-semibold text-yellow-800 dark:text-yellow-300">授权保护待激活</h3>
            <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">
                您在创建此产品时启用了授权保护，但尚未进行一键注入。<br>
                请点击上方绿色 <strong>"一键注入"</strong> 按钮完成配置，然后下载安装到 WordPress 即可看到授权激活表单。
            </p>
        </div>
    </div>
</div>
@endif

{{-- Edit Product (hidden by default) --}}
<div id="edit-form" class="hidden bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('dev_products.edit_product') }}</h2>
    <form method="POST" action="{{ route('developer.products.update', $product) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.slug') }}</label>
                <input type="text" name="slug" id="edit-field-slug" value="{{ old('slug', $product->slug) }}" required class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
                <span id="edit-slug-check-msg" class="text-xs mt-1 block"></span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.base_price') }}</label>
                <input type="number" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" required class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.status') }}</label>
                <select name="status" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
                    <option value="draft" {{ $product->status === 'draft' ? 'selected' : '' }}>{{ __('dev_products.status_draft') }}</option>
                    <option value="published" {{ $product->status === 'published' ? 'selected' : '' }}>{{ __('dev_products.status_published') }}</option>
                    <option value="archived" {{ $product->status === 'archived' ? 'selected' : '' }}>{{ __('dev_products.status_archived') }}</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.category') }}</label>
                <select name="category_id" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
                    <option value="">{{ __('dev_products.none') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.short_description') }}</label>
                <input type="text" name="short_description" value="{{ old('short_description', $product->short_description) }}" maxlength="500" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" placeholder="{{ __('dev_products.short_description_ph') }}">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.description') }}</label>
            <textarea name="description" rows="4" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">{{ old('description', $product->description) }}</textarea>
        </div>

        {{-- Installation Instructions --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.installation_instructions') }}</label>
            <textarea name="installation" id="edit-installation" rows="6" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">{{ old('installation', $product->installation) }}</textarea>
            <p class="text-xs text-gray-400 mt-1">{{ __('dev_products.html_content_hint') }}</p>
        </div>

        {{-- FAQ --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.faq_title') }}</label>
            <textarea name="faq" id="edit-faq" rows="6" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">{{ old('faq', $product->faq) }}</textarea>
            <p class="text-xs text-gray-400 mt-1">{{ __('dev_products.html_content_hint') }}</p>
        </div>

        {{-- Re-parse readme from ZIP --}}
        @if(in_array($product->type, ['wp_plugin', 'wp_theme']))
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Readme.txt 解析') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('上传插件 ZIP 文件，自动提取 Description / Installation / FAQ / Changelog / Screenshots 并回写到产品数据。') }}</p>
            <div class="flex items-center gap-3">
                <input type="file" id="readme-zip-input" accept=".zip" class="border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700">
                <button type="button" id="reparse-readme-btn" class="px-4 py-2 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50 whitespace-nowrap">{{ __('重新解析 readme') }}</button>
            </div>
            <span id="readme-parse-status" class="text-xs mt-1 hidden block"></span>
        </div>
        @endif

        {{-- WP Metadata (WP types only) --}}
        @if(in_array($product->type, ['wp_plugin', 'wp_theme']))
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('dev_products.wp_metadata_title') }}</h2>
            <div class="grid grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.author') }}</label>
                    <input type="text" name="author" value="{{ old('author', $product->author) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.author_profile') }}</label>
                    <input type="url" name="author_profile" value="{{ old('author_profile', $product->author_profile) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.plugin_homepage') }}</label>
                    <input type="url" name="plugin_homepage" value="{{ old('plugin_homepage', $product->plugin_homepage) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.requires_wp') }}</label>
                    <input type="text" name="requires_wp" value="{{ old('requires_wp', $product->requires_wp) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.tested_up_to') }}</label>
                    <input type="text" name="tested_up_to" value="{{ old('tested_up_to', $product->tested_up_to) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.requires_php') }}</label>
                    <input type="text" name="requires_php" value="{{ old('requires_php', $product->requires_php) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.license') }}</label>
                    <input type="text" name="license" value="{{ old('license', $product->license) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="100" placeholder="GPL-2.0+">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.license_uri') }}</label>
                    <input type="url" name="license_uri" value="{{ old('license_uri', $product->license_uri) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="255" placeholder="http://www.gnu.org/licenses/gpl-2.0.html">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.text_domain') }}</label>
                    <input type="text" name="text_domain" value="{{ old('text_domain', $product->text_domain) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="100" placeholder="my-theme">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.wp_tags') }}</label>
                    <input type="text" name="wp_tags" value="{{ old('wp_tags', $product->wp_tags) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="500" placeholder="responsive, translation-ready, custom-header">
                </div>
            </div>
        </div>
        @endif

        {{-- Cover Image --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.cover_image') }}</label>
            @if($product->cover_image)
                <div class="mb-2">
                    <img src="{{ Storage::disk('public')->url($product->cover_image) }}" alt="Cover" class="h-20 rounded border dark:border-gray-600">
                </div>
            @endif
            <input type="file" name="cover_image" accept="image/*" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>

        {{-- Screenshots --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.screenshots') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('dev_products.screenshots_hint') }}</p>
            <div id="screenshots-preview" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3"></div>
            <input type="file" id="screenshot-upload" accept="image/*" multiple class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm file:mr-4 file:py-1 file:px-4 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700">
            <input type="hidden" name="screenshots" id="screenshots-input" value='@json($product->screenshots ?? [])'>
            <p id="screenshots-status" class="text-xs text-gray-400 mt-1 hidden"></p>
        </div>


        {{-- License Protection (WP types only) --}}
        @if(in_array($product->type, ['wp_plugin', 'wp_theme']))
        <div id="edit-license-protection-section" class="border-t dark:border-gray-700 pt-4 mt-4" style="{{ $isFreeProduct ? 'display:none' : '' }}">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                {{ __('授权保护由平台统一注入，请在下方“产品管理”区域进行一键注入或复原操作。') }}
            </div>
        </div>
        @endif

        {{-- Reviews Toggle --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="reviews_enabled" value="1" class="rounded" {{ $product->reviews_enabled ? 'checked' : '' }}>
                <div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('启用评价') }}</span>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('允许用户对该产品提交评价和评分。') }}</p>
                    @if(!\App\Models\SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true)))
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ __('注意：全局评价开关当前已关闭，即使产品开启评价，用户也无法提交。') }}</p>
                    @endif
                </div>
            </label>
        </div>

        {{-- SEO Section --}}
        <div class="border-t dark:border-gray-700 pt-4 mt-4">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('dev_products.seo_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('dev_products.seo_hint') }}</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.meta_title') }}</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" maxlength="255" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="{{ __('dev_products.meta_title_ph') }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.meta_description') }}</label>
                    <textarea name="meta_description" rows="2" maxlength="500" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="{{ __('dev_products.meta_description_ph') }}">{{ old('meta_description', $product->meta_description) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('dev_products.keywords') }}</label>
                    <input type="text" name="keywords" value="{{ old('keywords', is_array($product->keywords) ? implode(', ', $product->keywords) : ($product->keywords ?? '')) }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="plugin, wordpress, seo">
                </div>
            </div>
        </div>

        {{-- Featured --}}
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="featured" value="1" {{ $product->featured ? 'checked' : '' }} class="rounded">
            <span class="text-gray-700 dark:text-gray-300">{{ __('dev_products.featured') }}</span>
        </label>

        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('dev_products.save_changes') }}</button>
    </form>
</div>

{{-- Pricing Tiers Management --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">

    <div class="mb-4">
        <h2 class="text-lg font-semibold dark:text-white">{{ __('dev_products.pricing_tiers') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('dev_products.tiers_desc') }}</p>
    </div>

    {{-- Free product toggle --}}
    <div class="mb-4 p-4 border-2 border-dashed dark:border-gray-600 rounded-lg">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" id="is-free-product" {{ $isFreeProduct ? 'checked' : '' }} class="w-5 h-5 rounded text-green-600 focus:ring-green-500">
            <div>
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ __('dev_products.free_product') }}</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('dev_products.free_product_desc') }}</p>
            </div>
        </label>
    </div>

    {{-- Hidden free tier inputs (submitted when checkbox is checked) --}}
    <form method="POST" action="{{ route('developer.products.update-pricing', $product) }}" novalidate>
        @csrf
        <div id="free-tier-inputs" style="display:{{ $isFreeProduct ? 'block' : 'none' }}">
            <input type="hidden" name="pricing_tiers[0][enabled]" value="1">
            <input type="hidden" name="pricing_tiers[0][type]" value="free">
            <input type="hidden" name="pricing_tiers[0][price]" value="0">
            <input type="hidden" name="pricing_tiers[0][label]" value="{{ __('dev_tiers.free') }}">
            <input type="hidden" name="pricing_tiers[0][description]" value="{{ __('dev_products.free_description') }}">
            <div class="mt-4">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('dev_products.save_pricing_tiers') }}</button>
            </div>
        </div>
    </form>

    {{-- Tier editor section (hidden when free is checked) --}}
    <div id="tier-editor-section" style="display:{{ $isFreeProduct ? 'none' : '' }}">
        {{-- Current tiers preview --}}
        @php $existingTiers = $product->getEnabledTiers(); @endphp
        @if(!empty($existingTiers) && ($product->pricing_tiers))
        <div class="mb-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-300">{{ __('dev_products.status') }}</th>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-300">{{ __('dev_products.tier_label') }}</th>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-300">{{ __('dev_products.duration') }}</th>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-300">{{ __('dev_products.price') }}</th>
                        <th class="px-3 py-2 text-left text-gray-500 dark:text-gray-300">{{ __('dev_products.auto_renew') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($product->pricing_tiers as $t)
                    <tr class="border-t dark:border-gray-700">
                        <td class="px-3 py-2">
                            @if($t['enabled'] ?? false)
                                <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded-full">{{ __('dev_tiers.enabled') }}</span>
                            @else
                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 rounded-full">{{ __('dev_products.disabled') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-medium dark:text-gray-200">{{ $t['label'] ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                            @if(($t['type'] ?? '') === 'trial') {{ $t['days'] ?? 0 }} {{ __('dev_products.days_unit') }}
                            @elseif(($t['type'] ?? '') === 'free') {{ __('dev_tiers.free') }}
                            @elseif(($t['months'] ?? 0) == 0) {{ __('dev_products.lifetime') }}
                            @else {{ $t['months'] ?? 0 }} {{ __('dev_products.months_unit') }} @endif
                        </td>
                        <td class="px-3 py-2 font-medium dark:text-gray-200">
                            @if(($t['type'] ?? '') === 'free') {{ __('dev_tiers.free') }}
                            @else {{ $currencySymbol }}{{ number_format($t['price'] ?? 0, 2) }} @endif
                        </td>
                        <td class="px-3 py-2 text-gray-400 dark:text-gray-500">{{ ($t['auto_renew'] ?? false) ? __('dev_products.yes') : __('dev_products.no') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">{{ __('dev_products.no_tiers_msg') }}</p>
        @endif

        <div class="flex items-center justify-end mb-3">
            <button type="button" id="add-tier-btn" class="px-3 py-1.5 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+ {{ __('dev_products.add_tier') }}</button>
        </div>

        {{-- Edit form --}}
        <form method="POST" action="{{ route('developer.products.update-pricing', $product) }}" novalidate class="border-t dark:border-gray-700 pt-4 mt-4">
            @csrf
            <div id="tiers-container" class="space-y-3">
                {{-- Tier rows added via JS, pre-populated from existing data --}}
            </div>
            <div class="mt-4">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('dev_products.save_pricing_tiers') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Upload New Version --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('dev_products.upload_new_version') }}</h2>
    @if($latestVersion)
        <div class="mb-4 px-4 py-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg" id="latest-version-bar" data-latest="{{ $latestVersion }}">
            <p class="text-sm text-blue-700 dark:text-blue-300">
                <span class="font-medium">{{ __('当前最新版本：') }}</span>
                <span class="font-mono font-bold">{{ $latestVersion }}</span>
                <span class="text-xs ml-2">{{ __('（新版本号必须高于此版本）') }}</span>
            </p>
        </div>
    @endif
    <form method="POST" action="{{ route('developer.products.upload-version', $product) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <!-- Scan Status Display for Version Upload -->
        <div id="version-scan-status" class="hidden mt-3 rounded-lg border p-4" role="alert">
            <div class="flex items-start">
                <div id="version-scan-icon" class="flex-shrink-0"></div>
                <div class="ml-3 flex-1">
                    <h3 id="version-scan-title" class="text-sm font-medium"></h3>
                    <ul id="version-scan-issues" class="mt-2 ml-6 list-disc text-xs"></ul>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.zip_file_label') }}</label>
                <input type="file" name="file" id="version-file-input" accept=".zip" class="w-full border dark:border-gray-600 rounded-lg px-3 py-2 file:mr-4 file:py-1 file:px-4 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700" required>
                <span id="version-parse-status" class="text-xs text-gray-400 mt-1 hidden"></span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.version_number') }}</label>
                <input type="text" name="version" id="version-input" value="{{ old('version') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 @error('version') border-red-500 @enderror" required placeholder="1.0.1">
                <p id="version-error" class="text-xs text-red-500 mt-1 hidden"></p>
                @error('version')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
                @if(!$latestVersion)
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('dev_products.version_hint', ['version' => $product->version]) }}</p>
                @endif
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('dev_products.changelog') }}</label>
            <textarea name="changelog" rows="3" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" placeholder="{{ __('dev_products.changelog_ph') }}">{{ old('changelog') }}</textarea>
        </div>
        <button type="submit" id="upload-version-btn" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('dev_products.upload_version') }}</button>
    </form>
</div>

{{-- Inject Status Badge (WP types only) --}}
@if(in_array($product->type, ['wp_plugin', 'wp_theme']) && $latestPackage)
<div class="mb-4">
    @php
        $isInjected = !empty($latestPackage->injection_modules);
    @endphp
    @if($isInjected)
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
        ✓ {{ __('dev_products.inject_status_enabled') }}
    </span>
    @elseif(!$isInjected)
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
        {{ __('developer.injection_pending') }}
    </span>
    @endif
</div>

<div id="init-code-template" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"> {{ __('授权代码模板') }} </h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        {{ __('以下代码已由平台一键注入系统自动处理。如需手动修改，请在主文件中查找注释') }}  
        <code class="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">=== LicenseOS Initialization Code ===</code>
    </p>
    
    @if($apiKey)
    <div class="mb-4">
        <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2"> {{ __('API Key') }} </h4>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $apiKey }}" class="flex-1 border bg-gray-50 dark:bg-gray-700 dark:text-gray-300 px-3 py-2 text-sm rounded" id="api-key-field">
            <button type="button" onclick="copyApiKey()" class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm flex-shrink-0"> {{ __('复制') }} </button>
        </div>
    </div>
    @endif
    
    <div class="mb-4">
        <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2"> {{ __('选项名称建议') }} </h4>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <div><strong> {{ __('密钥存储选项') }} :</strong> {{ \Illuminate\Support\Str::snake($product->name) }}_license_key</div>
            <div><strong> {{ __('状态存储选项') }} :</strong> {{ \Illuminate\Support\Str::snake($product->name) }}_license_status</div>
            <div><strong> {{ __('菜单父级 slug') }} :</strong> {{ \Illuminate\Support\Str::slug($product->slug . '-settings') }}</div>
        </div>
    </div>
    
    <div>
        <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2"> {{ __('激活/停用函数参考（需在设置页调用）') }} </h4>
        <pre class="bg-gray-100 dark:bg-gray-700 p-4 rounded text-xs overflow-auto max-h-64" style="white-space: pre-wrap;">{{ view('partials.activation-functions', ['product' => $product, 'apiKey' => $apiKey])->render() }}</pre>
    </div>
</div>
<div id="inject-management-section" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6" style="{{ $isFreeProduct ? 'display:none' : '' }}">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
        {{ __('developer.license_protection_mgmt') }}
    </h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        {{ __('developer.license_protection_mgmt_desc') }}
    </p>

    <form method="POST" action="{{ route('developer.products.action', $product) }}" id="inject-form">
        @csrf
        <input type="hidden" name="action" value="inject">
        <div class="space-y-3 mb-4">
            <label class="flex items-start space-x-3 cursor-pointer">
                <input type="checkbox" name="modules[]" value="integrity_guard" checked class="mt-1 rounded border-gray-300 dark:border-gray-600">
                <div>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('developer.module_integrity_guard') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 block">{{ __('developer.module_integrity_guard_desc') }}</span>
                </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
                <input type="checkbox" name="modules[]" value="secondary_check" checked class="mt-1 rounded border-gray-300 dark:border-gray-600">
                <div>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('developer.module_secondary_check') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 block">{{ __('developer.module_secondary_check_desc') }}</span>
                </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
                <input type="checkbox" name="modules[]" value="license_gate" checked class="mt-1 rounded border-gray-300 dark:border-gray-600">
                <div>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('developer.module_license_gate') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 block">{{ __('developer.module_license_gate_desc') }}</span>
                </div>
            </label>
            <label class="flex items-start space-x-3 cursor-pointer">
                <input type="checkbox" name="modules[]" value="auto_guardian" checked class="mt-1 rounded border-gray-300 dark:border-gray-600">
                <div>
                    <span class="font-medium text-gray-900 dark:text-white">{{ __('developer.module_auto_guardian') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 block">{{ __('developer.module_auto_guardian_desc') }}</span>
                </div>
            </label>
        </div>

        <div class="flex items-center space-x-4">
            <button type="button" onclick="document.querySelectorAll('#inject-form input[name=\'modules[]\']').forEach(c=>c.checked=true)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">{{ __('developer.select_all') }}</button>
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <button type="button" onclick="document.querySelectorAll('#inject-form input[name=\'modules[]\']').forEach(c=>c.checked=false)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">{{ __('developer.deselect_all') }}</button>
            <div class="flex-1"></div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium">
                {{ __('developer.inject_now') }}
            </button>
        </div>
    </form>

    <div class="border-t border-gray-200 dark:border-gray-700 mt-4 pt-4 flex items-center justify-between">
        <div>
            @if($hasProtection && $latestPackage && !empty($latestPackage->injection_modules))
                    @php
                        $modulesArray = is_string($latestPackage->injection_modules) ? json_decode($latestPackage->injection_modules, true) : $latestPackage->injection_modules;
                        $moduleNames = [];
                        if (is_array($modulesArray)) {
                            foreach ($modulesArray as $module) {
                                switch ($module) {
                                    case 'sdk':
                                        $moduleNames[] = __('developer.module_sdk');
                                        break;
                                    case 'integrity_guard':
                                        $moduleNames[] = __('developer.module_integrity_guard');
                                        break;
                                    case 'license_gate':
                                        $moduleNames[] = __('developer.module_license_gate');
                                        break;
                                    case 'secondary_cross_check':
                                        $moduleNames[] = __('developer.module_secondary_cross_check');
                                        break;
                                    case 'self_guard':
                                        $moduleNames[] = __('developer.module_self_guard');
                                        break;
                                    default:
                                        $moduleNames[] = $module;
                                }
                            }
                        }
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                        {{ __('developer.injected') }}: {{ implode(', ', $moduleNames) }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                        {{ __('developer.injection_pending') }}
                    </span>
                @endif
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    {{ __('developer.not_injected') }}
                </span>
            @endif
        </div>

        <form method="POST" action="{{ route('developer.products.action', $product) }}">
            @csrf
            <input type="hidden" name="action" value="uninject">
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium"
                    onclick="return confirm('{{ __('developer.uninject_confirm') }}')">
                {{ __('developer.uninject_now') }}
            </button>
        </form>
    </div>
</div>
@endif

{{-- Version History --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
    <div class="p-4 border-b dark:border-gray-700"><h2 class="text-lg font-semibold dark:text-white">{{ __('dev_products.version_history') }}</h2></div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.version') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.changelog') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.uploaded') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $v)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 font-mono text-sm dark:text-green-400">
                    v{{ $v->version }}
                    @if($v->version === $product->version)
                        <span class="ml-1 px-1.5 py-0.5 text-xs bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded">{{ __('dev_products.latest') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $v->changelog ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">{{ $v->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('dev_products.no_versions') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- License List --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700"><h2 class="text-lg font-semibold dark:text-white">{{ __('dev_products.licenses') }}</h2></div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.license_key') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.domains') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.expires') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($licenses as $l)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 font-mono text-sm dark:text-green-400">{{ $l->license_key }}</td>
                <td class="px-4 py-3">
                    @php
                        $statusColor = match($l->status) {
                            'active' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                            'trial' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                            'expired' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        };
                    @endphp
                    <span class="px-2 py-1 text-xs rounded-full {{ $statusColor }}">{{ $l->status }}</span>
                    @if($l->is_trial) <span class="ml-1 px-1.5 py-0.5 text-xs bg-yellow-50 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400 rounded">{{ __('dev_products.trial_badge') }}</span> @endif
                </td>
                <td class="px-4 py-3 text-sm">
                    @if($l->activations->count() > 0)
                        @foreach($l->activations as $a)
                            <span class="inline-block px-2 py-0.5 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 rounded text-xs mr-1">{{ $a->domain }}</span>
                        @endforeach
                    @else
                        <span class="text-gray-400 dark:text-gray-500">{{ $l->activations->count() }}/{{ $l->max_domains }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">{{ $l->expire_at ? $l->expire_at->format('Y-m-d') : __('dev_products.lifetime') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('dev_products.no_licenses') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
(function() {
    var TIER_TYPES = {
        'trial':    '{{ __('dev_tiers.trial') }}',
        '1_month':  '{{ __('dev_tiers.1_month') }}',
        '3_months': '{{ __('dev_tiers.3_months') }}',
        '6_months': '{{ __('dev_tiers.6_months') }}',
        '1_year':   '{{ __('dev_tiers.1_year') }}',
        'lifetime': '{{ __('dev_tiers.lifetime') }}'
    };
    var T = {
        enabled:    '{{ __('dev_tiers.enabled') }}',
        remove:     '{{ __('dev_tiers.remove') }}',
        type:       '{{ __('dev_tiers.type') }}',
        select:     '{{ __('dev_tiers.select') }}',
        days:       '{{ __('dev_tiers.days') }}',
        months:     '{{ __('dev_tiers.months') }}',
        price:      '{{ __('dev_tiers.price') }}',
        label:      '{{ __('dev_tiers.label') }}',
        desc:       '{{ __('dev_tiers.desc') }}',
        auto_renew: '{{ __('dev_tiers.auto_renew') }}'
    };
    var CURRENCY = '{{ $currencySymbol }}';
    var tierIndex = 0;
    var container = document.getElementById('tiers-container');

    function addTier(type, enabled, price, label, description, days, months, autoRenew) {
        type = type || ''; enabled = enabled !== false && enabled !== undefined;
        price = price !== undefined && price !== null ? price : '';
        label = label || ''; description = description || '';
        days = days || '7'; months = months !== undefined && months !== null ? String(months) : '1';
        autoRenew = autoRenew || false;

        var idx = tierIndex++;
        var div = document.createElement('div');
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
                    '<input type="text" name="pricing_tiers[' + idx + '][label]" value="' + label + '" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm">' +
                '</div>' +
                '<div>' +
                    '<label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">' + T.desc + '</label>' +
                    '<input type="text" name="pricing_tiers[' + idx + '][description]" value="' + description + '" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm">' +
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
    var licenseSection = document.getElementById('edit-license-protection-section');
    var injectSection = document.getElementById('inject-management-section');

    freeCheckbox.addEventListener('change', function() {
        if (this.checked) {
            tierSection.style.display = 'none';
            freeInputs.style.display = 'block';
            tierSection.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = true; });
            if (licenseSection) licenseSection.style.display = 'none';
            if (injectSection) injectSection.style.display = 'none';
        } else {
            tierSection.style.display = '';
            freeInputs.style.display = 'none';
            tierSection.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = false; });
            if (licenseSection) licenseSection.style.display = '';
            if (injectSection) injectSection.style.display = '';
        }
    });

    // Pre-populate from existing pricing_tiers
    @if($product->pricing_tiers)
        @foreach($product->pricing_tiers as $tier)
            @if(($tier['type'] ?? '') !== 'free')
            addTier(
                '{{ $tier['type'] ?? '' }}',
                {{ ($tier['enabled'] ?? false) ? 'true' : 'false' }},
                '{{ $tier['price'] ?? 0 }}',
                '{{ addslashes($tier['label'] ?? '') }}',
                '{{ addslashes($tier['description'] ?? '') }}',
                '{{ $tier['days'] ?? 7 }}',
                '{{ $tier['months'] ?? 0 }}',
                {{ ($tier['auto_renew'] ?? false) ? 'true' : 'false' }}
            );
            @endif
        @endforeach
    @endif

    // Disable tier editor inputs on load if product is free
    @if($isFreeProduct)
    (function() {
        var ts = document.getElementById('tier-editor-section');
        if (ts) ts.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = true; });
    })();
    @endif

    // === Slug uniqueness check on blur (edit form) ===
    var editSlugInput = document.getElementById('edit-field-slug');
    var editSlugMsg = document.getElementById('edit-slug-check-msg');
    var slugCheckUrl = '{{ route('developer.check-slug') }}';
    var csrfToken = '{{ csrf_token() }}';
    var currentProductId = {{ $product->id }};
    var SLUG_CHECKING = '{{ __('dev_slug.checking') }}';
    var SLUG_AVAILABLE = '{{ __('dev_slug.available') }}';
    var SLUG_TAKEN = '{{ __('dev_slug.taken') }}';
    if (editSlugInput) {
        editSlugInput.addEventListener('blur', function() {
            var slug = this.value.trim();
            if (!slug) { editSlugMsg.textContent = ''; return; }
            editSlugMsg.textContent = SLUG_CHECKING;
            editSlugMsg.className = 'text-xs mt-1 block text-gray-500 dark:text-gray-400';
            fetch(slugCheckUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({slug: slug, exclude_id: currentProductId})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.exists) {
                    editSlugMsg.textContent = SLUG_TAKEN;
                    editSlugMsg.className = 'text-xs mt-1 block text-red-600 dark:text-red-400';
                } else {
                    editSlugMsg.textContent = SLUG_AVAILABLE;
                    editSlugMsg.className = 'text-xs mt-1 block text-green-600 dark:text-green-400';
                }
            })
            .catch(function() {
                editSlugMsg.textContent = '';
            });
        });
    }
})();

// === 版本号比较工具函数 ===
function compareVersions(a, b) {
    var pa = a.split('.').map(Number);
    var pb = b.split('.').map(Number);
    var len = Math.max(pa.length, pb.length);
    for (var i = 0; i < len; i++) {
        var na = pa[i] || 0, nb = pb[i] || 0;
        if (na > nb) return 1;
        if (na < nb) return -1;
    }
    return 0;
}

// === Version ZIP auto-parse: fill installation/faq in edit form ===
(function() {
    var fileInput = document.getElementById('version-file-input');
    var statusEl = document.getElementById('version-parse-status');
    if (!fileInput) return;

    var parseUrl = '{{ route('developer.products.parse-zip') }}';
    var scanUrl = '{{ route('developer.products.scan-zip') }}';

    // === Scan status management for version upload ===
    function showVersionScanStatus(type, message, issues, warnings) {
        var statusDiv = document.getElementById('version-scan-status');
        var iconDiv = document.getElementById('version-scan-icon');
        var titleEl = document.getElementById('version-scan-title');
        var issuesUl = document.getElementById('version-scan-issues');
        
        // Clear previous content
        issuesUl.innerHTML = '';
        
        // Set styles based on type
        switch (type) {
            case 'scanning':
                statusDiv.className = 'hidden mt-3 rounded-lg border p-4 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800';
                iconDiv.innerHTML = '<svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                titleEl.textContent = '正在扫描文件安全性...';
                titleEl.className = 'text-sm font-medium text-blue-800 dark:text-blue-300';
                break;
                
            case 'error':
                statusDiv.className = 'hidden mt-3 rounded-lg border p-4 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
                iconDiv.innerHTML = '<svg class="h-5 w-5 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
                titleEl.textContent = '✗ ' + message;
                titleEl.className = 'text-sm font-medium text-red-800 dark:text-red-300';
                
                // Add issues to list
                if (issues && issues.length > 0) {
                    issues.forEach(function(issue) {
                        var li = document.createElement('li');
                        li.textContent = issue;
                        issuesUl.appendChild(li);
                    });
                }
                break;
                
            case 'success':
                statusDiv.className = 'hidden mt-3 rounded-lg border p-4 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800';
                iconDiv.innerHTML = '<svg class="h-5 w-5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
                titleEl.textContent = '✓ 文件扫描通过';
                titleEl.className = 'text-sm font-medium text-green-800 dark:text-green-300';
                break;
        }
        
        // Show/hide div
        statusDiv.classList.toggle('hidden', type !== 'scanning' && type !== 'success' && type !== 'error');
        
        // Auto-hide after 3 seconds for success
        if (type === 'success') {
            setTimeout(function() {
                statusDiv.classList.add('hidden');
            }, 3000);
        }
    }

    fileInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) { statusEl.classList.add('hidden'); return; }

        // First perform security scan
        statusEl.classList.remove('hidden');
        statusEl.className = 'text-xs text-yellow-600 dark:text-yellow-400 mt-1';
        statusEl.textContent = 'Parsing ZIP...';

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        // Perform security scan first
        fetch(scanUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }})
            .then(function(r) { return r.json(); })
            .then(function(scanResult) {
                if (!scanResult.status || !scanResult.safe) {
                    // Security check failed
                    showVersionScanStatus('error', scanResult.message || '扫描失败', scanResult.issues || [], scanResult.warnings || []);
                    
                    // Disable submit button
                    var submitBtn = document.getElementById('upload-version-btn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                        submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    }
                    return;
                }
                
                // Scan passed, show success
                showVersionScanStatus('success', scanResult.message || '文件安全', [], scanResult.warnings || []);
                
                // Re-enable submit button
                var submitBtn = document.getElementById('upload-version-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                }
                
                // Then proceed with metadata parsing
                statusEl.textContent = 'Parsing ZIP...';
                return fetch(parseUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); });
            })
            .then(function(data) {
                if (data.success && data.metadata) {
                    var m = data.metadata;
                    // Auto-fill version number
                    var versionField = document.getElementById('version-input');
                    if (versionField && m.version) {
                        versionField.value = m.version;
                        // 校验版本号是否高于当前最新版本
                        var latestBar = document.getElementById('latest-version-bar');
                        var versionError = document.getElementById('version-error');
                        var submitBtn = document.getElementById('upload-version-btn');
                        if (latestBar && m.version) {
                            var latest = latestBar.getAttribute('data-latest');
                            if (latest && compareVersions(m.version, latest) <= 0) {
                                var I18N_version_error = '{{ __('新版本号必须高于当前版本，请重新填写。') }}';
                                versionError.textContent = I18N_version_error.replace('。', ' ' + latest + '，{{ __('请上传更高版本的 ZIP 包') }}');
                                versionError.classList.remove('hidden');
                                versionField.classList.add('border-red-500');
                                if (submitBtn) {
                                    submitBtn.disabled = true;
                                    submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                                    submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                                }
                            } else {
                                versionError.classList.add('hidden');
                                versionError.textContent = '';
                                versionField.classList.remove('border-red-500');
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                                    submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                                }
                            }
                        }
                    }
                    // Auto-fill installation if currently empty
                    var installField = document.getElementById('edit-installation');
                    if (installField && !installField.value && m.installation) {
                        installField.value = m.installation;
                    }
                    // Auto-fill faq if currently empty
                    var faqField = document.getElementById('edit-faq');
                    if (faqField && !faqField.value && m.faq) {
                        faqField.value = m.faq;
                    }
                    // 状态反馈
                    var statusParts = [];
                    if (m.version) statusParts.push('版本号: ' + m.version);
                    if (m.readme_found === false) {
                        statusParts.push('readme.txt 未找到');
                    } else {
                        var missingFields = [];
                        if (!m.installation) missingFields.push('Installation');
                        if (!m.faq) missingFields.push('FAQ');
                        if (missingFields.length > 0) {
                            statusParts.push('Readme 缺少: ' + missingFields.join(', '));
                        }
                    }
                    statusEl.className = 'text-xs ' + (statusParts.length > 1 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400') + ' mt-1';
                    statusEl.textContent = statusParts.length > 0 ? statusParts.join(' | ') : '解析成功';
                } else {
                    statusEl.className = 'text-xs text-red-600 dark:text-red-400 mt-1';
                    statusEl.textContent = 'Parse failed' + (data.error ? ': ' + data.error : '');
                }
            })
            .catch(function() {
                statusEl.className = 'text-xs text-red-600 dark:text-red-400 mt-1';
                statusEl.textContent = 'Parse failed';
            });
    });
})();

// === Reparse readme from ZIP (edit form button) ===
(function() {
    var zipInput = document.getElementById('readme-zip-input');
    var btn = document.getElementById('reparse-readme-btn');
    var statusEl = document.getElementById('readme-parse-status');
    if (!btn || !zipInput) return;

    var parseUrl = '{{ route('developer.products.parse-readme', $product) }}';
    var productType = '{{ $product->type }}';

    btn.addEventListener('click', function() {
        var file = zipInput.files[0];
        if (!file) {
            statusEl.classList.remove('hidden');
            statusEl.className = 'text-xs text-red-600 dark:text-red-400 mt-1 block';
            statusEl.textContent = '请先选择 ZIP 文件';
            return;
        }

        btn.disabled = true;
        btn.textContent = '解析中...';
        statusEl.classList.remove('hidden');
        statusEl.className = 'text-xs text-yellow-600 dark:text-yellow-400 mt-1 block';
        statusEl.textContent = '正在解析 readme.txt...';

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch(parseUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.sections) {
                    var s = data.sections;
                    // 回写 description textarea
                    var descField = document.querySelector('textarea[name="description"]');
                    if (descField && s.description) descField.value = s.description;
                    // 回写 installation textarea（仅当为空）
                    var installField = document.getElementById('edit-installation');
                    if (installField && !installField.value && s.installation) installField.value = s.installation;
                    // 回写 faq textarea（仅当为空）
                    var faqField = document.getElementById('edit-faq');
                    if (faqField && !faqField.value && s.faq) faqField.value = s.faq;

                    // 显示成功状态 + 已更新字段列表
                    var updatedList = (data.updated || []).join(', ');
                    var missingSections = [];
                    // wp_theme readme typically has no Installation/FAQ sections — skip those checks
                    if (productType !== 'wp_theme') {
                        if (!s.installation) missingSections.push('Installation');
                        if (!s.faq) missingSections.push('FAQ');
                    }
                    var statusText = '解析成功！已更新字段：' + (updatedList || '无') + '。';
                    if (missingSections.length > 0) {
                        statusText += ' 未找到：' + missingSections.join(', ') + '。';
                        statusEl.className = 'text-xs text-yellow-600 dark:text-yellow-400 mt-1 block';
                    } else {
                        statusEl.className = 'text-xs text-green-600 dark:text-green-400 mt-1 block';
                    }
                    statusText += '点击“Save Changes”提交表单。';
                    statusEl.textContent = statusText;
                } else {
                    statusEl.className = 'text-xs text-red-600 dark:text-red-400 mt-1 block';
                    statusEl.textContent = '解析失败' + (data.error ? '：' + data.error : '');
                }
            })
            .catch(function() {
                statusEl.className = 'text-xs text-red-600 dark:text-red-400 mt-1 block';
                statusEl.textContent = '解析失败，请重试';
            })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = '重新解析 readme';
            });
    });
})();

// === Screenshots Gallery Management ===
(function() {
    var preview = document.getElementById('screenshots-preview');
    var uploadInput = document.getElementById('screenshot-upload');
    var hiddenInput = document.getElementById('screenshots-input');
    var statusEl = document.getElementById('screenshots-status');
    var uploadUrl = '{{ route('developer.products.upload-screenshot', $product) }}';
    var csrfToken = '{{ csrf_token() }}';
    var MAX_SCREENSHOTS = 8;
    var T = {
        remove: '{{ __('dev_products.ss_remove') }}',
        caption_placeholder: '{{ __('dev_products.ss_caption_placeholder') }}',
        deleted: '{{ __('dev_products.ss_deleted') }}',
        max_reached: '{{ __('dev_products.ss_max_reached') }}',
        limit_hit: '{{ __('dev_products.ss_limit_hit') }}',
        uploading: '{{ __('dev_products.ss_uploading') }}',
        upload_done: '{{ __('dev_products.ss_upload_done') }}',
        upload_failed: '{{ __('dev_products.ss_upload_failed') }}',
    };

    function getScreenshots() {
        try { return JSON.parse(hiddenInput.value || '[]'); }
        catch(e) { return []; }
    }

    function syncHidden() {
        hiddenInput.value = JSON.stringify(getScreenshots());
    }

    function setStatus(msg, isError) {
        statusEl.classList.remove('hidden');
        statusEl.className = 'text-xs mt-1 ' + (isError ? 'text-red-500' : 'text-gray-500 dark:text-gray-400');
        statusEl.textContent = msg;
    }

    function render() {
        var items = getScreenshots();
        preview.innerHTML = '';
        items.forEach(function(item, idx) {
            var card = document.createElement('div');
            card.className = 'relative group rounded-lg overflow-hidden border dark:border-gray-600 bg-gray-50 dark:bg-gray-700';
            card.innerHTML =
                '<img src="' + item.url + '" alt="" class="w-full h-28 object-cover">' +
                '<div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">' +
                    '<button type="button" data-idx="' + idx + '" class="ss-remove-btn px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600">&times; ' + T.remove + '</button>' +
                '</div>' +
                '<input type="text" data-idx="' + idx + '" value="' + (item.caption || '').replace(/"/g, '&quot;') + '" placeholder="' + T.caption_placeholder + '" class="ss-caption w-full text-xs px-2 py-1 border-t dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 bg-white">';
            preview.appendChild(card);
        });

        // Bind remove buttons
        preview.querySelectorAll('.ss-remove-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var items2 = getScreenshots();
                items2.splice(parseInt(this.getAttribute('data-idx')), 1);
                hiddenInput.value = JSON.stringify(items2);
                render();
                setStatus(T.deleted, false);
            });
        });

        // Bind caption inputs
        preview.querySelectorAll('.ss-caption').forEach(function(inp) {
            inp.addEventListener('input', function() {
                var items2 = getScreenshots();
                var i = parseInt(this.getAttribute('data-idx'));
                if (items2[i]) {
                    items2[i].caption = this.value;
                    hiddenInput.value = JSON.stringify(items2);
                }
            });
        });
    }

    if (uploadInput) {
        uploadInput.addEventListener('change', function() {
            var files = Array.from(this.files || []);
            if (!files.length) return;

            var current = getScreenshots();
            var remaining = MAX_SCREENSHOTS - current.length;
            if (remaining <= 0) {
                setStatus(T.max_reached, true);
                this.value = '';
                return;
            }
            if (files.length > remaining) {
                files = files.slice(0, remaining);
                setStatus(T.limit_hit.replace(':count', remaining), false);
            }

            setStatus(T.uploading.replace(':count', files.length), false);

            var uploaded = 0;
            var failed = 0;
            files.forEach(function(file) {
                var fd = new FormData();
                fd.append('file', file);
                fd.append('_token', csrfToken);

                fetch(uploadUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.url) {
                        var items = getScreenshots();
                        items.push({ url: data.url, caption: '' });
                        hiddenInput.value = JSON.stringify(items);
                        render();
                        uploaded++;
                    } else {
                        failed++;
                    }
                })
                .catch(function() { failed++; })
                .finally(function() {
                    if (uploaded + failed === files.length) {
                        var msg = T.upload_done.replace(':count', uploaded);
                        if (failed > 0) msg += ', ' + T.upload_failed.replace(':count', failed);
                        setStatus(msg, failed > 0);
                    }
                });
            });

            uploadInput.value = '';
        });
    }

    // Initial render from existing data
    render();
})();

// === Copy API Key Function ===
function copyApiKey() {
    const field = document.getElementById('api-key-field');
    if (!field || !field.value) return;
    
    navigator.clipboard.writeText(field.value).then(() => {
        // Show success feedback
        const btn = field.nextElementSibling;
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.innerHTML = "{{ __('复制成功') }}";
        btn.classList.replace('bg-indigo-600', 'bg-green-600');
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('bg-green-600', 'bg-indigo-600');
        }, 1500);
    }).catch(err => {
        console.error('Copy failed:', err);
        alert("{{ __('复制失败，请手动复制') }}");
    });
}
</script>
@endsection
