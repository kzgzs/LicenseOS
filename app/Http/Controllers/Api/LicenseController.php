<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
        ]);

        $result = $this->licenseService->activate(
            $request->input('license_key'),
            $request->input('domain'),
            $request->ip(),
        );

        return response()->json($result);
    }

    public function check(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
        ]);

        $result = $this->licenseService->check($request->input('license_key'));

        // 1. 许可证不存在，直接返回
        if ($result['status'] === 'invalid') {
            return response()->json($result);
        }

        // 2. 篡改检查：is_tampered=true 时强制返回 tampered 状态
        if (!empty($result['is_tampered'])) {
            $result['status'] = 'tampered';
            $result['tamper_warning'] = '许可证因检测到代码篡改已被暂停，请联系平台管理员';
            return response()->json($result);
        }

        // 3. 黑名单检查：请求域名在 blacklisted_domains 中时返回 suspended 状态
        $blacklistedDomains = SiteSetting::get('blacklisted_domains', '');
        if (is_string($blacklistedDomains)) {
            $blacklistedDomains = array_filter(array_map('trim', explode(',', $blacklistedDomains)));
        }
        $domain = $request->input('domain', '');
        if ($domain && in_array($domain, $blacklistedDomains)) {
            $result['status'] = 'suspended';
            $result['tamper_warning'] = '此域名已被平台暂停授权，请联系平台管理员';
            return response()->json($result);
        }

        // 4. 正常返回许可证状态
        return response()->json($result);
    }

    /**
     * SDK 调用此端点确认 mu-plugin 删除请求合法，然后执行本地删除。
     *
     * 流程：
     * 1. 平台 uninjectNow 调用 WP 站点的 SDK 端点
     * 2. SDK 回调平台 removeGuardian 验证删除请求合法性
     * 3. 平台返回确认后 SDK 执行本地 mu-plugin 文件删除
     *
     * POST /api/v2/license/remove-guardian
     */
    public function removeGuardian(Request $request)
    {
        $request->validate([
            'product_slug' => 'required|string',
            'license_key'  => 'required|string',
            'domain'       => 'required|string',
        ]);

        $productSlug = $request->input('product_slug');
        $licenseKey  = $request->input('license_key');
        $domain      = $request->input('domain');

        // 验证产品存在
        $product = Product::where('slug', $productSlug)->first();
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found.'], 404);
        }

        // 验证许可证合法且域名匹配
        $license = License::where('license_key', $licenseKey)
            ->where('product_id', $product->id)
            ->first();

        if (!$license) {
            return response()->json(['status' => 'error', 'message' => 'License not found.'], 404);
        }

        $domainMatch = $license->activations()
            ->where('domain', $domain)
            ->exists();

        if (!$domainMatch) {
            return response()->json(['status' => 'error', 'message' => 'Domain not activated.'], 403);
        }

        // 检查该产品是否已清除注入记录
        $latestPackage = $product->versions()->latest('id')->first();
        $guardianActive = $latestPackage && !empty($latestPackage->injection_modules);

        Log::info('[removeGuardian] SDK 请求删除 mu-plugin', [
            'product' => $productSlug,
            'domain'  => $domain,
            'guardian_active' => $guardianActive,
        ]);

        return response()->json([
            'status'            => 'ok',
            'delete_mu_plugin'  => true,
            'guardian_active'   => $guardianActive,
            'message'           => 'Guardian removal authorized.',
        ]);
    }
}
