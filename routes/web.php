<?php

use App\Http\Controllers\ExchangeAccessController;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\SendFilesController;
use App\Http\Controllers\SharedFileController;
use App\Livewire\ExchangeWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('/', [MainPageController::class, 'show'])->name('home');

require __DIR__.'/admin-manager.php';

/*
| Public file links — a customer's emailed / copied "download link".
| Token-gated, no password; see SharedFileController.
*/
Route::prefix('share/{token}')->whereAlphaNumeric('token')->middleware('throttle:share-link')->name('share.')->group(function () {
    Route::get('/', [SharedFileController::class, 'show'])->name('show');
    Route::get('/view', [SharedFileController::class, 'view'])->name('view');
    Route::get('/download', [SharedFileController::class, 'download'])->name('download');
});

/*
| Send Files to Radcal — customer-initiated transfer (spec §3)
*/
Route::prefix('send')->name('send.')->group(function () {
    Route::get('/', [SendFilesController::class, 'create'])->name('start');
    Route::post('/', [SendFilesController::class, 'store'])
        ->middleware('throttle:verification-send')->name('store');

    Route::get('/verify', [SendFilesController::class, 'showVerify'])->name('verify');
    Route::post('/verify', [SendFilesController::class, 'verify'])
        ->middleware('throttle:verification-confirm')->name('verify.submit');
    Route::post('/verify/resend', [SendFilesController::class, 'resend'])
        ->middleware('throttle:verification-send')->name('verify.resend');
});

/*
| Access a File Exchange (spec §4.1)
*/
Route::get('/access', [ExchangeAccessController::class, 'show'])->name('access');
Route::post('/access', [ExchangeAccessController::class, 'enter'])->name('access.enter');

/*
| The exchange itself. Codes are always upper-case + digits + hyphen, so
| these patterns never shadow /send, /access, /admin, etc.
*/
Route::pattern('code', '[A-Z0-9][A-Z0-9\-]{2,63}');

Route::get('/{code}', [ExchangeAccessController::class, 'landing'])->name('exchange.landing');
Route::post('/{code}/access', [ExchangeAccessController::class, 'enterByCode'])->name('exchange.access');

Route::get('/{code}/workspace', ExchangeWorkspace::class)
    ->middleware('exchange.session')->name('exchange.workspace');
