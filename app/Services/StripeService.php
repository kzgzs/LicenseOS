<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\SiteSetting;

class StripeService
{
    public function __construct()
    {
        $secret = config('services.stripe.secret');
        if ($secret) {
            \Stripe\Stripe::setApiKey($secret);
        }
    }

    /**
     * Check if Stripe is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.stripe.secret'));
    }

    /**
     * Get the store's base currency (default: USD).
     */
    protected function getStoreCurrency(): string
    {
        return strtolower(SiteSetting::get('store_currency', 'CNY'));
    }

    /**
     * Get the exchange rate (how much 1 USD = in base currency).
     * Used to convert USD-denominated Stripe amounts to store currency.
     */
    protected function getExchangeRate(): float
    {
        return (float) SiteSetting::get('exchange_rate', '1.00');
    }

    /**
     * Convert store currency amount to USD cents for Stripe.
     * Stripe always charges in the currency's smallest unit.
     */
    protected function toStripeAmount(float $storeAmount): int
    {
        $currency = $this->getStoreCurrency();
        $rate = $this->getExchangeRate();

        // Convert store amount to USD if needed
        $usdAmount = $rate > 0 ? $storeAmount / $rate : $storeAmount;

        // Zero-decimal currencies (JPY, etc.) don't multiply by 100
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
        if (in_array($currency, $zeroDecimal)) {
            return (int) round($usdAmount);
        }

        return (int) round($usdAmount * 100);
    }

    /**
     * Create a Stripe Checkout Session with Connect transfer (platform takes a cut).
     */
    public function createCheckoutSession(Order $order, Tenant $developer): array
    {
        $product = $order->product;
        $currency = $this->getStoreCurrency();
        $platformFeeRate = $developer->commission_rate / 100;
        $stripeAmount = $this->toStripeAmount((float) $order->amount);
        $applicationFeeAmount = (int) round($stripeAmount * $platformFeeRate);

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $product->name,
                        'description' => $product->description ?? null,
                    ],
                    'unit_amount' => $stripeAmount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url') . '/checkout/cancel',
            'metadata' => [
                'order_id' => $order->id,
                'tenant_id' => $developer->id,
                'product_id' => $product->id,
            ],
        ];

        // Stripe Connect: transfer to developer, platform takes application fee
        if ($developer->stripe_account_id) {
            $params['payment_intent_data'] = [
                'application_fee_amount' => $applicationFeeAmount,
                'transfer_data' => [
                    'destination' => $developer->stripe_account_id,
                ],
            ];
        }

        $session = \Stripe\Checkout\Session::create($params);

        $order->update([
            'checkout_session_id' => $session->id,
            'payment_method' => 'stripe',
        ]);

        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    /**
     * Retrieve a checkout session status.
     */
    public function retrieveSession(string $sessionId): ?\Stripe\Checkout\Session
    {
        return \Stripe\Checkout\Session::retrieve($sessionId);
    }

    /**
     * Create a Connect Account link for developer onboarding.
     */
    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        $accountLink = \Stripe\AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    /**
     * Create a connected Stripe account for a developer.
     */
    public function createConnectedAccount(string $email, string $country = 'US'): string
    {
        $account = \Stripe\Account::create([
            'type' => 'express',
            'country' => $country,
            'email' => $email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        return $account->id;
    }

    /**
     * Create a PaymentIntent with manual capture for freelance escrow.
     * Funds are authorized (held) but not captured until milestone is approved.
     */
    public function createEscrowPaymentIntent(float $amount, string $currency = 'CNY'): \Stripe\PaymentIntent
    {
        $stripeAmount = $this->toStripeAmount($amount);
        $stripeCurrency = $this->getStoreCurrency();

        return \Stripe\PaymentIntent::create([
            'amount' => $stripeAmount,
            'currency' => $stripeCurrency,
            'capture_method' => 'manual',
            'description' => 'Freelance Escrow',
            'metadata' => [
                'type' => 'freelance_escrow',
            ],
        ]);
    }

    /**
     * Capture a previously authorized PaymentIntent (release escrow funds).
     */
    public function capturePaymentIntent(string $paymentIntentId): ?\Stripe\PaymentIntent
    {
        try {
            return \Stripe\PaymentIntent::retrieve($paymentIntentId)->capture();
        } catch (\Exception $e) {
            \Log::error('Stripe capture failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancel/refund an uncaptured PaymentIntent (escrow refund).
     */
    public function cancelPaymentIntent(string $paymentIntentId): ?\Stripe\PaymentIntent
    {
        try {
            return \Stripe\PaymentIntent::retrieve($paymentIntentId)->cancel();
        } catch (\Exception $e) {
            \Log::error('Stripe cancel failed: ' . $e->getMessage());
            return null;
        }
    }
}
