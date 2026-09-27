<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\ActivationCodes\ActivationCodeResource;
use App\Filament\Admin\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Admin\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Admin\Resources\OnlineUsers\OnlineUserResource;
use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Pages\TransportCollections;
use App\Filament\App\Widgets\BursarKpis;
use App\Filament\App\Widgets\HrKpis;
use App\Filament\App\Widgets\LeadershipKpis;
use App\Filament\App\Widgets\PlatformActivityKpis;
use App\Filament\App\Widgets\PlatformKpis;
use App\Filament\App\Widgets\TeacherKpis;
use App\Filament\App\Widgets\TeacherMarksProgress;
use App\Filament\App\Widgets\WelcomeBanner;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RegisterSchool;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Widgets\StatsOverview;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\FeeDocumentController;
use App\Http\Controllers\FinanceDocumentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PayrollDocumentController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\TransportDocumentController;
use App\Http\Middleware\EnsureSchoolSubscribed;
use Filafly\LogoTools\LogoToolsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('/')                       // Serves at http://schoolapp.test
            ->login(Login::class)
            // Schools sign themselves up; the person registering becomes its School Admin.
            ->registration(RegisterSchool::class)
            ->passwordReset(resetAction: ResetPassword::class)

            // Public landing page at the site root. Registered here (not in
            // routes/web.php) so Filament sees "/" is taken and sends
            // signed-in users to /dashboard instead.
            ->routes(function () {
                Route::get('/', LandingController::class)->name('landing');
                // Terms and conditions, linked from registration and the site footers.
                Route::view('/terms-and-conditions', 'legal.terms')->name('legal.terms');   // /terms is the academic Terms resource
                // "Book a demo" form on the landing page.
                Route::post('/demo-request', DemoRequestController::class)
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

            // After saving a new or edited record, go back to its table.
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')

            // Printable fee documents, behind the panel's login. Full route
            // names: filament.app.fees.receipt / filament.app.fees.letters.
            ->authenticatedRoutes(function () {
                // Filament sends people to the route named "home" after
                // sign-in and from the logo; "/" is the landing page, so
                // point it at the dashboard.
                Route::get('/home', fn () => redirect(Dashboard::getUrl()))
                    ->name('home');
                Route::get('/fees/receipts/{payment}', [FeeDocumentController::class, 'receipt'])
                    ->whereNumber('payment')
                    ->name('fees.receipt');
                Route::get('/fees/reminder-letters', [FeeDocumentController::class, 'letters'])
                    ->name('fees.letters');
                Route::get('/payroll-documents/{period}/{type}', [PayrollDocumentController::class, 'schedule'])
                    ->whereNumber('period')
                    ->name('payroll.schedule');
                Route::get('/academics/report-cards', ReportCardController::class)
                    ->name('academics.report-cards');
                Route::get('/finance/vouchers/{entry}', [FinanceDocumentController::class, 'voucher'])
                    ->whereNumber('entry')
                    ->name('finance.voucher');
                Route::get('/students/{student}/admission-letter', [StudentDocumentController::class, 'admissionLetter'])
                    ->whereNumber('student')
                    ->name('students.admission-letter');
                Route::get('/transport/route-lists', [TransportDocumentController::class, 'routeLists'])
                    ->name('transport.route-lists');
                Route::get('/students/{student}/profile', [StudentDocumentController::class, 'profile'])
                    ->whereNumber('student')
                    ->name('students.profile');
            })

            // --- Navigation ---
            // Groups in order of daily use: front-office work first,
            // one-time setup last. Settings starts collapsed because it
            // is rarely opened once the school is configured.
            ->navigationGroups([
                NavigationGroup::make('Students'),
                NavigationGroup::make('Fees'),
                NavigationGroup::make('Transport'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Exams & Results'),
                NavigationGroup::make('Human Resources'),
                NavigationGroup::make('Academics'),
                NavigationGroup::make('Settings')->collapsed(),
                NavigationGroup::make('Platform Management'),
            ])
            // The platform owner's own pages live in the admin panel
            // (/admin); link them here so the Super Admin's sidebar isn't
            // just Dashboard and Users.
            ->navigationItems(collect([
                [SchoolResource::class, 'Schools', Heroicon::OutlinedBuildingOffice2, 0],
                [PlanResource::class, 'Plans & pricing', Heroicon::OutlinedRectangleStack, 1],
                [DemoRequestResource::class, 'Demo requests', Heroicon::OutlinedCalendarDays, 3],
                [ActivationCodeResource::class, 'Activation codes', Heroicon::OutlinedKey, 4],
                [ActivityLogResource::class, 'Activity logs', Heroicon::OutlinedClipboardDocumentList, 5],
                [OnlineUserResource::class, 'Online users', Heroicon::OutlinedSignal, 6],
            ])->map(fn (array $item) => NavigationItem::make($item[1])
                ->url(fn (): string => $item[0]::getUrl(panel: 'admin'))
                ->icon($item[2])
                ->group('Platform Management')
                ->sort($item[3])
                ->visible(fn (): bool => auth()->user()?->hasRole('Super Admin') ?? false))
                ->all())

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
                PanelsRenderHook::HEAD_END,
                fn () => '<link rel="stylesheet" href="'.asset('css/filament-custom.css').'?v='.filemtime(public_path('css/filament-custom.css')).'">'
                    .'<link rel="stylesheet" href="'.asset('css/dashboard.css').'?v='.filemtime(public_path('css/dashboard.css')).'">'
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.topbar')
            )
            // Subscription warnings for School Admins (after the topbar).
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.partials.subscription-banner')
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
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                TransportCollections::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Everything in app/Filament/Widgets is discovered; the old
            // welcome card is left out -- the topbar already has the user menu.
            ->widgets([
                StatsOverview::class,
                // Per-user-group dashboard widgets (App\Filament\App\Pages\Dashboard
                // picks which ones each user sees).
                WelcomeBanner::class,
                LeadershipKpis::class,
                BursarKpis::class,
                HrKpis::class,
                TeacherKpis::class,
                TeacherMarksProgress::class,
                PlatformKpis::class,
                PlatformActivityKpis::class,
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
                EnsureSchoolSubscribed::class,
            ]);
    }
}
