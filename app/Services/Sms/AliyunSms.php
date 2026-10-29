<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Alibaba Cloud SMS (阿里云短信)
 *
 * API: https://dysmsapi.aliyuncs.com
 * Config required:
 *   - SMS_ALIYUN_ACCESS_KEY_ID: AccessKey ID
 *   - SMS_ALIYUN_ACCESS_KEY_SECRET: AccessKey Secret
 *   - SMS_ALIYUN_SIGN_NAME: SMS signature (短信签名)
 */
class AliyunSms implements SmsProvider
{
    protected string $accessKeyId;
    protected string $accessKeySecret;
    protected string $signName;

    public function __construct(string $accessKeyId, string $accessKeySecret, string $signName)
    {
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->signName = $signName;
    }

    public function send(string $phone, string $templateId, array $params = []): bool
    {
        $requestParams = [
            'Action' => 'SendSms',
            'PhoneNumbers' => $phone,
            'SignName' => $this->signName,
            'TemplateCode' => $templateId,
            'TemplateParam' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'AccessKeyId' => $this->accessKeyId,
            'Format' => 'JSON',
            'RegionId' => 'cn-hangzhou',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => uniqid((string) mt_rand(), true),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25',
        ];

        $requestParams['Signature'] = $this->sign($requestParams);

        try {
            $response = $this->httpPost('https://dysmsapi.aliyuncs.com/', $requestParams);
            $result = json_decode($response, true);

            if (($result['Code'] ?? '') === 'OK') {
                Log::info('Aliyun SMS sent successfully', ['phone' => $phone, 'template' => $templateId]);
                return true;
            }

            Log::warning('Aliyun SMS failed', [
                'code' => $result['Code'] ?? 'unknown',
                'message' => $result['Message'] ?? 'unknown',
                'phone' => $phone,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Aliyun SMS error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HMAC-SHA1 signature per Alibaba Cloud API spec.
     */
    protected function sign(array $params): string
    {
        ksort($params);
        $canonicalized = '';
        foreach ($params as $key => $value) {
            $canonicalized .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $stringToSign = 'GET&' . $this->percentEncode('/') . '&' . $this->percentEncode(substr($canonicalized, 1));
        return base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
    }

    protected function percentEncode(string $str): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($str));
    }

    protected function httpPost(string $url, array $data): string
    {
        $query = http_build_query($data);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Aliyun SMS HTTP error: {$error}");
        }
        curl_close($ch);
        return $result ?: '';
    }
}
