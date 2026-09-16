<?php

namespace App\Providers\Filament;

use Filafly\LogoTools\LogoToolsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')                   // Super Admin only — gated in User::canAccessPanel()
            ->login()

            ->passwordReset()
            ->viteTheme('resources/css/filament/admin/theme.css')

            // --- Branding ---
            ->brandName('SchoolHub')
            ->brandLogo(fn () => asset('images/schoolhub-logo-dark.svg'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('images/schoolhub-icon-192.png'))

            // --- Colors ---
            ->colors([
                'primary' => Color::Blue,
                'danger'  => Color::Red,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'info'    => Color::Sky,
            ])

            // --- Layout ---
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->darkMode(false)
            ->breadcrumbs(false)

            // --- Plugins ---
            // Icon-only logo for the collapsed sidebar rail.
            // No darkModeIconLogo() — darkMode is off.
            ->plugin(
                LogoToolsPlugin::make()
                    ->iconLogo(asset('images/schoolhub-icon.svg'))
                    ->iconLogoHeight('1.75rem')
                    ->collapseButtonIcon(Heroicon::OutlinedBars3)
                    ->expandButtonIcon(Heroicon::OutlinedBars3)
            )

            // --- Custom shell ---
            // PAGE_START (not TOPBAR_START): Filament's own topbar is
            // hidden in CSS, and this custom bar renders at the top of
            // the page content instead.
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="' . asset('css/filament-custom.css') . '?v=' . filemtime(public_path('css/filament-custom.css')) . '">'
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.topbar')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
                fn () => view('filament.partials.footer')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIMPLE_PAGE_START,
                fn () => view('filament.partials.login-brand')
            )

            // --- Resources, pages, widgets ---
            // Schools lives here (platform-owner only). UserResource is
            // shared with the app panel — one class, registered on both.
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->resources([
                \App\Filament\App\Resources\Users\UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                \App\Filament\Widgets\StatsOverview::class,
            ])

            // --- Middleware ---
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}