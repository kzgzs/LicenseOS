<?php

namespace App\Services\Gateways;

interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): array;
    public function refund(string $paymentId, float $amount): array;
    public function createCustomer(array $data): string;
    public function getSupportedCurrencies(): array;
    public function getSupportedRegions(): array;
}
