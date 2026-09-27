<?php

use App\Http\Controllers\NssfScheduleController;
use App\Http\Controllers\ParentPageController;
use App\Http\Controllers\PayslipController;
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

// The parent page: a learner's fees and report cards from the short link
// in an SMS, no login (see ParentPageController).
Route::middleware('throttle:30,1')->prefix('p/{token}')->group(function () {
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
