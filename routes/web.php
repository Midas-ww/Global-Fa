<?php

use App\Http\Controllers\Auth\SsoCallbackController;
use App\Http\Controllers\GlobalFinanceController;
use App\Http\Controllers\MasterBusinessUnitController;
use Illuminate\Support\Facades\Route;

// SSO & Autentikasi
Route::get('/sso/callback', [SsoCallbackController::class, 'handle'])->name('sso.callback');
Route::get('/sso/portal', [SsoCallbackController::class, 'redirectToPortal'])->name('sso.portal');
Route::get('/login', [SsoCallbackController::class, 'showLogin'])->name('login');
Route::post('/login', [SsoCallbackController::class, 'login'])->name('login.post');
Route::match(['get', 'post'], '/logout', [SsoCallbackController::class, 'logout'])->name('logout');

// Main Routes (Protected by auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('finance.dashboard'));
    Route::get('/dashboard', [GlobalFinanceController::class, 'dashboard'])->name('finance.dashboard');
    Route::get('/pnl', [GlobalFinanceController::class, 'pnl'])->name('finance.pnl');
    Route::get('/balance-sheet', [GlobalFinanceController::class, 'balanceSheet'])->name('finance.balance-sheet');
    Route::get('/sync', [GlobalFinanceController::class, 'syncView'])->name('finance.sync');
    Route::post('/sync/database', [GlobalFinanceController::class, 'triggerDbSync'])->name('finance.sync.database');
    Route::post('/sync/excel', [GlobalFinanceController::class, 'uploadExcel'])->name('finance.sync.excel');
    Route::post('/sync/preview-excel', [GlobalFinanceController::class, 'previewExcel'])->name('finance.sync.preview');
    Route::delete('/sync/logs/bulk-delete', [GlobalFinanceController::class, 'bulkDeleteIngestionLogs'])->name('finance.sync.bulk-delete');
    Route::delete('/sync/log/{id}', [GlobalFinanceController::class, 'deleteIngestionLog'])->name('finance.sync.delete-log');

    // Master Data Unit Usaha & Entitas
    Route::get('/master/units', [MasterBusinessUnitController::class, 'index'])->name('finance.master.units');
    Route::post('/master/units', [MasterBusinessUnitController::class, 'store'])->name('finance.master.units.store');
    Route::put('/master/units/{id}', [MasterBusinessUnitController::class, 'update'])->name('finance.master.units.update');
    Route::delete('/master/units/{id}', [MasterBusinessUnitController::class, 'destroy'])->name('finance.master.units.destroy');
    Route::post('/master/units/{unitId}/entities', [MasterBusinessUnitController::class, 'storeEntity'])->name('finance.master.entities.store');
    Route::delete('/master/entities/{id}', [MasterBusinessUnitController::class, 'destroyEntity'])->name('finance.master.entities.destroy');
    Route::get('/master/units/{code}/template', [MasterBusinessUnitController::class, 'downloadTemplate'])->name('finance.master.units.template');
});
