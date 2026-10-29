<?php

namespace App\Services;

use App\Services\Gateways\AdyenGateway;
use App\Services\Gateways\PaymentGatewayInterface;
use App\Services\Gateways\RazorpayGateway;
use App\Services\Gateways\StripeGateway;

class PaymentGatewayService
{
    protected array $gateways = [];

    public function __construct()
    {
        $this->gateways = [
            'stripe' => new StripeGateway(),
            'adyen' => new AdyenGateway(),
            'razorpay' => new RazorpayGateway(),
        ];
    }

    /**
     * Route payment to the appropriate gateway based on region/currency.
     */
    public function getGateway(string $countryCode, string $currency): PaymentGatewayInterface
    {
        $gatewayName = $this->resolveGateway($countryCode, $currency);
        return $this->gateways[$gatewayName] ?? $this->gateways['stripe'];
    }

    /**
     * Determine gateway based on region.
     */
    protected function resolveGateway(string $countryCode, string $currency): string
    {
        // India -> Razorpay
        if ($countryCode === 'IN' || $currency === 'INR') {
            return 'razorpay';
        }

        // EU -> Adyen
        if (in_array($countryCode, ['DE', 'FR', 'NL', 'BE', 'AT', 'IT', 'ES', 'PT', 'IE', 'FI', 'SE', 'DK', 'NO', 'CH', 'PL']) || $currency === 'EUR') {
            return 'adyen';
        }

        // Default -> Stripe (US, UK, etc.)
        return 'stripe';
    }

    /**
     * Process a charge through the appropriate gateway.
     */
    public function charge(string $countryCode, string $currency, float $amount, array $options = []): array
    {
        $gateway = $this->getGateway($countryCode, $currency);
        return $gateway->charge($amount, $currency, $options);
    }

    /**
     * Process a refund through a specific gateway.
     */
    public function refund(string $gatewayName, string $paymentId, float $amount): array
    {
        $gateway = $this->gateways[$gatewayName] ?? $this->gateways['stripe'];
        return $gateway->refund($paymentId, $amount);
    }

    /**
     * Get all available gateways.
     */
    public function getAvailableGateways(): array
    {
        return array_keys($this->gateways);
    }
}
