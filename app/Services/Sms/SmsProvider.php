<?php

namespace App\Services\Sms;

interface SmsProvider
{
    /**
     * Send an SMS message.
     *
     * @param string $phone Phone number (e.g. 13800138000)
     * @param string $templateId Template ID from the provider
     * @param array $params Template variables (key-value)
     * @return bool Success or failure
     */
    public function send(string $phone, string $templateId, array $params = []): bool;
}
