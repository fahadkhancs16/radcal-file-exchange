<?php

use App\Http\Controllers\AdminManagerController;
use Illuminate\Support\Facades\Route;

/*
| Staff-access user manager — a private, unlinked URL (see
| config/admin_manager.php) gated by a single shared password instead of a
| user login. The recovery path if every admin password is lost.
*/
Route::prefix(config('admin_manager.path'))->name('admin-manager.')->group(function () {
    Route::get('/', [AdminManagerController::class, 'showGate'])->name('gate');
    Route::post('/', [AdminManagerController::class, 'authenticate'])->name('authenticate');
    Route::post('/logout', [AdminManagerController::class, 'logout'])->name('logout');

    Route::middleware('admin-manager.session')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminManagerController::class, 'index'])->name('index');
        Route::get('/create', [AdminManagerController::class, 'create'])->name('create');
        Route::post('/', [AdminManagerController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [AdminManagerController::class, 'edit'])->name('edit');
        Route::put('/{user}', [AdminManagerController::class, 'update'])->name('update');
        Route::delete('/{user}', [AdminManagerController::class, 'destroy'])->name('destroy');
    });
});
