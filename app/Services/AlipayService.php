<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class AlipayService
{
    protected string $appId;
    protected string $privateKey;
    protected string $alipayPublicKey;
    protected string $gateway;
    protected string $notifyUrl;
    protected string $returnUrl;

    public function __construct()
    {
        $config = config('services.alipay');
        $this->appId = $config['app_id'] ?? '';
        $this->privateKey = $config['private_key'] ?? '';
        $this->alipayPublicKey = $config['alipay_public_key'] ?? '';
        $this->gateway = $config['gateway'] ?? 'https://openapi.alipay.com/gateway.do';
        $this->notifyUrl = $config['notify_url'] ?? '';
        $this->returnUrl = $config['return_url'] ?? '';
    }

    /**
     * Check if Alipay is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->privateKey) && !empty($this->alipayPublicKey);
    }

    /**
     * Create a PC page pay order (alipay.trade.page.pay).
     * Returns the redirect URL to Alipay cashier.
     */
    public function createPagePay(Order $order): string
    {
        $outTradeNo = $order->id . '_' . time();
        $totalAmount = number_format((float) $order->amount, 2, '.', '');

        $bizContent = json_encode([
            'out_trade_no' => $outTradeNo,
            'total_amount' => $totalAmount,
            'subject' => $order->product->name ?? 'LicenseOS Purchase',
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ], JSON_UNESCAPED_UNICODE);

        $params = $this->buildPublicParams('alipay.trade.page.pay', $bizContent);
        $params['return_url'] = $this->returnUrl;
        $params['notify_url'] = $this->notifyUrl;
        $params['sign'] = $this->generateSign($params);

        $url = $this->gateway . '?' . http_build_query($params);

        error_log("[AlipayService] PagePay URL generated for order #{$order->id}, out_trade_no={$outTradeNo}, amount={$totalAmount}");
        Log::info('Alipay PagePay created', ['order_id' => $order->id, 'trade_no' => $outTradeNo]);

        $order->update([
            'payment_method' => 'alipay',
            'payment_intent_id' => $outTradeNo,
        ]);

        return $url;
    }

    /**
     * Create a WAP pay order (alipay.trade.wap.pay).
     * Returns the redirect URL to Alipay cashier.
     */
    public function createWapPay(Order $order): string
    {
        $outTradeNo = $order->id . '_' . time();
        $totalAmount = number_format((float) $order->amount, 2, '.', '');

        $bizContent = json_encode([
            'out_trade_no' => $outTradeNo,
            'total_amount' => $totalAmount,
            'subject' => $order->product->name ?? 'LicenseOS Purchase',
            'product_code' => 'QUICK_WAP_WAY',
        ], JSON_UNESCAPED_UNICODE);

        $params = $this->buildPublicParams('alipay.trade.wap.pay', $bizContent);
        $params['return_url'] = $this->returnUrl;
        $params['notify_url'] = $this->notifyUrl;
        $params['sign'] = $this->generateSign($params);

        $url = $this->gateway . '?' . http_build_query($params);

        error_log("[AlipayService] WapPay URL generated for order #{$order->id}, out_trade_no={$outTradeNo}, amount={$totalAmount}");
        Log::info('Alipay WapPay created', ['order_id' => $order->id, 'trade_no' => $outTradeNo]);

        $order->update([
            'payment_method' => 'alipay',
            'payment_intent_id' => $outTradeNo,
        ]);

        return $url;
    }

    /**
     * Query trade status (alipay.trade.query).
     */
    public function query(string $tradeNo): array
    {
        $bizContent = json_encode([
            'out_trade_no' => $tradeNo,
        ], JSON_UNESCAPED_UNICODE);

        $params = $this->buildPublicParams('alipay.trade.query', $bizContent);
        $params['sign'] = $this->generateSign($params);

        $response = $this->httpPost($this->gateway, http_build_query($params));
        $result = json_decode($response, true);

        error_log("[AlipayService] Query response for trade_no={$tradeNo}: {$response}");

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[AlipayService] Query JSON decode failed for trade_no={$tradeNo}");
            Log::error('Alipay query response JSON decode failed', ['response' => $response]);
            throw new \RuntimeException('Alipay query error: invalid JSON response');
        }

        $responseData = $result['alipay_trade_query_response'] ?? [];
        $tradeStatus = $responseData['trade_status'] ?? '';
        error_log("[AlipayService] Query result for trade_no={$tradeNo}: code=" . ($responseData['code'] ?? '') . ", trade_status={$tradeStatus}");
        Log::info('Alipay query result', ['trade_no' => $tradeNo, 'code' => $responseData['code'] ?? '', 'trade_status' => $tradeStatus]);

        if (($responseData['code'] ?? '') !== '10000') {
            $subMsg = $responseData['sub_msg'] ?? $responseData['msg'] ?? 'Unknown error';
            throw new \RuntimeException("Alipay query error: {$subMsg}");
        }

        return $responseData;
    }

    /**
     * Refund a trade (alipay.trade.refund).
     *
     * @param string $tradeNo  Out trade number
     * @param float  $amount   Refund amount in yuan (e.g. 100.00)
     */
    public function refund(string $tradeNo, float $amount): array
    {
        $refundAmount = number_format($amount, 2, '.', '');

        $bizContent = json_encode([
            'out_trade_no' => $tradeNo,
            'refund_amount' => $refundAmount,
        ], JSON_UNESCAPED_UNICODE);

        $params = $this->buildPublicParams('alipay.trade.refund', $bizContent);
        $params['sign'] = $this->generateSign($params);

        $response = $this->httpPost($this->gateway, http_build_query($params));
        $result = json_decode($response, true);

        error_log("[AlipayService] Refund response for trade_no={$tradeNo}, amount={$refundAmount}: {$response}");

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[AlipayService] Refund JSON decode failed for trade_no={$tradeNo}");
            Log::error('Alipay refund response JSON decode failed', ['response' => $response]);
            throw new \RuntimeException('Alipay refund error: invalid JSON response');
        }

        $responseData = $result['alipay_trade_refund_response'] ?? [];
        error_log("[AlipayService] Refund result for trade_no={$tradeNo}: code=" . ($responseData['code'] ?? '') . ", refund_fee=" . ($responseData['refund_fee'] ?? ''));
        Log::info('Alipay refund result', ['trade_no' => $tradeNo, 'code' => $responseData['code'] ?? '']);

        if (($responseData['code'] ?? '') !== '10000') {
            $subMsg = $responseData['sub_msg'] ?? $responseData['msg'] ?? 'Unknown error';
            throw new \RuntimeException("Alipay refund error: {$subMsg}");
        }

        return $responseData;
    }

    /**
     * Verify async notification signature.
     * Returns the verified params on success, or null on failure.
     */
    public function verifyNotify(array $params): ?array
    {
        error_log('[AlipayService] verifyNotify received params: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        $sign = $params['sign'] ?? '';
        if (empty($sign)) {
            error_log('[AlipayService] verifyNotify: missing sign parameter');
            Log::warning('Alipay notify: missing sign parameter');
            return null;
        }

        // Remove sign and sign_type before verification
        unset($params['sign'], $params['sign_type']);

        if (!$this->verifySign($params, $sign)) {
            error_log('[AlipayService] verifyNotify: signature verification failed for trade_no=' . ($params['out_trade_no'] ?? ''));
            Log::warning('Alipay notify: signature verification failed', [
                'trade_no' => $params['out_trade_no'] ?? '',
            ]);
            return null;
        }

        error_log('[AlipayService] verifyNotify: signature verified for trade_no=' . ($params['out_trade_no'] ?? '') . ', trade_status=' . ($params['trade_status'] ?? ''));
        Log::info('Alipay notify: signature verified', [
            'trade_no' => $params['out_trade_no'] ?? '',
            'trade_status' => $params['trade_status'] ?? '',
        ]);

        return $params;
    }

    // === Signature ===

    /**
     * Generate RSA2 (SHA256WithRSA) signature.
     * Sort params by key ascending, join with &, then sign.
     */
    public function generateSign(array $params): string
    {
        ksort($params);

        $stringToSign = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $stringToSign .= "{$key}={$value}&";
            }
        }
        $stringToSign = rtrim($stringToSign, '&');

        error_log("[AlipayService] String to sign: {$stringToSign}");

        $privateKeyPem = $this->formatPrivateKey($this->privateKey);
        $privateKeyRes = openssl_pkey_get_private($privateKeyPem);

        if ($privateKeyRes === false) {
            $error = openssl_error_string();
            Log::error('Alipay sign: failed to load private key', ['error' => $error]);
            throw new \RuntimeException("Alipay sign error: failed to load private key ({$error})");
        }

        $signature = '';
        $success = openssl_sign($stringToSign, $signature, $privateKeyRes, OPENSSL_ALGO_SHA256);

        if (!$success) {
            $error = openssl_error_string();
            Log::error('Alipay sign: openssl_sign failed', ['error' => $error]);
            throw new \RuntimeException("Alipay sign error: openssl_sign failed ({$error})");
        }

        return base64_encode($signature);
    }

    /**
     * Verify RSA2 signature from Alipay callback.
     */
    public function verifySign(array $params, string $sign): bool
    {
        ksort($params);

        $stringToVerify = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $stringToVerify .= "{$key}={$value}&";
            }
        }
        $stringToVerify = rtrim($stringToVerify, '&');

        error_log("[AlipayService] String to verify: {$stringToVerify}");

        $publicKeyPem = $this->formatPublicKey($this->alipayPublicKey);
        $publicKeyRes = openssl_pkey_get_public($publicKeyPem);

        if ($publicKeyRes === false) {
            $error = openssl_error_string();
            Log::error('Alipay verify: failed to load public key', ['error' => $error]);
            return false;
        }

        $result = openssl_verify($stringToVerify, base64_decode($sign), $publicKeyRes, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    // === Helpers ===

    /**
     * Build common public request parameters.
     */
    protected function buildPublicParams(string $method, string $bizContent): array
    {
        return [
            'app_id' => $this->appId,
            'method' => $method,
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => $bizContent,
        ];
    }

    /**
     * Format private key to PEM format.
     * Handles both raw Base64 and already-formatted PEM keys.
     */
    protected function formatPrivateKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // Remove any whitespace/newlines from raw key
        $key = preg_replace('/\s+/', '', $key);

        return "-----BEGIN RSA PRIVATE KEY-----\n"
            . chunk_split($key, 64, "\n")
            . "-----END RSA PRIVATE KEY-----";
    }

    /**
     * Format public key to PEM format.
     * Handles both raw Base64 and already-formatted PEM keys.
     */
    protected function formatPublicKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        $key = preg_replace('/\s+/', '', $key);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($key, 64, "\n")
            . "-----END PUBLIC KEY-----";
    }

    /**
     * Send HTTP POST request.
     */
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
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Alipay HTTP error: {$error}");
        }
        curl_close($ch);

        error_log("[AlipayService] Response: {$result}");

        return $result ?: '';
    }
}
