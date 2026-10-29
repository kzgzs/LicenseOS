<h1 class="text-3xl font-bold mb-2">{{ __('faq.title') }}</h1>
<p class="text-gray-500 mb-8">{{ __('faq.subtitle') }}</p>

{{-- 目录 --}}
<div class="bg-white rounded-lg shadow-sm p-6 mb-8">
    <h2 class="font-semibold mb-3">{{ __('faq.toc') }}</h2>
    <div class="grid md:grid-cols-2 gap-2 text-sm">
        <a href="#getting-started" class="text-indigo-600 hover:underline">1. {{ __('faq.s1') }}</a>
        <a href="#sdk-integration" class="text-indigo-600 hover:underline">2. {{ __('faq.s2') }}</a>
        <a href="#license-api" class="text-indigo-600 hover:underline">3. {{ __('faq.s3') }}</a>
        <a href="#update-system" class="text-indigo-600 hover:underline">4. {{ __('faq.s4') }}</a>
        <a href="#pricing-commission" class="text-indigo-600 hover:underline">5. {{ __('faq.s5') }}</a>
        <a href="#payout" class="text-indigo-600 hover:underline">6. {{ __('faq.s6') }}</a>
        <a href="#troubleshooting" class="text-indigo-600 hover:underline">7. {{ __('faq.s7') }}</a>
    </div>
</div>

{{-- 1. 快速开始 --}}
<div id="getting-started" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">1</span>
        {{ __('faq.s1') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q1_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>{{ __('faq.q1_1_a1') }}</li>
                    <li>{{ __('faq.q1_1_a2') }}</li>
                    <li>{{ __('faq.q1_1_a3') }}</li>
                    <li>{{ __('faq.q1_1_a4') }}</li>
                    <li>{{ __('faq.q1_1_a5') }}</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q1_2') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-2">{{ __('faq.q1_2_intro') }}</p>
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>{{ __('faq.q1_2_a1') }}</li>
                    <li>{{ __('faq.q1_2_a2') }}</li>
                    <li>{{ __('faq.q1_2_a3') }}</li>
                    <li>{!! __('faq.q1_2_a4') !!}</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q1_3') }}</span>
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

{{-- 2. SDK 集成 --}}
<div id="sdk-integration" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">2</span>
        {{ __('faq.s2') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q2_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">{{ __('faq.q2_1_desc') }}</p>
                <div class="code-block mb-3">
<pre><code><span class="comment">// 在插件主文件中（如 my-plugin.php）</span>
<span class="keyword">require_once</span> __DIR__ . <span class="string">'/licenseos-sdk.php'</span>;

<span class="comment">// 使用平台 API URL 和 API Key 初始化</span>
LicenseOS::<span class="func">init</span>(
    <span class="string">'https://your-licenseos-domain.com/api/v2'</span>,
    <span class="string">'your-api-key-here'</span>
);

<span class="comment">// 启用 WordPress 自动更新集成</span>
LicenseOS::<span class="func">enableAutoUpdate</span>(
    <span class="string">'your-plugin-slug'</span>,
    <span class="string">'stored-license-key'</span>
);</code></pre>
                </div>
                <p class="text-sm text-gray-700">{{ __('faq.q2_1_note') }}</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q2_2') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">{{ __('faq.q2_2_desc') }}</p>
                <div class="code-block mb-3">
<pre><code><span class="comment">// 用户提交许可证密钥时</span>
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
                <span class="font-medium">{{ __('faq.q2_3') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700 mb-3">{{ __('faq.q2_3_desc') }}</p>
                <div class="code-block mb-3">
<pre><code><span class="keyword">function</span> <span class="func">my_plugin_check_license</span>() {
    <span class="keyword">$key</span> = <span class="func">get_option</span>(<span class="string">'my_plugin_license_key'</span>);
    <span class="keyword">if</span> (!<span class="keyword">$key</span>) <span class="keyword">return</span> <span class="keyword">false</span>;

    <span class="comment">// 缓存检查结果 1 小时</span>
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

{{-- 3. License API --}}
<div id="license-api" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">3</span>
        {{ __('faq.s3') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q3_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-gray-500">{{ __('faq.method') }}</th><th class="px-3 py-2 text-left text-gray-500">Endpoint</th><th class="px-3 py-2 text-left text-gray-500">{{ __('faq.auth') }}</th><th class="px-3 py-2 text-left text-gray-500">{{ __('faq.desc') }}</th></tr></thead>
                        <tbody>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-green-100 text-green-700 px-1 rounded">POST</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/license/activate</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">{{ __('faq.q3_1_activate') }}</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-green-100 text-green-700 px-1 rounded">POST</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/license/check</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">{{ __('faq.q3_1_check') }}</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-blue-100 text-blue-700 px-1 rounded">GET</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/update/check</td><td class="px-3 py-2">API Key</td><td class="px-3 py-2">{{ __('faq.q3_1_update') }}</td></tr>
                            <tr class="border-t"><td class="px-3 py-2"><code class="text-xs bg-blue-100 text-blue-700 px-1 rounded">GET</code></td><td class="px-3 py-2 font-mono text-xs">/api/v2/download</td><td class="px-3 py-2">License Key</td><td class="px-3 py-2">{{ __('faq.q3_1_download') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-3">{{ __('faq.q3_1_note') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- 4. 更新系统 --}}
<div id="update-system" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">4</span>
        {{ __('faq.s4') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q4_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>{!! __('faq.q4_1_a1') !!}</li>
                    <li>{!! __('faq.q4_1_a2') !!}</li>
                    <li>{!! __('faq.q4_1_a3') !!}</li>
                    <li>{{ __('faq.q4_1_a4') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>

{{-- 5. 定价与佣金 --}}
<div id="pricing-commission" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">5</span>
        {{ __('faq.s5') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q5_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">{{ __('faq.q5_1_a') }}</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q5_2') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">{{ __('faq.q5_2_a') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- 6. 提现 --}}
<div id="payout" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">6</span>
        {{ __('faq.s6') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q6_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>{!! __('faq.q6_1_a1') !!}</li>
                    <li>{{ __('faq.q6_1_a2') }}</li>
                    <li>{!! __('faq.q6_1_a3') !!}</li>
                    <li>{{ __('faq.q6_1_a4') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>

{{-- 7. 常见问题排查 --}}
<div id="troubleshooting" class="mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="w-7 h-7 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">7</span>
        {{ __('faq.s7') }}
    </h2>
    <div class="space-y-0 bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q7_1') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <p class="text-sm text-gray-700">{{ __('faq.q7_1_a') }}</p>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q7_2') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ol class="list-decimal ml-5 space-y-2 text-sm text-gray-700">
                    <li>{!! __('faq.q7_2_a1') !!}</li>
                    <li>{{ __('faq.q7_2_a2') }}</li>
                    <li>{!! __('faq.q7_2_a3') !!}</li>
                </ol>
            </div>
        </div>
        <div class="faq-section">
            <div class="faq-toggle px-6 py-4 flex items-center gap-3" onclick="toggleFaq(this)">
                <span class="arrow text-gray-400">&#9654;</span>
                <span class="font-medium">{{ __('faq.q7_3') }}</span>
            </div>
            <div class="faq-answer px-6 pb-4">
                <ul class="list-disc ml-5 space-y-2 text-sm text-gray-700">
                    <li>{!! __('faq.q7_3_a1') !!}</li>
                    <li>{{ __('faq.q7_3_a2') }}</li>
                    <li>{{ __('faq.q7_3_a3') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
