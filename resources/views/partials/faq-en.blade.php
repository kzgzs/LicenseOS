<h1 class="text-3xl font-bold mb-2">Developer FAQ</h1>
<p class="text-gray-500 mb-8">Everything you need to integrate your products with LicenseOS.</p>

{{-- Table of Contents --}}
<div class="bg-white rounded-lg shadow-sm p-6 mb-8">
    <h2 class="font-semibold mb-3">Table of Contents</h2>
    <div class="grid md:grid-cols-2 gap-2 text-sm">
        <a href="#getting-started" class="text-indigo-600 hover:underline">1. Getting Started</a>
        <a href="#sdk-integration" class="text-indigo-600 hover:underline">2. SDK Integration (WordPress)</a>
        <a href="#license-api" class="text-indigo-600 hover:underline">3. License API</a>
        <a href="#update-system" class="text-indigo-600 hover:underline">4. Update System</a>
        <a href="#pricing-commission" class="text-indigo-600 hover:underline">5. Pricing & Commission</a>
        <a href="#payout" class="text-indigo-600 hover:underline">6. Payout</a>
        <a href="#troubleshooting" class="text-indigo-600 hover:underline">7. Troubleshooting</a>
    </div>
</div>

{{-- 1. Getting Started --}}
<div id="getting-started" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">1</span>
        Getting Started
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How do I become a developer on LicenseOS?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>Click <strong>Register</strong> to create an account.</li>
                    <li>Navigate to the <strong>Developer → Apply</strong> page from your user menu or footer links.</li>
                    <li>Fill in your developer profile (name, website, description) and submit.</li>
                    <li>Once approved by admin, you'll get access to the <strong>Developer Dashboard</strong>.</li>
                    <li>From Dashboard, go to <strong>Products → Create</strong> to publish your first product.</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How do I get an API Key?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-2">After your developer account is approved:</p>
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>Go to <strong>Developer Dashboard → API Keys</strong>.</li>
                    <li>Click <strong>Generate New Key</strong>.</li>
                    <li>Copy the key — it will only be shown once.</li>
                    <li>Use this key in your SDK initialization as the <code class="bg-gray-100 px-1 rounded">api_key</code> parameter.</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">What product types are supported?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @foreach(\App\Models\Product::TYPES as $key => $label)
                        <span class="px-3 py-1.5 bg-gray-50 rounded text-gray-700"><code class="text-xs text-indigo-600">{{ $key }}</code> — {{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. SDK Integration --}}
<div id="sdk-integration" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">2</span>
        SDK Integration (WordPress)
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">Quick Start: Integrate LicenseOS SDK in a WordPress plugin</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">Download the SDK file from the platform and include it in your plugin. Initialize in your plugin's main file:</p>
                <div class="code-block mb-3">
<pre><code><span class="comment">// In your plugin's main file (e.g. my-plugin.php)</span>
<span class="keyword">require_once</span> __DIR__ . <span class="string">'/licenseos-sdk.php'</span>;

<span class="comment">// Initialize with your platform API URL and API key</span>
LicenseOS::<span class="func">init</span>(
    <span class="string">'https://your-licenseos-domain.com/api/v2'</span>,
    <span class="string">'your-api-key-here'</span>
);

<span class="comment">// Enable WordPress auto-update integration</span>
LicenseOS::<span class="func">enableAutoUpdate</span>(
    <span class="string">'your-plugin-slug'</span>,
    <span class="string">'stored-license-key'</span>
);</code></pre>
                </div>
                <p class="text-sm text-gray-700">This enables license activation, status checking, and automatic updates directly within WordPress.</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How to add a license activation page in my plugin?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">Create a settings page where users enter their license key, then call the SDK:</p>
                <div class="code-block mb-3">
<pre><code><span class="comment">// When user submits their license key</span>
<span class="keyword">function</span> <span class="func">my_plugin_activate_license</span>(<span class="keyword">$key</span>) {
    <span class="keyword">$domain</span> = <span class="func">parse_url</span>(<span class="func">home_url</span>(), PHP_URL_HOST);
    <span class="keyword">$result</span> = LicenseOS::<span class="func">activate</span>(<span class="keyword">$key</span>, <span class="keyword">$domain</span>, <span class="string">'my-plugin-slug'</span>);

    <span class="keyword">if</span> (<span class="keyword">$result</span>[<span class="string">'status'</span>] === <span class="string">'success'</span>) {
        <span class="func">update_option</span>(<span class="string">'my_plugin_license_key'</span>, <span class="keyword">$key</span>);
        <span class="func">update_option</span>(<span class="string">'my_plugin_license_status'</span>, <span class="string">'active'</span>);
        <span class="keyword">return</span> <span class="string">'License activated!'</span>;
    }

    <span class="keyword">return</span> <span class="string">'Activation failed: '</span> . (<span class="keyword">$result</span>[<span class="string">'message'</span>] ?? <span class="string">'Unknown error'</span>);
}</code></pre>
                </div>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How to check license status on every page load?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">Use a cached check to avoid API calls on every request. Verify once per hour:</p>
                <div class="code-block mb-3">
<pre><code><span class="keyword">function</span> <span class="func">my_plugin_check_license</span>() {
    <span class="keyword">$key</span> = <span class="func">get_option</span>(<span class="string">'my_plugin_license_key'</span>);
    <span class="keyword">if</span> (!<span class="keyword">$key</span>) <span class="keyword">return</span> <span class="keyword">false</span>;

    <span class="comment">// Cache check result for 1 hour</span>
    <span class="keyword">$cached</span> = <span class="func">get_transient</span>(<span class="string">'my_plugin_license_check'</span>);
    <span class="keyword">if</span> (<span class="keyword">$cached</span> !== <span class="keyword">false</span>) <span class="keyword">return</span> <span class="keyword">$cached</span> === <span class="string">'valid'</span>;

    <span class="keyword">$result</span> = LicenseOS::<span class="func">check</span>(<span class="keyword">$key</span>);
    <span class="keyword">$valid</span> = (<span class="keyword">$result</span>[<span class="string">'status'</span>] ?? <span class="string">''</span>) === <span class="string">'valid'</span>;

    <span class="func">set_transient</span>(<span class="string">'my_plugin_license_check'</span>,
        <span class="keyword">$valid</span> ? <span class="string">'valid'</span> : <span class="string">'invalid'</span>, HOUR_IN_SECONDS);

    <span class="keyword">return</span> <span class="keyword">$valid</span>;
}</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3-7 sections (same content as original) --}}
{{-- License API, Update System, Pricing, Payout, Troubleshooting --}}
<div id="license-api" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">3</span>
        License API
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">API Endpoints reference</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-gray-500">Method</th><th class="px-3 py-2 text-left text-gray-500">Endpoint</th><th class="px-3 py-2 text-left text-gray-500">Auth</th><th class="px-3 py-2 text-left text-gray-500">Description</th></tr></thead>
                        <tbody>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-green-100 text-green-700 px-1 rounded">POST</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/license/activate</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">Activate a license key for a domain</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-green-100 text-green-700 px-1 rounded">POST</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/license/check</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">Check license status & expiry</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-blue-100 text-blue-700 px-1 rounded">GET</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/update/check</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">Check for new versions</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-blue-100 text-blue-700 px-1 rounded">GET</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/download</td><td class="px-3 py-2">License Key</td><td class="px-3 py-2">Secure download</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-3">Pass API Key via <code class="bg-gray-100 px-1 rounded">X-API-KEY</code> header. All responses are JSON.</p>
            </div>
        </div>
    </div>
</div>

<div id="update-system" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">4</span>
        Update System
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How to push a new version?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>Go to <strong>Developer → Products</strong>, click your product.</li>
                    <li>In the <strong>Upload New Version</strong> section, fill in version number, ZIP file and changelog.</li>
                    <li>Click <strong>Upload Version</strong>.</li>
                    <li>All customers with active licenses will see the update automatically.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div id="pricing-commission" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">5</span>
        Pricing & Commission
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How do pricing tiers work?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">Each product supports multiple pricing tiers: trial, monthly, quarterly, semi-annual, annual, and lifetime. Configure tiers in your product's <strong>Edit → Pricing Tiers</strong> section.</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How is commission calculated?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">When a sale is made, the platform deducts a commission fee. The remaining amount is your <strong>developer income</strong>. Check <strong>Developer → Payouts</strong> for details.</p>
            </div>
        </div>
    </div>
</div>

<div id="payout" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">6</span>
        Payout
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">How do I withdraw my earnings?</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>Set up your payout account in <strong>Developer → Payouts → Payout Methods</strong>.</li>
                    <li>Supported methods: Bank transfer, PayPal, Alipay, WeChat Pay.</li>
                    <li>Click <strong>Request Payout</strong> and enter the amount.</li>
                    <li>Admin reviews and processes the payout.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div id="troubleshooting" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">7</span>
        Troubleshooting
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">License activation returns "max domains reached"</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">The license has been activated on the maximum allowed number of domains. Purchase a higher-tier license with more domain slots.</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">WordPress doesn't show available updates</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>Verify <code class="bg-gray-100 px-1 rounded">enableAutoUpdate()</code> is called after <code class="bg-gray-100 px-1 rounded">LicenseOS::init()</code>.</li>
                    <li>Ensure the plugin slug matches the product slug.</li>
                    <li>Force refresh: <strong>Dashboard → Updates → Check Again</strong>.</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">API returns "Invalid API key" or 401</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ul class="list-disc ml-5 space-y-2 text-sm text-gray-700">
                    <li>Ensure the <code class="bg-gray-100 px-1 rounded">X-API-KEY</code> header is being sent correctly.</li>
                    <li>Verify the API key hasn't been revoked.</li>
                    <li>Regenerate a new key if needed.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
