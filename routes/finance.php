<?php

use App\Domain\Finance\Http\Controllers\FinanceController;
use App\Domain\Finance\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// finance.kretiv.co — Finance module. Auth relies entirely on the shared
// session set at the hub (SESSION_DOMAIN=.kretiv.co); no login form here.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'finance.index' : 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/finance/expense', [FinanceController::class, 'storeExpense'])->name('finance.expense.store');
    Route::post('/finance/opening-balance', [FinanceController::class, 'storeOpeningBalance'])->name('finance.opening-balance.store');
    Route::post('/finance/director-loan', [FinanceController::class, 'storeDirectorLoan'])->name('finance.director-loan.store');
    Route::post('/finance/bank-transfer', [FinanceController::class, 'storeBankTransfer'])->name('finance.bank-transfer.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
