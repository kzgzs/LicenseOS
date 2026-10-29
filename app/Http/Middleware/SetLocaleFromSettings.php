<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;

class SetLocaleFromSettings
{
    public function handle(Request $request, Closure $next)
    {
        $locale = 'zh_CN'; // default

        try {
            $settingValue = SiteSetting::get('default_locale', null);
            if ($settingValue && in_array($settingValue, ['zh_CN', 'en'])) {
                $locale = $settingValue;
            }
        } catch (\Throwable $e) {
            // DB not ready, use config fallback
            $locale = config('app.locale', 'zh_CN');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
