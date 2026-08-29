<?php

use App\Domain\Finance\Http\Controllers\DocumentController as FinanceDocumentController;
use App\Domain\Jobs\Http\Controllers\AttachmentController;
use App\Domain\Jobs\Http\Controllers\CustomerController;
use App\Domain\Jobs\Http\Controllers\JobController;
use App\Domain\Jobs\Http\Controllers\LeadController;
use App\Domain\Jobs\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// jobs.kretiv.co — Jobs module. Auth relies entirely on the shared
// session set at the hub (SESSION_DOMAIN=.kretiv.co); no login form here.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'jobs.index' : 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');

    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::post('/jobs/{job}/take-in', [JobController::class, 'takeIn'])->name('jobs.take-in');
    Route::post('/jobs/{job}/close-ticket', [JobController::class, 'closeTicket'])->name('jobs.close-ticket');
    Route::post('/jobs/{job}/complete', [JobController::class, 'complete'])->name('jobs.complete');
    Route::post('/jobs/{job}/attachments', [AttachmentController::class, 'store'])->name('jobs.attachments.store');
    Route::get('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'show'])->name('jobs.attachments.show');
    Route::delete('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'destroy'])->name('jobs.attachments.destroy');

    // Handled by the Finance module's DocumentController — a Job action
    // calling straight into Finance as a plain PHP method call, the
    // cross-module link the module split (Phase 3) exists to prove.
    Route::get('/jobs/{job}/invoice', [FinanceDocumentController::class, 'invoice'])->name('jobs.invoice');
    Route::get('/jobs/{job}/receipt', [FinanceDocumentController::class, 'receipt'])->name('jobs.receipt');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::post('/leads/{lead}/mark-lost', [LeadController::class, 'markLost'])->name('leads.mark-lost');
});
