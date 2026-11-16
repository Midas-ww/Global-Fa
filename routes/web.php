<?php

use App\Http\Controllers\Auth\SsoCallbackController;
use App\Http\Controllers\GlobalFinanceController;
use Illuminate\Support\Facades\Route;

// SSO & Autentikasi
Route::get('/sso/callback', [SsoCallbackController::class, 'handle'])->name('sso.callback');
Route::get('/login', [SsoCallbackController::class, 'showLogin'])->name('login');
Route::post('/login', [SsoCallbackController::class, 'login'])->name('login.post');
Route::match(['get', 'post'], '/logout', [SsoCallbackController::class, 'logout'])->name('logout');

// Main Routes (Protected by auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/', fn() => redirect()->route('finance.dashboard'));
    Route::get('/dashboard', [GlobalFinanceController::class, 'dashboard'])->name('finance.dashboard');
    Route::get('/pnl', [GlobalFinanceController::class, 'pnl'])->name('finance.pnl');
    Route::get('/balance-sheet', [GlobalFinanceController::class, 'balanceSheet'])->name('finance.balance-sheet');
    Route::get('/sync', [GlobalFinanceController::class, 'syncView'])->name('finance.sync');
    Route::post('/sync/database', [GlobalFinanceController::class, 'triggerDbSync'])->name('finance.sync.database');
    Route::post('/sync/excel', [GlobalFinanceController::class, 'uploadExcel'])->name('finance.sync.excel');
});
