<?php

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\PlatformEvent;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function __construct(
        protected StripeService $stripe,
        protected WechatPayService $wechat,
        protected AlipayService $alipay,
        protected CommissionService $commission,
        protected LicenseService $license,
    ) {}

    /**
     * Check which payment methods are available.
     */
    public function availableMethods(): array
    {
        $methods = [];
        if ($this->stripe->isConfigured()) {
            $methods[] = 'stripe';
        }
        if ($this->wechat->isConfigured()) {
            $methods[] = 'wechat';
        }
        if ($this->alipay->isConfigured()) {
            $methods[] = 'alipay';
        }
        return $methods;
    }

    /**
     * Create an order and initiate checkout.
     *
     * @param string $tierType  One of: trial, 1_month, 3_months, 6_months, 1_year, lifetime
     * @param string|null $domain  Domain to bind the license to
     */
    public function createOrderAndCheckout(Product $product, ?int $userId = null, string $method = 'stripe', string $tierType = '', ?string $domain = null): array
    {
        // Resolve tier from product pricing_tiers
        $tier = null;
        if ($tierType && $product->pricing_tiers) {
            $tier = $product->findTier($tierType);
        }
        if (!$tier) {
            // Fallback to legacy price + 1 year
            $tier = [
                'type' => '1_year', 'months' => 12, 'days' => 365,
                'price' => (float) $product->price, 'label' => '年付',
            ];
        }

        $amount = (float) ($tier['price'] ?? $product->price);
        $tierMonths = (int) ($tier['months'] ?? 12);
        $tierDays = $tier['type'] === 'trial' ? (int) ($tier['days'] ?? 7) : null;
        $tierLabel = $tier['label'] ?? '';
        $isTrial = $tier['type'] === 'trial';

        // Get affiliate referral from cookie
        $referredBy = (int) request()->cookie('affiliate_ref', 0) ?: null;
        // Don't attribute self-referrals
        if ($referredBy && $referredBy === $userId) {
            $referredBy = null;
        }

        // If amount is 0 (free trial), create paid order directly
        if ($amount <= 0) {
            $order = Order::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'user_id' => $userId,
                'referred_by' => $referredBy,
                'amount' => 0,
                'status' => 'pending',
                'payment_method' => 'free',
                'tier_months' => $tierMonths,
                'tier_days' => $tierDays,
                'tier_label' => $tierLabel,
                'domain' => $domain,
                'is_trial' => $isTrial,
            ]);
            // confirmPayment will set status=paid and fire OrderPaid event
            $this->confirmPayment($order, 'free_trial_' . $order->id);
            return [
                'order' => $order,
                'checkout_url' => route('checkout.success', ['session_id' => $order->id]),
                'session_id' => $order->id,
            ];
        }

        // Check payment gateway
        if ($method === 'stripe' && !$this->stripe->isConfigured()) {
            throw new \RuntimeException('Stripe payment is not configured. Please contact the administrator.');
        }
        if ($method === 'wechat' && !$this->wechat->isConfigured()) {
            throw new \RuntimeException('WeChat Pay is not configured. Please contact the administrator.');
        }
        if ($method === 'alipay' && !$this->alipay->isConfigured()) {
            throw new \RuntimeException('Alipay is not configured. Please contact the administrator.');
        }

        $order = Order::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'user_id' => $userId,
            'referred_by' => $referredBy,
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => $method,
            'tier_months' => $tierMonths,
            'tier_days' => $tierDays,
            'tier_label' => $tierLabel,
            'domain' => $domain,
            'is_trial' => $isTrial,
        ]);

        if ($method === 'wechat') {
            $result = $this->wechat->createNativeOrder($order);
            return [
                'order' => $order,
                'checkout_url' => $result['code_url'],
                'session_id' => $order->id,
            ];
        }

        if ($method === 'alipay') {
            // Detect mobile via User-Agent to choose WapPay or PagePay
            $userAgent = request()->userAgent() ?? '';
            $isMobile = (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent);
            $url = $isMobile ? $this->alipay->createWapPay($order) : $this->alipay->createPagePay($order);
            return [
                'order' => $order,
                'checkout_url' => $url,
                'session_id' => $order->id,
            ];
        }

        // Default: Stripe
        $session = $this->stripe->createCheckoutSession($order, $product->tenant);

        return [
            'order' => $order,
            'checkout_url' => $session['url'],
            'session_id' => $session['id'],
        ];
    }

    /**
     * Handle a successful payment (from webhook or manual confirmation).
     */
    public function confirmPayment(Order $order, ?string $paymentIntentId = null): void
    {
        if ($order->status === 'paid') {
            return;
        }

        $order->update([
            'status' => 'paid',
            'payment_intent_id' => $paymentIntentId ?? $order->payment_intent_id,
        ]);

        // V2.1 legacy: PlatformEvent for audit trail
        PlatformEvent::dispatch('order.paid', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'product_id' => $order->product_id,
            'amount' => (float) $order->amount,
        ]);

        // V3.1: Fire Laravel event — triggers async license generation,
        // commission calculation, and stats update via Listeners
        OrderPaid::dispatch($order);

        // Send order confirmation email
        $this->sendOrderConfirmationEmail($order);

        // Send SMS notification if enabled
        $this->sendOrderPaidSms($order);
    }

    /**
     * Send order confirmation email to the buyer.
     */
    protected function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            $order->load('user', 'product', 'licenses');
            $user = $order->user;

            if (!$user || !$user->email) {
                return;
            }

            $siteName = SiteSetting::get('site_name', config('app.name', 'Platform'));
            $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@' . parse_url(config('app.url'), PHP_URL_HOST));
            $fromName = env('MAIL_FROM_NAME', $siteName);

            Mail::send('emails.order-paid', ['order' => $order], function ($message) use ($user, $order, $fromAddress, $fromName, $siteName) {
                $message->from($fromAddress, $fromName)
                    ->to($user->email)
                    ->subject("[{$siteName}] Order #{$order->id} Confirmed");
            });

            Log::info('Order confirmation email sent', ['order_id' => $order->id, 'email' => $user->email]);
        } catch (\Throwable $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Send SMS notification for paid order if SMS is enabled.
     */
    protected function sendOrderPaidSms(Order $order): void
    {
        try {
            $smsEnabled = SiteSetting::get('sms_enabled', '0');
            if ($smsEnabled !== '1') {
                return;
            }

            $order->load('user');
            $phone = $order->user->phone ?? null;
            if (!$phone) {
                return;
            }

            $smsService = app(SmsService::class);
            $smsService->sendOrderPaid($phone, (string) $order->id);

            Log::info('Order paid SMS sent', ['order_id' => $order->id, 'phone' => $phone]);
        } catch (\Throwable $e) {
            Log::error('Failed to send order paid SMS: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Handle simulated payment (for testing / WeChat / Alipay callbacks).
     */
    public function handleSimulatedPayment(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $this->confirmPayment($order, 'simulated_' . $orderId);
    }

    /**
     * Handle WeChat Pay callback.
     */
    public function handleWechatCallback(array $data): void
    {
        $outTradeNo = $data['out_trade_no'] ?? null;
        // Strip timestamp suffix: "123_1718000000" → "123"
        $orderId = $outTradeNo ? explode('_', $outTradeNo)[0] : null;

        $order = Order::where('payment_intent_id', $data['transaction_id'] ?? null)
            ->orWhere('id', $orderId)
            ->first();

        if (!$order || $order->status === 'paid') {
            return;
        }

        $this->confirmPayment($order, $data['transaction_id'] ?? null);
    }

    /**
     * Handle Alipay callback.
     */
    public function handleAlipayCallback(array $data): void
    {
        // out_trade_no format: "orderId_timestamp", strip suffix to get order ID
        $outTradeNo = $data['out_trade_no'] ?? null;
        $orderId = $outTradeNo ? explode('_', $outTradeNo)[0] : null;
        // Alipay's trade_no is the actual Alipay transaction ID
        $alipayTradeNo = $data['trade_no'] ?? null;

        $order = Order::where('payment_intent_id', $outTradeNo)
            ->orWhere('id', $orderId)
            ->first();

        if (!$order || $order->status === 'paid') {
            return;
        }

        $this->confirmPayment($order, $alipayTradeNo ?? $outTradeNo);
    }
}
