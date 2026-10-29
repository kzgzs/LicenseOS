<?php

namespace App\Providers;

use App\Http\ViewComposers\AdComposer;
use App\Http\ViewComposers\ShareMetaComposer;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fix: Laravel 11 lazy-loaded commands miss setLaravel() call.
        // Ensure every Command resolved from the container gets the app instance.
        $this->app->resolving(ConsoleCommand::class, function ($command, $app) {
            if (method_exists($command, 'setLaravel')) {
                $command->setLaravel($app);
            }
        });
    }

    public function boot(): void
    {
        // During package:discover and early bootstrap, DB is not available.
        // Wrap everything to prevent any crash during composer/artisan operations.
        try {
            if (!Schema::hasTable('site_settings')) {
                $this->applyDefaults();
                return;
            }

            // Locale
            $locale = SiteSetting::get('default_locale', config('app.locale', 'zh_CN'));
            $this->app->setLocale($locale);

            // Timezone
            $tz = SiteSetting::get('timezone', config('app.timezone', 'Asia/Shanghai'));
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);

            View::composer('*', function ($view) {
                $view->with('currencySymbol', SiteSetting::get('currency_symbol', '¥'));
            });

            // Inject ad data into storefront views
            View::composer(['welcome', 'storefront.*'], AdComposer::class);

            // Inject share / SEO meta data into the main layout
            View::composer('layouts.app', ShareMetaComposer::class);
        } catch (\Throwable $e) {
            $this->applyDefaults();
        }
    }

    protected function applyDefaults(): void
    {
        config(['app.timezone' => 'Asia/Shanghai']);
        date_default_timezone_set('Asia/Shanghai');
        View::composer('*', fn ($view) => $view->with('currencySymbol', '¥'));
    }
}
