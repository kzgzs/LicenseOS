<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class MyLicenseController extends Controller
{
    /**
     * Show the current user's purchased licenses.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->where('status', 'paid')
            ->with(['product', 'licenses.activations'])
            ->latest()
            ->paginate(20);

        $currencySymbol = \App\Models\SiteSetting::get('currency_symbol', '¥');

        return view('my-licenses', compact('orders', 'currencySymbol'));
    }
}
