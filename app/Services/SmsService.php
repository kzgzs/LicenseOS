<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Services\Sms\AliyunSms;
use App\Services\Sms\SmsProvider;
use App\Services\Sms\TencentSms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Unified SMS Service.
 *
 * Reads configuration from SiteSetting and instantiates the correct provider.
 * Supported providers: aliyun, tencent
 */
class SmsService
{
    protected ?SmsProvider $provider = null;

    /**
     * Check if SMS is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return SiteSetting::get('sms_enabled', '0') === '1'
            && $this->getProvider() !== null;
    }

    /**
     * Get the active SMS provider.
     */
    public function getProvider(): ?SmsProvider
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $providerType = SiteSetting::get('sms_provider', 'aliyun');

        $this->provider = match ($providerType) {
            'aliyun' => $this->buildAliyun(),
            'tencent' => $this->buildTencent(),
            default => null,
        };

        return $this->provider;
    }

    /**
     * Send an SMS message.
     */
    public function send(string $phone, string $templateId, array $params = []): bool
    {
        $provider = $this->getProvider();
        if (!$provider) {
            Log::warning('SMS provider not configured');
            return false;
        }

        return $provider->send($phone, $templateId, $params);
    }

    /**
     * Generate and send a verification code.
     */
    public function sendVerificationCode(string $phone): string
    {
        if (SiteSetting::get('sms_tpl_verify_code_enabled', '1') !== '1') {
            throw new \RuntimeException('Verification code SMS is disabled.');
        }

        $code = (string) random_int(100000, 999999);
        $templateId = SiteSetting::get('sms_tpl_verify_code', '');

        if (!$templateId) {
            throw new \RuntimeException('Verification code SMS template not configured.');
        }

        // Store code in cache for 5 minutes
        Cache::put("sms_code:{$phone}", $code, now()->addMinutes(5));

        $sent = $this->send($phone, $templateId, ['code' => $code]);

        if (!$sent) {
            throw new \RuntimeException('Failed to send verification code SMS.');
        }

        return $code;
    }

    /**
     * Verify a SMS verification code.
     */
    public function verifyCode(string $phone, string $code): bool
    {
        $cached = Cache::get("sms_code:{$phone}");
        if (!$cached) {
            return false;
        }

        if ($cached !== $code) {
            return false;
        }

        Cache::forget("sms_code:{$phone}");
        return true;
    }

    /**
     * Send order payment success SMS.
     */
    public function sendOrderPaid(string $phone, string $orderNum): bool
    {
        if (SiteSetting::get('sms_tpl_order_paid_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_order_paid', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, ['ordernum' => $orderNum]);
    }

    /**
     * Send order delivery/license notification SMS.
     */
    public function sendOrderShipped(string $phone, string $orderNum, string $expressCom = '', string $expressNo = ''): bool
    {
        if (SiteSetting::get('sms_tpl_order_shipped_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_order_shipped', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, [
            'ordernum' => $orderNum,
            'express_com' => $expressCom,
            'express_no' => $expressNo,
        ]);
    }

    /**
     * Send refund success SMS.
     */
    public function sendRefundSuccess(string $phone, string $orderNum, float $money): bool
    {
        if (SiteSetting::get('sms_tpl_refund_success_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_refund_success', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, [
            'ordernum' => $orderNum,
            'money' => number_format($money, 2),
        ]);
    }

    /**
     * Send refund rejected SMS.
     */
    public function sendRefundRejected(string $phone, string $orderNum, string $reason): bool
    {
        if (SiteSetting::get('sms_tpl_refund_rejected_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_refund_rejected', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, [
            'ordernum' => $orderNum,
            'reason' => $reason,
        ]);
    }

    /**
     * Send payout success SMS.
     */
    public function sendPayoutSuccess(string $phone, float $money): bool
    {
        if (SiteSetting::get('sms_tpl_payout_success_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_payout_success', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, [
            'money' => number_format($money, 2),
        ]);
    }

    /**
     * Send payout failed SMS.
     */
    public function sendPayoutFailed(string $phone, string $reason): bool
    {
        if (SiteSetting::get('sms_tpl_payout_failed_enabled', '1') !== '1') {
            return false;
        }
        $templateId = SiteSetting::get('sms_tpl_payout_failed', '');
        if (!$templateId) {
            return false;
        }
        return $this->send($phone, $templateId, [
            'reason' => $reason,
        ]);
    }

    protected function buildAliyun(): ?AliyunSms
    {
        $keyId = SiteSetting::get('sms_aliyun_access_key_id', '');
        $keySecret = SiteSetting::get('sms_aliyun_access_key_secret', '');
        $signName = SiteSetting::get('sms_aliyun_sign_name', '');

        if (empty($keyId) || empty($keySecret) || empty($signName)) {
            return null;
        }

        return new AliyunSms($keyId, $keySecret, $signName);
    }

    protected function buildTencent(): ?TencentSms
    {
        $secretId = SiteSetting::get('sms_tencent_secret_id', '');
        $secretKey = SiteSetting::get('sms_tencent_secret_key', '');
        $sdkAppId = SiteSetting::get('sms_tencent_sdk_appid', '');
        $signName = SiteSetting::get('sms_tencent_sign_name', '');

        if (empty($secretId) || empty($secretKey) || empty($sdkAppId) || empty($signName)) {
            return null;
        }

        return new TencentSms($secretId, $secretKey, $sdkAppId, $signName);
    }
}
