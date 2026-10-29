<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id', 'role', 'avatar', 'bio', 'phone',
        'account_type', 'company_name', 'business_license',
        'verification_status', 'verification_note', 'verified_at',
        'tier', 'developer_level', 'platform_tier_id', 'is_member',
        'membership_expires_at', 'enterprise_account_id', 'affiliate_code',
        'affiliate_balance', 'affiliate_total_earned', 'affiliate_total_referrals',
        // V4: Global enterprise fields
        'locale', 'country_code', 'vat_id', 'timezone',
        // WeChat Open Platform
        'wechat_openid', 'wechat_unionid', 'wechat_nickname',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_member' => 'boolean',
        'membership_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function licenses() { return $this->hasMany(License::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function tickets() { return $this->hasMany(Ticket::class); }
    public function postedProjects() { return $this->hasMany(Project::class, 'client_id'); }
    public function projectBids() { return $this->hasMany(ProjectBid::class, 'developer_id'); }
    public function projectContractsAsClient() { return $this->hasMany(ProjectContract::class, 'client_id'); }
    public function projectContractsAsDeveloper() { return $this->hasMany(ProjectContract::class, 'developer_id'); }
    public function badges() { return $this->hasMany(DeveloperBadge::class); }
    public function skills() { return $this->hasMany(DeveloperSkill::class); }
    public function timeLogs() { return $this->hasMany(ProjectTimeLog::class, 'developer_id'); }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isModerator(): bool { return $this->role === 'moderator'; }
    public function isDeveloper(): bool { return $this->role === 'developer'; }
    public function isCustomer(): bool { return $this->role === 'customer'; }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->isModerator();
    }

    // Enterprise verification
    public function isCompany(): bool { return $this->account_type === 'company'; }
    public function isVerified(): bool { return $this->verification_status === 'approved'; }
    public function isPendingVerification(): bool { return $this->verification_status === 'pending'; }

    // V3.4 Tier
    public function platformTier() { return $this->belongsTo(PlatformTier::class); }
    public function isMemberActive(): bool { return $this->is_member && $this->membership_expires_at?->isFuture(); }
    public function isPremium(): bool { return $this->tier === 'premium'; }

    // V4: Global enterprise
    public function isEU(): bool { return in_array($this->country_code, ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE']); }
    public function getLocaleOrDefault(): string { return $this->locale ?: 'en'; }

    // Affiliate
    public function affiliateCommissions() { return $this->hasMany(Commission::class, 'affiliate_user_id'); }
    public function referredOrders() { return $this->hasMany(Order::class, 'referred_by'); }

    public function getAffiliateLink(): string
    {
        return url('/?ref=' . $this->affiliate_code);
    }

    public function getAffiliateEarnings(): float
    {
        return round((float) $this->affiliateCommissions()->sum('affiliate_fee'), 2);
    }

    public function getAffiliateReferralCount(): int
    {
        return $this->referredOrders()->where('status', 'paid')->distinct('user_id')->count('user_id');
    }
}
