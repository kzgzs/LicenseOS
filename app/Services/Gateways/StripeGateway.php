<?php

namespace App\Services\Gateways;

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array
    {
        // In production, integrate with Stripe SDK
        return [
            'gateway' => 'stripe',
            'payment_id' => 'pi_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'success',
        ];
    }

    public function refund(string $paymentId, float $amount): array
    {
        return [
            'gateway' => 'stripe',
            'refund_id' => 're_' . uniqid(),
            'amount' => $amount,
            'status' => 'refunded',
        ];
    }

    public function createCustomer(array $data): string
    {
        return 'cus_' . uniqid();
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];
    }

    public function getSupportedRegions(): array
    {
        return ['US', 'EU', 'CA', 'AU', 'GB'];
    }
}
