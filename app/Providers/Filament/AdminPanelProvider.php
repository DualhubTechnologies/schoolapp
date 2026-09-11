<?php

namespace App\Providers\Filament;

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
use Filafly\LogoTools\LogoToolsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('SchoolHub')
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Red,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->brandLogo(fn () => request()->routeIs('filament.admin.auth.login')
                ? asset('images/schoolhub-logo-light.svg')
                : asset('images/schoolhub-logo-dark.svg'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('images/schoolhub-icon-192.png'))
            ->maxContentWidth(Width::Full)
            ->breadcrumbs(false)
            ->plugin(
                LogoToolsPlugin::make()
                    ->iconLogo(asset('images/schoolhub-icon.svg'))
                    ->darkModeIconLogo(asset('images/schoolhub-icon-dark.svg')) // optional
                    ->iconLogoHeight('1.5rem')
                    ->collapseButtonIcon(Heroicon::OutlinedBars3)
                    ->expandButtonIcon(Heroicon::OutlinedBars3)
            )
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="' . asset('css/filament-custom.css') . '">'
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                \App\Filament\Widgets\StatsOverview::class,
            ])
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