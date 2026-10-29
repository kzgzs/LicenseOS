<?php

namespace App\Services\Gateways;

class RazorpayGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array
    {
        return [
            'gateway' => 'razorpay',
            'payment_id' => 'pay_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'success',
        ];
    }

    public function refund(string $paymentId, float $amount): array
    {
        return [
            'gateway' => 'razorpay',
            'refund_id' => 'rfnd_' . uniqid(),
            'amount' => $amount,
            'status' => 'refunded',
        ];
    }

    public function createCustomer(array $data): string
    {
        return 'cust_' . uniqid();
    }

    public function getSupportedCurrencies(): array
    {
        return ['INR'];
    }

    public function getSupportedRegions(): array
    {
        return ['IN'];
    }
}
