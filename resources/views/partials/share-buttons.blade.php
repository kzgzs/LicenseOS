@if(!empty($shareEnabled ?? false))
@php
    // 参数取值与 og 元信息一致（由 ShareMetaComposer 提供）
    $sTitle = $shareTitle ?? trim($__env->yieldContent('title', \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform'))));
    $sDesc = $shareDescription ?? '';
    $sImage = $shareImage ?? '';
    $sUrl = $shareUrl ?? request()->fullUrl();
    $qqFriendUrl = 'https://connect.qq.com/widget/shareqq/index.html?url=' . rawurlencode($sUrl)
        . '&title=' . rawurlencode($sTitle)
        . '&desc=' . rawurlencode($sDesc)
        . '&pics=' . rawurlencode($sImage);
    $qzoneUrl = 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' . rawurlencode($sUrl)
        . '&title=' . rawurlencode($sTitle)
        . '&desc=' . rawurlencode($sDesc)
        . '&pics=' . rawurlencode($sImage);
@endphp
<div class="my-6" x-data="{ wechatOpen: false }" id="share-bar">
    <style>[x-cloak]{display:none!important}</style>
    <div class="flex items-center flex-wrap gap-2">
        <span class="text-sm text-gray-500 dark:text-gray-400 mr-1">{{ __('share.title') }}</span>

        {{-- 微信：弹窗二维码分享 --}}
        <button type="button"
                @click="wechatOpen = true; window.shareInitQr && window.shareInitQr()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-200 hover:border-green-400 hover:text-green-600 dark:hover:text-green-400 transition-colors">
            <svg class="w-4 h-4 text-green-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M8.7 4C4.9 4 2 6.6 2 9.8c0 1.8.9 3.4 2.5 4.5l-.6 1.9 2.2-1.1c.7.2 1.3.3 2 .3h.4a5.4 5.4 0 0 1-.2-1.5c0-3.1 3-5.6 6.7-5.6h.4C14.7 5.7 12 4 8.7 4zM6.5 7.2c.5 0 .9.4.9.9s-.4.9-.9.9-.9-.4-.9-.9.4-.9.9-.9zm4.5 0c.5 0 .9.4.9.9s-.4.9-.9.9-.9-.4-.9-.9.4-.9.9-.9zM15.3 9.5c-3.2 0-5.8 2.2-5.8 4.9s2.6 4.9 5.8 4.9c.6 0 1.2-.1 1.7-.3l1.9 1-.5-1.7c1.4-.9 2.2-2.3 2.2-3.9 0-2.7-2.6-4.9-5.3-4.9zm-2 2.7c.4 0 .8.3.8.8s-.4.8-.8.8-.8-.3-.8-.8.4-.8.8-.8zm4 0c.4 0 .8.3.8.8s-.4.8-.8.8-.8-.3-.8-.8.4-.8.8-.8z"/>
            </svg>
            {{ __('share.wechat') }}
        </button>

        {{-- QQ 好友 --}}
        <a href="{{ $qqFriendUrl }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-200 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2C8.1 2 5 5 5 8.7c0 .6.1 1.2.2 1.8-.6 1-1.5 2.7-1.9 4.1-.3 1 .1 1.4.5 1.5.4.1 1-.1 1.6-.9.1.5.4 1.1.8 1.6-1 .4-1.7 1-1.7 1.7 0 1.1 1.7 1.9 3.9 1.9 1.3 0 2.5-.3 3.2-.7.1 0 .3 0 .4 0s.3 0 .4 0c.7.4 1.9.7 3.2.7 2.2 0 3.9-.8 3.9-1.9 0-.7-.7-1.3-1.7-1.7.4-.5.7-1.1.8-1.6.6.8 1.2 1 1.6.9.4-.1.8-.5.5-1.5-.4-1.4-1.3-3.1-1.9-4.1.1-.6.2-1.2.2-1.8C19 5 15.9 2 12 2z"/>
            </svg>
            {{ __('share.qq_friend') }}
        </a>

        {{-- QQ 空间 --}}
        <a href="{{ $qzoneUrl }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-200 hover:border-yellow-400 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors">
            <svg class="w-4 h-4 text-yellow-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
            </svg>
            {{ __('share.qzone') }}
        </a>
    </div>

    {{-- 微信分享弹窗 --}}
    <div x-show="wechatOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="wechatOpen = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="wechatOpen = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-sm w-full p-6 text-center">
            <button type="button" @click="wechatOpen = false"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close">
                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('share.wechat') }}</h3>

            {{-- 二维码区域（qrcodejs 加载失败时隐藏） --}}
            <div id="shareQrWrap">
                <div id="shareQrBox" class="inline-block p-3 bg-white rounded-lg border border-gray-200"></div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ __('share.scan_tip') }}</p>
            </div>
            {{-- 二维码加载失败兜底提示 --}}
            <p id="shareQrFallback" class="hidden mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('share.qr_failed') }}</p>

            <div class="mt-4 flex items-center gap-2">
                <input type="text" readonly value="{{ $sUrl }}"
                       class="flex-1 min-w-0 px-3 py-2 text-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                       onclick="this.select()">
                <button type="button" data-url="{{ $sUrl }}" onclick="window.shareCopyLink(this)"
                        class="shrink-0 px-3 py-2 text-sm rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                    {{ __('share.copy_link') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var SHARE_URL = @json($sUrl);
    var QR_SRC = 'https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';

    function showToast(msg, ok) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-4 py-2 rounded-lg text-sm text-white shadow-lg ' +
            (ok ? 'bg-green-600' : 'bg-red-600');
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 2200);
    }

    function qrFallback() {
        var wrap = document.getElementById('shareQrWrap');
        var fb = document.getElementById('shareQrFallback');
        if (wrap) wrap.classList.add('hidden');
        if (fb) fb.classList.remove('hidden');
    }

    window.shareInitQr = function () {
        var box = document.getElementById('shareQrBox');
        if (!box) return;
        var wrap = document.getElementById('shareQrWrap');
        if (wrap) wrap.classList.remove('hidden');
        var fb = document.getElementById('shareQrFallback');
        if (fb) fb.classList.add('hidden');

        var render = function () {
            try {
                box.innerHTML = '';
                new QRCode(box, {
                    text: SHARE_URL,
                    width: 180,
                    height: 180,
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                qrFallback();
            }
        };

        if (window.QRCode) { render(); return; }
        var s = document.createElement('script');
        s.src = QR_SRC;
        s.onload = render;
        s.onerror = qrFallback;
        document.head.appendChild(s);
    };

    window.shareCopyLink = function (btn) {
        var text = (btn && btn.dataset.url) || SHARE_URL;
        var done = function () { showToast(@json(__('share.copied')), true); };
        var failed = function () { showToast(@json(__('share.copy_failed')), false); };
        var fallback = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy') ? done() : failed();
            } catch (e) {
                failed();
            }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }
    };
})();
</script>
@endif
