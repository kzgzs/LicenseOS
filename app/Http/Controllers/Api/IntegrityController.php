<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Activation;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegrityController extends Controller
{
    public function report(Request $request)
    {
        $request->validate([
            'domain'        => 'required|string|max:255',
            'code_hash'     => 'nullable|string',
            'api_key_hash'  => 'nullable|string',
            'license_key'   => 'nullable|string',
            'api_key'       => 'nullable|string',
            'product_slug'  => 'nullable|string',
        ]);

        $domain       = $request->input('domain');
        $codeHash     = $request->input('code_hash');
        $apiKeyHash   = $request->input('api_key_hash');
        $licenseKey   = $request->input('license_key', '');
        $apiKey       = $request->input('api_key', '');
        $productSlug  = $request->input('product_slug', '');

        // 查找产品
        $product = null;
        if ($productSlug) {
            $product = Product::where('slug', $productSlug)->first();
        }

        // 获取预期哈希
        $expectedCodeHash   = $product ? SiteSetting::get("integrity_hash_{$productSlug}", '') : '';
        $expectedApiKeyHash = $product ? SiteSetting::get("integrity_api_key_hash_{$productSlug}", '') : '';

        // 比对哈希
        $codeHashMatch   = empty($expectedCodeHash) || ($codeHash === $expectedCodeHash);
        $apiKeyHashMatch = empty($expectedApiKeyHash) || ($apiKeyHash === $expectedApiKeyHash);
        $isValid         = $codeHashMatch && $apiKeyHashMatch;

        // 查找许可证
        $license = null;
        if ($licenseKey) {
            $license = License::where('license_key', $licenseKey)->first();
        }

        // 记录日志
        DB::table('domain_integrity_logs')->insert([
            'product_id'    => $product?->id,
            'license_id'    => $license?->id,
            'domain'        => $domain,
            'code_hash'     => $codeHash,
            'expected_hash' => $expectedCodeHash,
            'api_key_hash'  => $apiKeyHash,
            'is_valid'      => $isValid,
            'ip_address'    => $request->ip(),
            'created_at'    => now(),
        ]);

        if (!$isValid) {
            Log::warning('[IntegrityGuard] 代码篡改检测', [
                'domain'       => $domain,
                'product'      => $productSlug,
                'code_match'   => $codeHashMatch,
                'api_key_match' => $apiKeyHashMatch,
            ]);

            // 标记许可证为篡改
            if ($license) {
                $license->update([
                    'is_tampered' => true,
                    'tampered_at' => now(),
                ]);

                // 同时更新该域名的激活记录篡改状态
                Activation::where('license_id', $license->id)
                    ->where('domain', $domain)
                    ->update(['is_tampered' => true, 'tampered_at' => now()]);
            }

            // 将域名加入黑名单
            $blacklist = json_decode(SiteSetting::get('blacklisted_domains', '[]'), true) ?: [];
            if (!in_array($domain, $blacklist)) {
                $blacklist[] = $domain;
                SiteSetting::set('blacklisted_domains', json_encode($blacklist));
            }

            return response()->json([
                'status'  => 'tampered',
                'message' => 'Code integrity check failed. Your domain has been recorded.',
            ]);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Integrity check passed.',
        ]);
    }
}
