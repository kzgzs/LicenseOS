<?php

return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
    ],

    'wechat' => [
        'app_id' => env('WECHAT_APP_ID'),
        'mch_id' => env('WECHAT_MCH_ID'),
        'api_key' => env('WECHAT_API_KEY'),
        'cert_path' => env('WECHAT_CERT_PATH'),
        'notify_url' => env('WECHAT_NOTIFY_URL'),
        'jsapi_auth_url' => env('WECHAT_JSAPI_AUTH_URL'),
    ],

    'wechat_open' => [
        'app_id' => env('WECHAT_OPEN_APP_ID'),
        'app_secret' => env('WECHAT_OPEN_APP_SECRET'),
    ],

    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID'),
        'private_key' => env('ALIPAY_PRIVATE_KEY'),
        'alipay_public_key' => env('ALIPAY_PUBLIC_KEY'),
        'gateway' => env('ALIPAY_GATEWAY', 'https://openapi.alipay.com/gateway.do'),
        'sign_type' => env('ALIPAY_SIGN_TYPE', 'RSA2'),
        'notify_url' => env('ALIPAY_NOTIFY_URL'),
        'return_url' => env('ALIPAY_RETURN_URL'),
    ],

    'platform' => [
        'fee_rate' => (float) env('PLATFORM_FEE_RATE', 10),
        'payout_min_amount' => (float) env('PAYOUT_MIN_AMOUNT', 10),
        'payout_delay_days' => (int) env('PAYOUT_DELAY_DAYS', 7),
    ],

    'translate' => [
        'appid' => env('BAIDU_TRANSLATE_APPID'),
        'secret' => env('BAIDU_TRANSLATE_SECRET'),
    ],
];
