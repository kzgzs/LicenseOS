<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    /**
     * Show affiliate dashboard for the current user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Generate affiliate code if not exists
        if (!$user->affiliate_code) {
            $user->affiliate_code = strtoupper(Str::random(8));
            $user->save();
        }

        // Affiliate stats
        $totalEarnings = $user->getAffiliateEarnings();
        $availableBalance = (float) $user->affiliate_balance;
        $totalReferrals = $user->getAffiliateReferralCount();

        // Recent affiliate commissions
        $recentCommissions = Commission::where('affiliate_user_id', $user->id)
            ->with(['order.product', 'tenant'])
            ->where('affiliate_fee', '>', 0)
            ->latest()
            ->paginate(10);

        // Tiered commission info for developers
        $monthlySales = 0;
        $currentTier = $this->commissionService->getDeveloperTier(0);
        $tierKey = 'standard';

        if ($user->tenant) {
            $monthlySales = $this->commissionService->getMonthlySales($user->tenant->id);
            $currentTier = $this->commissionService->getDeveloperTier($monthlySales);
            $tierKey = $this->commissionService->getTierKey($monthlySales);
        }

        $tiers = CommissionService::DEVELOPER_TIERS;
        $currencySymbol = \App\Models\SiteSetting::get('currency_symbol', '¥');
        $affiliateRate = $this->commissionService->getAffiliateRate();
        $affiliateLink = $user->getAffiliateLink();
        $isDeveloper = $user->isDeveloper() || $user->isSuperAdmin();

        return view('developer.affiliate', compact(
            'user', 'totalEarnings', 'availableBalance', 'totalReferrals',
            'recentCommissions', 'monthlySales', 'currentTier', 'tierKey',
            'tiers', 'currencySymbol', 'affiliateRate', 'affiliateLink', 'isDeveloper'
        ));
    }

    /**
     * Generate or regenerate affiliate code.
     */
    public function regenerateCode(Request $request)
    {
        $user = $request->user();
        $user->affiliate_code = strtoupper(Str::random(8));
        $user->save();

        return back()->with('success', __('affiliate.code_regenerated'));
    }

    /**
     * Withdraw affiliate earnings to main balance.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $amount = min((float) $request->amount, (float) $user->affiliate_balance);

        if ($amount <= 0) {
            return back()->with('error', __('affiliate.insufficient_balance'));
        }

        // Transfer affiliate balance to tenant balance (if developer)
        if ($user->tenant) {
            $user->decrement('affiliate_balance', $amount);
            $user->increment('affiliate_total_earned', $amount);
            $user->tenant->increment('balance', $amount);
        }

        return back()->with('success', __('affiliate.withdraw_success'));
    }
}
