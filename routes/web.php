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