<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLanguage
{
    public function handle(Request $request, Closure $next)
    {
        $defaultLocale = \App\Models\SiteSetting::get('default_locale', 'zh_CN');
        $lang = $request->query('lang') ?? $request->session()->get('lang', $defaultLocale);

        if (!in_array($lang, ['en', 'zh_CN'])) {
            $lang = $defaultLocale;
        }

        App::setLocale($lang);

        if ($request->query('lang')) {
            $request->session()->put('lang', $lang);
        }

        return $next($request);
    }
}
