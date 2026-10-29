<?php

namespace App\Services;

use App\Models\License;
use App\Models\Order;
use App\Models\Activation;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

class LicenseService
{
    /**
     * Generate a license key for a paid order.
     */
    public function generateForOrder(Order $order): License
    {
        // Prevent duplicate licenses for the same order
        $existing = License::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        // Determine expire_at based on tier
        $tierMonths = (int) ($order->tier_months ?? 12);
        $tierDays = $order->tier_days; // for trial (7/15 days)

        // Check for existing active license to extend (stack remaining days)
        // Paid orders extend ANY active license (including trial → paid upgrade, no days wasted)
        // Trial orders only extend non-trial licenses (each domain trial gets its own)
        $query = License::where('product_id', $order->product_id)
            ->where('tenant_id', $order->tenant_id)
            ->where('status', 'active')
            ->whereNotNull('expire_at')
            ->where('expire_at', '>', now());

        if ($order->is_trial) {
            $query->where('is_trial', false);
        }

        $existingLicense = $query->latest('expire_at')->first();

        $baseDate = $existingLicense ? $existingLicense->expire_at : now();

        if ($tierMonths === 0 && !$tierDays) {
            $expireAt = null; // Lifetime
        } elseif ($tierDays) {
            $expireAt = $baseDate->copy()->addDays($tierDays); // Trial or day-based
        } else {
            $expireAt = $baseDate->copy()->addMonths($tierMonths); // Standard
        }

        // Extend existing license (same key, same domain activations, seamless renewal)
        if ($existingLicense) {
            $existingLicense->update([
                'expire_at' => $expireAt,
                'is_trial' => $order->is_trial ? $existingLicense->is_trial : false, // upgrade trial→paid
            ]);

            // Pre-bind domain if provided and not already activated
            if ($order->domain) {
                $alreadyActivated = Activation::where('license_id', $existingLicense->id)
                    ->where('domain', $order->domain)
                    ->exists();
                if (!$alreadyActivated) {
                    Activation::create([
                        'license_id' => $existingLicense->id,
                        'domain' => $order->domain,
                        'ip' => null,
                        'last_check' => now(),
                    ]);
                }
            }

            return $existingLicense;
        }

        // No existing license — create a new one
        $license = License::create([
            'tenant_id' => $order->tenant_id,
            'product_id' => $order->product_id,
            'order_id' => $order->id,
            'license_key' => $this->generateKey(),
            'status' => 'active',
            'is_trial' => (bool) $order->is_trial,
            'max_domains' => 1,
            'expire_at' => $expireAt,
        ]);

        // Pre-bind domain if provided
        if ($order->domain) {
            Activation::create([
                'license_id' => $license->id,
                'domain' => $order->domain,
                'ip' => null,
                'last_check' => now(),
            ]);
        }

        return $license;
    }

    /**
     * Activate a license on a domain.
     */
    public function activate(string $licenseKey, string $domain, ?string $ip = null): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['status' => 'invalid', 'message' => 'License key not found.'];
        }

        if ($license->status !== 'active') {
            return ['status' => 'inactive', 'message' => 'License is ' . $license->status . '.'];
        }

        if ($license->expire_at && $license->expire_at->isPast()) {
            $license->update(['status' => 'expired']);
            return ['status' => 'expired', 'message' => 'License has expired.'];
        }

        // Check if domain already activated
        $existingActivation = Activation::where('license_id', $license->id)
            ->where('domain', $domain)
            ->first();

        if ($existingActivation) {
            // Re-activation resets tamper flag — if still tampered, IntegrityController will re-mark
            $existingActivation->update([
                'last_check'    => now(),
                'ip'            => $ip,
                'is_tampered'   => false,
                'tampered_at'   => null,
            ]);

            return [
                'status' => 'active',
                'message' => 'Domain already activated.',
                'max_domains' => $license->max_domains,
                'expire_at' => $license->expire_at?->toIso8601String(),
                'is_trial' => $license->is_trial,
            ];
        }

        $count = Activation::where('license_id', $license->id)->count();

        if ($count >= $license->max_domains) {
            return ['status' => 'limit_reached', 'message' => 'Maximum domain limit reached.'];
        }

        Activation::create([
            'license_id' => $license->id,
            'domain' => $domain,
            'ip' => $ip,
            'last_check' => now(),
        ]);

        return [
            'status' => 'active',
            'message' => 'License activated successfully.',
            'max_domains' => $license->max_domains,
            'expire_at' => $license->expire_at?->utc()->toIso8601String(),
            'is_trial' => $license->is_trial,
        ];
    }

    /**
     * Check license status.
     */
    public function check(string $licenseKey): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['status' => 'invalid'];
        }

        // Auto-expire if past expiration date
        if ($license->status === 'active' && $license->expire_at && $license->expire_at->isPast()) {
            $license->update(['status' => 'expired']);
        }

        // 实时查询激活记录的篡改状态（任意一条激活记录被标记即为篡改）
        $isTampered = Activation::where('license_id', $license->id)
            ->where('is_tampered', true)
            ->exists();

        return [
            'status'         => $license->status === 'active' ? 'valid' : $license->status,
            'expire_at'      => $license->expire_at?->utc()->toIso8601String(),
            'max_domains'    => $license->max_domains,
            'activations'    => $license->activations()->count(),
            'is_trial'       => $license->is_trial,
            'is_tampered'    => $isTampered,
            'tamper_warning' => $isTampered
                ? '您的域名已被标记为代码篡改，请联系插件开发者获取正版授权。'
                : null,
            'site_name'      => SiteSetting::get('site_name', 'LicenseOS'),
        ];
    }

    /**
     * Generate a formatted license key: XXXX-XXXX-XXXX-XXXX
     */
    public function generateKey(): string
    {
        return strtoupper(
            Str::random(4) . '-' .
            Str::random(4) . '-' .
            Str::random(4) . '-' .
            Str::random(4)
        );
    }
}
