<?php

namespace App\Services\Gateways;

class AdyenGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array
    {
        return [
            'gateway' => 'adyen',
            'payment_id' => 'ady_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'success',
        ];
    }

    public function refund(string $paymentId, float $amount): array
    {
        return [
            'gateway' => 'adyen',
            'refund_id' => 'ady_re_' . uniqid(),
            'amount' => $amount,
            'status' => 'refunded',
        ];
    }

    public function createCustomer(array $data): string
    {
        return 'ady_cus_' . uniqid();
    }

    public function getSupportedCurrencies(): array
    {
        return ['EUR', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN'];
    }

    public function getSupportedRegions(): array
    {
        return ['EU', 'CH', 'GB', 'NO', 'SE', 'DK'];
    }
}
