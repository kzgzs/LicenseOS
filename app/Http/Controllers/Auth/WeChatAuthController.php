<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WeChatAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WeChatAuthController extends Controller
{
    protected WeChatAuthService $service;

    public function __construct(WeChatAuthService $service)
    {
        $this->service = $service;
    }

    /**
     * Redirect to WeChat OAuth (QR code for PC, in-app for WeChat browser).
     */
    public function redirect(Request $request)
    {
        if (!$this->service->isEnabled()) {
            return redirect('/login')->with('error', __('wechat_login_disabled'));
        }

        if ($this->service->isWeChatBrowser()) {
            return redirect($this->service->getInAppAuthUrl());
        }

        return redirect($this->service->getQrLoginUrl());
    }

    /**
     * Handle callback from WeChat OAuth.
     */
    public function callback(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return redirect('/login')->with('error', __('wechat_login_failed'));
        }

        // Exchange code for token
        $token = $this->service->getAccessToken($code);
        if (!$token) {
            return redirect('/login')->with('error', __('wechat_login_failed'));
        }

        $openid = $token['openid'] ?? null;
        $unionid = $token['unionid'] ?? null;
        $accessToken = $token['access_token'] ?? null;

        if (!$openid || !$accessToken) {
            return redirect('/login')->with('error', __('wechat_login_failed'));
        }

        // Get WeChat user info
        $wechatUser = $this->service->getUserInfo($accessToken, $openid);
        $nickname = $wechatUser['nickname'] ?? null;

        // Already logged in? Bind WeChat to current account
        if (Auth::check()) {
            return $this->bindToCurrentUser($openid, $unionid, $nickname);
        }

        // Find user by wechat_openid
        $user = User::where('wechat_openid', $openid)->first();

        // Try unionid fallback
        if (!$user && $unionid) {
            $user = User::where('wechat_unionid', $unionid)->first();
        }

        if ($user) {
            // Existing bound user → login
            Auth::login($user, true);
            return redirect('/dashboard')->with('success', __('welcome_back'));
        }

        // No linked account → show bind/register page
        session([
            'wechat_openid' => $openid,
            'wechat_unionid' => $unionid,
            'wechat_nickname' => $nickname,
        ]);

        return view('auth.wechat-bind', compact('nickname'));
    }

    /**
     * Bind WeChat to an existing account (login + password).
     */
    public function bindLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $openid = session('wechat_openid');
        $unionid = session('wechat_unionid');
        $nickname = session('wechat_nickname');

        // Check if this WeChat is already bound to another user
        if (User::where('wechat_openid', $openid)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['email' => __('wechat_already_bound')]);
        }

        $user->update([
            'wechat_openid' => $openid,
            'wechat_unionid' => $unionid,
            'wechat_nickname' => $nickname,
        ]);

        session()->forget(['wechat_openid', 'wechat_unionid', 'wechat_nickname']);
        Auth::login($user, true);

        return redirect('/dashboard')->with('success', __('wechat_bound_success'));
    }

    /**
     * Register a new account and bind WeChat.
     */
    public function bindRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $openid = session('wechat_openid');
        $unionid = session('wechat_unionid');
        $nickname = session('wechat_nickname');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'wechat_openid' => $openid,
            'wechat_unionid' => $unionid,
            'wechat_nickname' => $nickname ?? $validated['name'],
        ]);

        session()->forget(['wechat_openid', 'wechat_unionid', 'wechat_nickname']);
        Auth::login($user, true);

        return redirect('/dashboard')->with('success', __('auth.welcome_customer'));
    }

    /**
     * Bind WeChat to the currently authenticated user.
     */
    protected function bindToCurrentUser(string $openid, ?string $unionid, ?string $nickname)
    {
        $user = Auth::user();

        // Check if this WeChat is already bound to another user
        if (User::where('wechat_openid', $openid)->where('id', '!=', $user->id)->exists()) {
            return redirect('/dashboard')->with('error', __('wechat_already_bound'));
        }

        $user->update([
            'wechat_openid' => $openid,
            'wechat_unionid' => $unionid,
            'wechat_nickname' => $nickname,
        ]);

        return redirect('/dashboard')->with('success', __('wechat_bound_success'));
    }

    /**
     * Unbind WeChat from current user.
     */
    public function unbind(Request $request)
    {
        $request->user()->update([
            'wechat_openid' => null,
            'wechat_unionid' => null,
            'wechat_nickname' => null,
        ]);

        return back()->with('success', __('wechat_unbound_success'));
    }
}
