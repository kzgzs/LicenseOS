<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\PlatformEvent;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    /**
     * Show payout overview.
     */
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            $payouts = $request->user()->isSuperAdmin()
                ? Payout::latest()->paginate(20)
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            return view('developer.payouts.index', [
                'payouts' => $payouts,
                'availableBalance' => 0,
                'totalIncome' => 0,
                'totalPaidOut' => 0,
                'tenant' => null,
            ]);
        }
        $payouts = $tenant->payouts()->latest()->paginate(20);
        $availableBalance = $tenant->availableBalance();
        $totalIncome = $tenant->totalIncome();
        $totalPaidOut = $tenant->totalPaidOut();
        return view('developer.payouts.index', compact('payouts', 'availableBalance', 'totalIncome', 'totalPaidOut', 'tenant'));
    }

    /**
     * Request a payout.
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
            'method' => 'required|in:stripe,alipay,wechat',
        ]);

        $tenant = $request->user()->tenant;
        $availableBalance = $tenant->availableBalance();

        if ($request->amount > $availableBalance) {
            $cs = \App\Models\SiteSetting::get('currency_symbol', '¥');
            return back()->with('error', __('payout.insufficient_balance', ['amount' => $cs . number_format($availableBalance, 2)]));
        }

        $payout = Payout::create([
            'tenant_id' => $tenant->id,
            'amount' => $request->amount,
            'method' => $request->method,
            'status' => 'pending',
        ]);

        // Dispatch payout event
        PlatformEvent::dispatch('payout.requested', [
            'payout_id' => $payout->id,
            'tenant_id' => $tenant->id,
            'amount' => (float) $payout->amount,
            'method' => $payout->method,
        ]);

        return back()->with('success', 'Payout request submitted. Processing time: 1-3 business days.');
    }

    /**
     * Update payout accounts (Stripe / Alipay / WeChat).
     */
    public function updateAccounts(Request $request)
    {
        $request->validate([
            'alipay_account' => 'nullable|string|max:255',
            'wechat_account' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return back()->with('error', 'No developer account found.');
        }

        $tenant->update([
            'alipay_account' => $request->input('alipay_account'),
            'wechat_account' => $request->input('wechat_account'),
            'stripe_account_id' => $request->input('stripe_account_id'),
        ]);

        return back()->with('success', 'Payout accounts updated.');
    }
}
