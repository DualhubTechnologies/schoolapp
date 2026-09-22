<?php

namespace App\Providers\Filament;

use Filafly\LogoTools\LogoToolsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('/')                       // Serves at http://schoolapp.test
            ->login(\App\Filament\Pages\Auth\Login::class)
            // Schools sign themselves up; the person registering becomes its School Admin.
            ->registration(\App\Filament\Pages\Auth\RegisterSchool::class)
            ->passwordReset()

            // Public landing page at the site root. Registered here (not in
            // routes/web.php) so Filament sees "/" is taken and sends
            // signed-in users to /dashboard instead.
            ->routes(function () {
                \Illuminate\Support\Facades\Route::get('/', \App\Http\Controllers\LandingController::class)->name('landing');
                // "Book a demo" form on the landing page.
                \Illuminate\Support\Facades\Route::post('/demo-request', \App\Http\Controllers\DemoRequestController::class)
                    ->middleware('throttle:5,10')
                    ->name('demo-request');
            })

            // The compiled Tailwind theme. This was MISSING before, which
            // is why modals (e.g. the student import wizard) rendered with
            // no styling on this panel — the classes those views use only
            // exist in this compiled stylesheet.
            ->viteTheme('resources/css/filament/admin/theme.css')

            // --- Branding ---
            ->brandName('SchoolHub')
            ->brandLogo(fn () => asset('images/schoolhub-logo-sidebar.svg'))  // tightly cropped, so the height below is all logo
            ->brandLogoHeight('2.25rem')
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

            // After saving a new or edited record, go back to its table.
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')

            // Printable fee documents, behind the panel's login. Full route
            // names: filament.app.fees.receipt / filament.app.fees.letters.
            ->authenticatedRoutes(function () {
                // Filament sends people to the route named "home" after
                // sign-in and from the logo; "/" is the landing page, so
                // point it at the dashboard.
                \Illuminate\Support\Facades\Route::get('/home', fn () => redirect(\App\Filament\App\Pages\Dashboard::getUrl()))
                    ->name('home');
                \Illuminate\Support\Facades\Route::get('/fees/receipts/{payment}', [\App\Http\Controllers\FeeDocumentController::class, 'receipt'])
                    ->whereNumber('payment')
                    ->name('fees.receipt');
                \Illuminate\Support\Facades\Route::get('/fees/reminder-letters', [\App\Http\Controllers\FeeDocumentController::class, 'letters'])
                    ->name('fees.letters');
                \Illuminate\Support\Facades\Route::get('/payroll-documents/{period}/{type}', [\App\Http\Controllers\PayrollDocumentController::class, 'schedule'])
                    ->whereNumber('period')
                    ->name('payroll.schedule');
                \Illuminate\Support\Facades\Route::get('/academics/report-cards', \App\Http\Controllers\ReportCardController::class)
                    ->name('academics.report-cards');
                \Illuminate\Support\Facades\Route::get('/finance/vouchers/{entry}', [\App\Http\Controllers\FinanceDocumentController::class, 'voucher'])
                    ->whereNumber('entry')
                    ->name('finance.voucher');
            })

            // --- Navigation ---
            // Groups in order of daily use: front-office work first,
            // one-time setup last. Settings starts collapsed because it
            // is rarely opened once the school is configured.
            ->navigationGroups([
                NavigationGroup::make('Students'),
                NavigationGroup::make('Fees'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Exams & Results'),
                NavigationGroup::make('Human Resources'),
                NavigationGroup::make('Academics'),
                NavigationGroup::make('Settings')->collapsed(),
                NavigationGroup::make('Platform Management'),
            ])

            // --- Plugins ---
            // Supplies the icon-only logo shown in the collapsed sidebar
            // rail. Without this the collapsed rail shows an empty block.
            // No darkModeIconLogo() here on purpose — darkMode is off.
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
                    . '<link rel="stylesheet" href="' . asset('css/dashboard.css') . '?v=' . filemtime(public_path('css/dashboard.css')) . '">'
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.topbar')
            )
            // Subscription warnings for School Admins (after the topbar).
            ->renderHook(
                \Filament\View\PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.subscription-banner')
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
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\App\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Everything in app/Filament/Widgets is discovered; the old
            // welcome card is left out -- the topbar already has the user menu.
            ->widgets([
                \App\Filament\Widgets\StatsOverview::class,
                // Per-user-group dashboard widgets (App\Filament\App\Pages\Dashboard
                // picks which ones each user sees).
                \App\Filament\App\Widgets\WelcomeBanner::class,
                \App\Filament\App\Widgets\LeadershipKpis::class,
                \App\Filament\App\Widgets\BursarKpis::class,
                \App\Filament\App\Widgets\HrKpis::class,
                \App\Filament\App\Widgets\TeacherKpis::class,
                \App\Filament\App\Widgets\TeacherMarksProgress::class,
                \App\Filament\App\Widgets\PlatformKpis::class,
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
                // Expired or suspended schools see only their Subscription page.
                \App\Http\Middleware\EnsureSchoolSubscribed::class,
            ]);
    }
}