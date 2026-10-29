<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAffiliateReferral
{
    /**
     * Track affiliate referral from ?ref=xxx URL parameter.
     * Stores affiliate user ID in cookie for later attribution.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');

        if ($ref) {
            $affiliateUser = \App\Models\User::where('affiliate_code', $ref)->first();

            if ($affiliateUser) {
                $cookieDays = (int) \App\Models\SiteSetting::get('affiliate_cookie_days', '30');
                // Store affiliate user ID in cookie
                cookie()->queue('affiliate_ref', $affiliateUser->id, $cookieDays * 24 * 60);
            }
        }

        return $next($request);
    }
}
