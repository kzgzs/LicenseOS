<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeChatAuthService
{
    protected string $appId;
    protected string $appSecret;
    protected string $redirectUri;

    public function __construct()
    {
        $this->appId = SiteSetting::get('wechat_open_app_id', config('services.wechat_open.app_id', ''));
        $this->appSecret = SiteSetting::get('wechat_open_app_secret', config('services.wechat_open.app_secret', ''));
        $this->redirectUri = config('app.url') . '/auth/wechat/callback';
    }

    public function isEnabled(): bool
    {
        return SiteSetting::get('wechat_login_enabled', '0') === '1'
            && !empty($this->appId)
            && !empty($this->appSecret);
    }

    /**
     * Generate the WeChat Open Platform QR code login URL (PC browser).
     */
    public function getQrLoginUrl(string $state = ''): string
    {
        $state = $state ?: md5(uniqid(mt_rand(), true));
        session(['wechat_oauth_state' => $state]);

        return 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
            'appid' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'snsapi_login',
            'state' => $state,
        ]) . '#wechat_redirect';
    }

    /**
     * Generate the WeChat in-app OAuth URL (WeChat built-in browser).
     */
    public function getInAppAuthUrl(string $state = ''): string
    {
        $state = $state ?: md5(uniqid(mt_rand(), true));
        session(['wechat_oauth_state' => $state]);

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query([
            'appid' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'snsapi_userinfo',
            'state' => $state,
        ]) . '#wechat_redirect';
    }

    /**
     * Exchange authorization code for access_token + openid.
     */
    public function getAccessToken(string $code): ?array
    {
        try {
            $response = Http::get('https://api.weixin.qq.com/sns/oauth2/access_token', [
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

            $data = $response->json();
            if (isset($data['errcode'])) {
                Log::error('WeChat token error: ' . json_encode($data));
                return null;
            }
            return $data;
        } catch (\Exception $e) {
            Log::error('WeChat token request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user info using access_token and openid.
     */
    public function getUserInfo(string $accessToken, string $openid): ?array
    {
        try {
            $response = Http::get('https://api.weixin.qq.com/sns/userinfo', [
                'access_token' => $accessToken,
                'openid' => $openid,
                'lang' => 'zh_CN',
            ]);

            $data = $response->json();
            if (isset($data['errcode'])) {
                Log::error('WeChat userinfo error: ' . json_encode($data));
                return null;
            }
            return $data;
        } catch (\Exception $e) {
            Log::error('WeChat userinfo request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Detect if current request is from WeChat built-in browser.
     */
    public function isWeChatBrowser(): bool
    {
        $ua = request()->userAgent() ?? '';
        return str_contains(strtolower($ua), 'micromessenger');
    }
}
