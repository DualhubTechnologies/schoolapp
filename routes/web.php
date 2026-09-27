<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\NssfScheduleController;


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