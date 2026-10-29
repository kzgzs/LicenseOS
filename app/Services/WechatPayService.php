<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;

class WechatPayService
{
    protected string $appId;
    protected string $mchId;
    protected string $apiKey;
    protected string $notifyUrl;

    public function __construct()
    {
        $this->appId = config('services.wechat.app_id', '');
        $this->mchId = config('services.wechat.mch_id', '');
        $this->apiKey = config('services.wechat.api_key', '');
        $this->notifyUrl = env('WECHAT_NOTIFY_URL', config('app.url') . '/api/webhook/wechat');
    }

    /**
     * Check if WeChat Pay is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->mchId) && !empty($this->apiKey);
    }

    /**
     * Create a Native Pay order (QR code scan).
     * Returns the code_url for QR code generation.
     */
    public function createNativeOrder(Order $order): array
    {
        // Unique out_trade_no: orderId + timestamp to avoid WeChat "parameter mismatch on re-entry"
        $outTradeNo = $order->id . '_' . time();

        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => $this->generateNonceStr(),
            'body' => $order->product->name ?? 'LicenseOS Purchase',
            'out_trade_no' => $outTradeNo,
            'total_fee' => $this->toCents((float) $order->amount), // Amount in fen (分)
            'spbill_create_ip' => request()->ip() ?? '127.0.0.1',
            'notify_url' => $this->notifyUrl,
            'trade_type' => 'NATIVE',
        ];

        $params['sign'] = $this->sign($params);

        $xml = $this->toXml($params);
        $response = $this->httpPost('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        $result = $this->fromXml($response);

        if (($result['return_code'] ?? '') !== 'SUCCESS') {
            throw new \RuntimeException('WeChat Pay error: ' . ($result['return_msg'] ?? 'Unknown error'));
        }

        if (($result['result_code'] ?? '') !== 'SUCCESS') {
            throw new \RuntimeException('WeChat Pay error: ' . ($result['err_code_des'] ?? 'Unknown error'));
        }

        $order->update([
            'payment_method' => 'wechat',
            'payment_intent_id' => $result['prepay_id'] ?? null,
        ]);

        return [
            'code_url' => $result['code_url'],
            'prepay_id' => $result['prepay_id'] ?? '',
        ];
    }

    /**
     * Verify and parse a WeChat Pay callback notification.
     */
    public function verifyCallback(string $xml): ?array
    {
        $data = $this->fromXml($xml);

        if (($data['return_code'] ?? '') !== 'SUCCESS') {
            return null;
        }

        // Verify signature
        $sign = $data['sign'] ?? '';
        unset($data['sign']);
        if ($this->sign($data) !== $sign) {
            Log::warning('WeChat Pay callback signature mismatch');
            return null;
        }

        return $data;
    }

    /**
     * Generate success XML response for WeChat callback.
     */
    public function successResponse(): string
    {
        return $this->toXml([
            'return_code' => 'SUCCESS',
            'return_msg' => 'OK',
        ]);
    }

    /**
     * Generate fail XML response for WeChat callback.
     */
    public function failResponse(string $msg = 'FAIL'): string
    {
        return $this->toXml([
            'return_code' => 'FAIL',
            'return_msg' => $msg,
        ]);
    }

    // === Helpers ===

    protected function toCents(float $amount): int
    {
        $currency = strtolower(SiteSetting::get('store_currency', 'CNY'));
        // CNY uses fen (分), 1 CNY = 100 fen
        return (int) round($amount * 100);
    }

    protected function sign(array $params): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $string .= "{$key}={$value}&";
            }
        }
        $string .= "key={$this->apiKey}";
        return strtoupper(md5($string));
    }

    protected function generateNonceStr(int $length = 32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    protected function toXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $xml .= "<{$key}>{$value}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    protected function fromXml(string $xml): array
    {
        if (empty($xml)) {
            return [];
        }
        // Disable external entity loading for security
        $backup = libxml_disable_entity_loader(true);
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR);
        libxml_disable_entity_loader($backup);

        if ($obj === false) {
            return [];
        }
        return json_decode(json_encode($obj), true) ?: [];
    }

    protected function httpPost(string $url, string $data, int $timeout = 30): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
        ]);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("WeChat Pay HTTP error: {$error}");
        }
        curl_close($ch);
        return $result ?: '';
    }
}
