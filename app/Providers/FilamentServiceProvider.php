<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate as AppAuthenticate;
use App\Http\Middleware\SetLocaleFromSettings;
use App\Models\SiteSetting;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use Filament\View\PanelsRenderHook;

class FilamentServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon(function () {
                return SiteSetting::get('site_favicon') ?: asset('favicon.ico');
            })
            ->login(false)
            ->brandName(function () {
                try {
                    return __(SiteSetting::get('site_name', config('app.name', 'Platform')));
                } catch (\Throwable $e) {
                    return config('app.name', 'Platform');
                }
            })
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>
                    .fi-section .animate-spin,
                    .fi-ta .animate-spin,
                    .fi-btn .animate-spin,
                    .fi-input-wrp .animate-spin,
                    .fi-modal .animate-spin,
                    .fi-wi .animate-spin { display: none !important; }
                    .fi-loading .animate-spin,
                    .fi-btn.fi-loading .animate-spin,
                    .fi-input-wrp.fi-loading .animate-spin,
                    .fi-ta-action.fi-loading .animate-spin { display: inline-block !important; }
                </style>',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => '<a href="' . url('/admin/logout') . '" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-white shadow-sm bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 transition" onclick="event.preventDefault(); if(confirm(\'' . __('admin.logout_confirm') . '\')){window.location.href=this.href;}">'
                    . '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>'
                    . __('admin.logout')
                    . '</a>',
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('admin.logout'))
                    ->url(fn () => route('admin.logout'))
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->sort(100),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocaleFromSettings::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AppAuthenticate::class,
                \App\Http\Middleware\FilamentAdminAccess::class,
            ]);
    }
}
