<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Tencent Cloud SMS (腾讯云短信)
 *
 * API: https://sms.tencentcloudapi.com
 * Config required:
 *   - SMS_TENCENT_SECRET_ID: SecretId
 *   - SMS_TENCENT_SECRET_KEY: SecretKey
 *   - SMS_TENCENT_SDK_APPID: SDK AppID
 *   - SMS_TENCENT_SIGN_NAME: SMS signature (短信签名)
 */
class TencentSms implements SmsProvider
{
    protected string $secretId;
    protected string $secretKey;
    protected string $sdkAppId;
    protected string $signName;

    public function __construct(string $secretId, string $secretKey, string $sdkAppId, string $signName)
    {
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->sdkAppId = $sdkAppId;
        $this->signName = $signName;
    }

    public function send(string $phone, string $templateId, array $params = []): bool
    {
        $host = 'sms.tencentcloudapi.com';
        $service = 'sms';
        $version = '2021-01-11';
        $action = 'SendSms';
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);

        $payload = json_encode([
            'SmsSdkAppId' => $this->sdkAppId,
            'SignName' => $this->signName,
            'TemplateId' => $templateId,
            'TemplateParamSet' => array_values(array_map('strval', $params)),
            'PhoneNumberSet' => ['+86' . $phone],
        ], JSON_UNESCAPED_UNICODE);

        // TC3-HMAC-SHA256 signature
        $headers = $this->buildHeaders($host, $service, $date, $timestamp, $payload);

        try {
            $response = $this->httpPost("https://{$host}", $payload, $headers);
            $result = json_decode($response, true);

            $error = $result['Response']['Error'] ?? null;
            if ($error) {
                Log::warning('Tencent SMS failed', [
                    'code' => $error['Code'] ?? 'unknown',
                    'message' => $error['Message'] ?? 'unknown',
                    'phone' => $phone,
                ]);
                return false;
            }

            Log::info('Tencent SMS sent successfully', ['phone' => $phone, 'template' => $templateId]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Tencent SMS error: ' . $e->getMessage());
            return false;
        }
    }

    protected function buildHeaders(string $host, string $service, string $date, int $timestamp, string $payload): array
    {
        $contentType = 'application/json; charset=utf-8';

        // Step 1: Canonical request
        $httpRequestMethod = 'POST';
        $canonicalUri = '/';
        $canonicalQueryString = '';
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-tc-action:SendSms\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $hashedPayload = hash('SHA256', $payload);
        $canonicalRequest = "{$httpRequestMethod}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

        // Step 2: String to sign
        $algorithm = 'TC3-HMAC-SHA256';
        $credentialScope = "{$date}/{$service}/tc3_request";
        $hashedCanonicalRequest = hash('SHA256', $canonicalRequest);
        $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n{$hashedCanonicalRequest}";

        // Step 3: Signature
        $secretDate = hash_hmac('SHA256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('SHA256', $service, $secretDate, true);
        $secretSigning = hash_hmac('SHA256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('SHA256', $stringToSign, $secretSigning);

        // Step 4: Authorization header
        $authorization = "{$algorithm} Credential={$this->secretId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Authorization' => $authorization,
            'Content-Type' => $contentType,
            'Host' => $host,
            'X-TC-Action' => 'SendSms',
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Version' => '2021-01-11',
            'X-TC-Region' => 'ap-guangzhou',
        ];
    }

    protected function httpPost(string $url, string $body, array $headers): string
    {
        $ch = curl_init();
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Tencent SMS HTTP error: {$error}");
        }
        curl_close($ch);
        return $result ?: '';
    }
}
