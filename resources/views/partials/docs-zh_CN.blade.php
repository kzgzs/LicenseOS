<h1 class="text-3xl font-bold mb-2">{{ __('docs.page_title') }}</h1>
<p class="text-gray-500 mb-8">{{ __('docs.page_desc') }}</p>

{{-- Introduction --}}
<section id="introduction" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_intro') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="text-gray-700 mb-4">{!! __('docs.intro_text') !!}</p>
        <div class="bg-indigo-50 rounded-lg p-4 text-sm">
            <p class="font-medium text-indigo-700 mb-1">Base URL</p>
            <code class="text-indigo-600">https://your-domain.com/api/v2</code>
        </div>
        <h3 class="font-semibold mt-6 mb-2">{{ __('docs.quick_start') }}</h3>
        <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
            <li>{{ __('docs.qs_1') }}</li>
            <li>{!! __('docs.qs_2') !!}</li>
            <li>{!! __('docs.qs_3') !!}</li>
            <li>{{ __('docs.qs_4') }}</li>
        </ol>
    </div>
</section>

{{-- Authentication --}}
<section id="authentication" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_auth') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="text-gray-700 mb-4">{!! __('docs.auth_text') !!}</p>
        <div class="code-block mb-4">
<pre><span class="f">curl</span> https://your-domain.com/api/v2/license/check \
  -H <span class="s">"X-API-KEY: sk_live_xxxxxxxxxxxxxxxx"</span> \
  -d <span class="s">"license_key=XXXX-XXXX-XXXX-XXXX"</span></pre>
        </div>
        <table class="w-full params">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Header</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">X-API-KEY</td><td class="px-3 py-2">{{ __('docs.auth_apikey') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">Content-Type</td><td class="px-3 py-2"><code class="bg-gray-100 px-1 rounded text-xs">application/x-www-form-urlencoded</code> (POST)</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- License: Activate --}}
<section id="license-activate" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_activate') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="mb-4"><span class="method method-post">POST</span> <span class="endpoint">/api/v2/license/activate</span></p>
        <p class="text-sm text-gray-600 mb-4">{{ __('docs.activate_desc') }}</p>
        <h3 class="font-semibold text-sm mb-2">{{ __('docs.req_params') }}</h3>
        <table class="w-full params mb-6">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Parameter</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">{{ __('docs.activate_p_license') }}</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">license_key</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.activate_p_desc') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">domain</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.activate_p_domain') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">product</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.activate_p_product') }}</td></tr>
            </tbody>
        </table>
        <h3 class="font-semibold text-sm mb-2">{{ __('docs.res_success') }}</h3>
        <div class="code-block mb-4">
<pre>{
  <span class="s">"status"</span>: <span class="s">"active"</span>,
  <span class="s">"message"</span>: <span class="s">"License activated successfully."</span>,
  <span class="s">"max_domains"</span>: 1,
  <span class="s">"expire_at"</span>: <span class="s">"2025-12-31T23:59:59+00:00"</span>,
  <span class="s">"is_trial"</span>: <span class="k">false</span>
}</pre>
        </div>
    </div>
</section>

{{-- License: Check --}}
<section id="license-check" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_check') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="mb-4"><span class="method method-post">POST</span> <span class="endpoint">/api/v2/license/check</span></p>
        <p class="text-sm text-gray-600 mb-4">{{ __('docs.check_desc') }}</p>
        <h3 class="font-semibold text-sm mb-2">{{ __('docs.res') }}</h3>
        <div class="code-block mb-4">
<pre>{
  <span class="s">"status"</span>: <span class="s">"valid"</span>,
  <span class="s">"expire_at"</span>: <span class="s">"2025-12-31T23:59:59+00:00"</span>,
  <span class="s">"max_domains"</span>: 1,
  <span class="s">"activations"</span>: 1,
  <span class="s">"is_trial"</span>: <span class="k">false</span>
}</pre>
        </div>
        <h3 class="font-semibold text-sm mb-2">{{ __('docs.status_values') }}</h3>
        <table class="w-full params mb-6">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">valid</span></td><td class="px-3 py-2">{{ __('docs.status_valid') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2"><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">expired</span></td><td class="px-3 py-2">{{ __('docs.status_expired') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2"><span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs">inactive</span></td><td class="px-3 py-2">{{ __('docs.status_inactive') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2"><span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">invalid</span></td><td class="px-3 py-2">{{ __('docs.status_invalid') }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- Update: Check --}}
<section id="update-check" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_update_check') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="mb-4"><span class="method method-get">GET</span> <span class="endpoint">/api/v2/update/check</span></p>
        <p class="text-sm text-gray-600 mb-4">{{ __('docs.update_check_desc') }}</p>
        <h3 class="font-semibold text-sm mb-2">{{ __('docs.query_params') }}</h3>
        <table class="w-full params mb-6">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Parameter</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">product</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.uc_product') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">version</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.uc_version') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">license_key</td><td class="px-3 py-2">string</td><td class="px-3 py-2">{{ __('docs.uc_license') }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- Update: Download --}}
<section id="update-download" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_update_download') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="mb-4"><span class="method method-get">GET</span> <span class="endpoint">/api/v2/download</span></p>
        <p class="text-sm text-gray-600 mb-4">{{ __('docs.download_desc') }}</p>
        <p class="text-sm text-gray-600">{{ __('docs.download_success') }} <code class="bg-gray-100 px-1 rounded">application/zip</code></p>
        <p class="text-sm text-gray-600">{{ __('docs.download_fail') }} <code class="bg-gray-100 px-1 rounded">403</code></p>
    </div>
</section>

{{-- SDK: WordPress --}}
<section id="sdk-wordpress" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_sdk_wp') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <p class="text-sm text-gray-600 mb-4">{{ __('docs.sdk_intro') }}</p>
        <h3 class="font-semibold mb-3">{{ __('docs.sdk_install') }}</h3>
        <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700 mb-6">
            <li>{!! __('docs.sdk_i1') !!}</li>
            <li>{{ __('docs.sdk_i2') }}</li>
            <li>{{ __('docs.sdk_i3') }}</li>
        </ol>
        <h3 class="font-semibold mb-3">{{ __('docs.sdk_setup') }}</h3>
        <div class="code-block mb-6">
<pre><span class="c">// Load the SDK</span>
<span class="k">require_once</span> __DIR__ . <span class="s">'/licenseos-sdk.php'</span>;

<span class="c">// Initialize (call once, early in plugin lifecycle)</span>
LicenseOS::<span class="f">init</span>(
    <span class="s">'https://your-domain.com/api/v2'</span>,
    <span class="s">'sk_live_xxxxxxxx'</span>
);

<span class="c">// Enable WordPress auto-update</span>
LicenseOS::<span class="f">enableAutoUpdate</span>(
    <span class="s">'my-plugin-slug'</span>,
    <span class="f">get_option</span>(<span class="s">'my_plugin_license'</span>)
);</pre>
        </div>
        <h3 class="font-semibold mb-3">{{ __('docs.sdk_methods') }}</h3>
        <table class="w-full params mb-6">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Method</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">init($url, $key)</td><td class="px-3 py-2">{{ __('docs.m_init') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">activate($key, $domain, $product)</td><td class="px-3 py-2">{{ __('docs.m_activate') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">check($key)</td><td class="px-3 py-2">{{ __('docs.m_check') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">checkUpdate($slug, $version)</td><td class="px-3 py-2">{{ __('docs.m_checkUpdate') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono text-xs">enableAutoUpdate($slug, $key)</td><td class="px-3 py-2">{{ __('docs.m_enableAutoUpdate') }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- Errors & Rate Limits --}}
<section id="errors" class="doc-section mb-12">
    <h2 class="text-xl font-bold mb-4">{{ __('docs.nav_errors') }}</h2>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="font-semibold mb-3">HTTP Status Codes</h3>
        <table class="w-full params mb-6">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Code</th><th class="px-3 py-2 text-left">{{ __('faq.desc') }}</th></tr></thead>
            <tbody>
                <tr class="border-t"><td class="px-3 py-2 font-mono">200</td><td class="px-3 py-2">{{ __('docs.http_200') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">400</td><td class="px-3 py-2">{{ __('docs.http_400') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">401</td><td class="px-3 py-2">{{ __('docs.http_401') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">403</td><td class="px-3 py-2">{{ __('docs.http_403') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">404</td><td class="px-3 py-2">{{ __('docs.http_404') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">429</td><td class="px-3 py-2">{{ __('docs.http_429') }}</td></tr>
                <tr class="border-t"><td class="px-3 py-2 font-mono">500</td><td class="px-3 py-2">{{ __('docs.http_500') }}</td></tr>
            </tbody>
        </table>
        <h3 class="font-semibold mb-3">{{ __('docs.best_practices') }}</h3>
        <ul class="list-disc ml-5 space-y-2 text-sm text-gray-700">
            <li>{!! __('docs.bp_1') !!}</li>
            <li><strong>{{ __('docs.bp_2_title') }}</strong> — {{ __('docs.bp_2') }}</li>
            <li>{!! __('docs.bp_3') !!}</li>
            <li>{{ __('docs.bp_4') }}</li>
            <li>{{ __('docs.bp_5') }}</li>
        </ul>
    </div>
</section>
