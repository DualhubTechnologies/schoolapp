<?php

namespace App\Providers\Filament;

use App\Filament\App\Resources\Users\UserResource;
use App\Filament\App\Widgets\PlatformActivityKpis;
use App\Filament\App\Widgets\PlatformKpis;
use App\Filament\App\Widgets\WelcomeBanner;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Support\Pages\RecordFormScope;
use App\Filament\Widgets\StatsOverview;
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
use Filament\View\PanelsRenderHook;
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
            ->login(Login::class)

            ->passwordReset(resetAction: ResetPassword::class)
            ->viteTheme('resources/css/filament/admin/theme.css')

            // --- Branding ---
            ->brandName('SchoolHub')
            ->brandLogo(fn () => asset('images/schoolhub-logo-sidebar.svg'))  // tightly cropped, so the height below is all logo
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/schoolhub-icon-192.png'))

            // --- Colors ---
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Red,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])

            // --- Layout ---
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->darkMode(false)
            ->breadcrumbs(false)
            // Updating (503) and an expired sign-in (419) are not errors:
            // public/js/schoolhub-mobile.js shows a banner or reloads instead
            // of "Error while loading page".
            ->hiddenErrorNotification(503)
            ->hiddenErrorNotification(419)

            // After saving a new or edited record, go back to its table.
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')

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
                PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="'.asset('css/filament-custom.css').'?v='.filemtime(public_path('css/filament-custom.css')).'">'
                    .'<link rel="stylesheet" href="'.asset('css/dashboard.css').'?v='.filemtime(public_path('css/dashboard.css')).'">'
                    // Calmer handling of expired sign-ins and dropped requests.
                    .'<script src="'.asset('js/schoolhub-mobile.js').'?v='.filemtime(public_path('js/schoolhub-mobile.js')).'"></script>'
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.topbar')
            )
            // "Back to the list" above the heading of every create / edit page.
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => RecordFormScope::backLink(),
                scopes: RecordFormScope::NAME,
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('filament.partials.footer')
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn () => view('filament.partials.login-brand')
            )

            // --- Resources, pages, widgets ---
            // Schools lives here (platform-owner only). UserResource is
            // shared with the app panel — one class, registered on both.
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->resources([
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Same dashboard design as the school app: greeting banner and
            // platform cards and today's activity first, then the subscription
            // figures.
            ->widgets([
                WelcomeBanner::class,
                PlatformKpis::class,
                PlatformActivityKpis::class,
                StatsOverview::class,
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
