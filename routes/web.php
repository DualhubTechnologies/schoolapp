<?php

use App\Http\Controllers\DesktopSetupController;
use App\Http\Controllers\NssfScheduleController;
use App\Http\Controllers\ParentPageController;
use App\Http\Controllers\PayslipController;
use App\Http\Middleware\ServerEditionOnly;
use App\Support\PublicSite;
use Illuminate\Support\Facades\Route;

Route::get('/payslip/{entry}/download', [PayslipController::class, 'download'])
    ->name('payslip.download')
    ->middleware('auth');

Route::get('/nssf-schedule/{period}/pdf', [NssfScheduleController::class, 'downloadPdf'])
    ->name('nssf-schedule.pdf')
    ->middleware('auth');

Route::get('/nssf-schedule/{period}/excel', [NssfScheduleController::class, 'downloadExcel'])
    ->name('nssf-schedule.excel')
    ->middleware('auth');

Route::get('/payslips/{period}/all', [PayslipController::class, 'downloadAll'])
    ->name('payslips.all')
    ->middleware('auth');

// The Windows app's first run: the school and its administrator.
Route::get('/setup', [DesktopSetupController::class, 'show'])->name('desktop.setup');
Route::post('/setup', [DesktopSetupController::class, 'store'])->middleware('throttle:10,1')->name('desktop.setup.store');

// For search engines: the public pages to list, and the old website's
// privacy page, which Google still shows, sent to where it lives now
// (301, so Google moves its listing too).
Route::get('/sitemap.xml', function () {
    $site = rtrim((string) config('app.url'), '/');
    $pages = [
        ['/', 'weekly', '1.0'],
        ...array_map(fn (string $slug): array => ['/'.$slug, 'monthly', '0.8'], array_keys(PublicSite::PAGES)),
        ['/terms-and-conditions', 'monthly', '0.5'],
    ];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach ($pages as [$path, $changes, $priority]) {
        $xml .= "  <url><loc>{$site}{$path}</loc><changefreq>{$changes}</changefreq><priority>{$priority}</priority></url>\n";
    }

    return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml']);
})->middleware(ServerEditionOnly::class)->name('sitemap');

// Built here rather than kept as public/robots.txt so it names the same
// address as the sitemap and the pages' canonical links (APP_URL).
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow:\n\nSitemap: ".rtrim((string) config('app.url'), '/')."/sitemap.xml\n",
    200,
    ['Content-Type' => 'text/plain'],
))->middleware(ServerEditionOnly::class)->name('robots');

Route::permanentRedirect('/privacy-policy', '/terms-and-conditions#privacy')->middleware(ServerEditionOnly::class);

// The parent page: a learner's fees and report cards from the short link
// in an SMS, no login (see ParentPageController).
Route::middleware(['throttle:30,1', ServerEditionOnly::class])->prefix('p/{token}')->group(function () {
    Route::get('/', [ParentPageController::class, 'show'])->name('parent.page');
    Route::get('/receipt/{payment}', [ParentPageController::class, 'receipt'])->whereNumber('payment')->name('parent.receipt');
    Route::get('/report/{term}', [ParentPageController::class, 'reportCard'])->whereNumber('term')->name('parent.report');
});

// Some hosts (e.g. InfinityFree) disable PHP's symlink(), so `storage:link`
// can't create public/storage. This serves the same files dynamically instead.
Route::get('/storage/{path}', function (string $path) {
    $base = storage_path('app/public');
    $full = realpath($base.'/'.$path);

    // The trailing separator stops a sibling folder such as
    // app/public-old from passing the check.
    abort_unless($full && str_starts_with($full, $base.DIRECTORY_SEPARATOR), 404);

    return response()->file($full);
})->where('path', '.*')->name('storage.local');
