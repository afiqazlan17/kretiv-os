<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\ItemLibraryController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobVendorCostController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Kretiv OS: with the *_HOST env vars set each module answers only on its own
// subdomain; with none set everything runs on one host as before.
$host = fn (string $module) => config("kretivco.hosts.{$module}");
$onHost = fn (string $module, Closure $routes) => $host($module) ? Route::domain($host($module))->group($routes) : $routes();

// OS: login, launcher, Users & Access.
$osRoutes = function () {
    Route::middleware('auth')->group(function () {
        Route::get('/', [OsController::class, 'home'])->name('os.home');
        Route::get('/access', [OsController::class, 'access'])->name('os.access');
        Route::put('/access/{user}', [OsController::class, 'updateAccess'])->name('os.access.update');
    });
};
if ($host('os')) {
    Route::domain($host('os'))->group($osRoutes);
    Route::domain($host('os'))->group(base_path('routes/auth.php'));
} else {
    Route::prefix('os')->group($osRoutes);
    require __DIR__.'/auth.php';
}

$onHost('jobs', function () {
    Route::get('/', function () {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    })->name('jobs.home');

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified', 'module:jobs'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::middleware(['auth', 'module:jobs'])->group(function () {
        Route::get('/settings', [UserController::class, 'index'])->name('settings.index');
        Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
        Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
        Route::post('/settings/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('settings.users.toggle-active');
        Route::post('/settings/reset-jobs', [UserController::class, 'resetJobs'])->name('settings.reset-jobs');
        Route::post('/settings/reset-all-data', [UserController::class, 'resetAllData'])->name('settings.reset-all-data');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::get('/items', [ItemLibraryController::class, 'index'])->name('items.index');
        Route::get('/items/search', [ItemLibraryController::class, 'search'])->name('items.search');
        Route::post('/items', [ItemLibraryController::class, 'store'])->name('items.store');
        Route::put('/items/{item}', [ItemLibraryController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [ItemLibraryController::class, 'destroy'])->name('items.destroy');
        Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::post('/jobs/quotation-preview', [DocumentController::class, 'previewNewJob'])->name('jobs.quotation-preview');
        Route::get('/jobs/quotation-notes', [DocumentController::class, 'quotationNotes'])->name('jobs.quotation-notes');
        Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::put('/jobs/{job}/line-items', [JobController::class, 'updateLineItems'])->name('jobs.line-items.update');
        Route::post('/jobs/{job}/take-in', [JobController::class, 'takeIn'])->name('jobs.take-in');
        Route::post('/jobs/{job}/close-ticket', [JobController::class, 'closeTicket'])->name('jobs.close-ticket');
        Route::post('/jobs/{job}/complete', [JobController::class, 'complete'])->name('jobs.complete');
        Route::put('/jobs/{job}/reassign', [JobController::class, 'reassign'])->name('jobs.reassign');
        Route::post('/jobs/{job}/hold', [JobController::class, 'hold'])->name('jobs.hold');
        Route::post('/jobs/{job}/resume', [JobController::class, 'resume'])->name('jobs.resume');
        Route::post('/jobs/{job}/archive', [JobController::class, 'archive'])->name('jobs.archive');
        Route::post('/jobs/{job}/rollback', [JobController::class, 'rollback'])->name('jobs.rollback');
        Route::post('/jobs/{job}/notes', [JobController::class, 'addNote'])->name('jobs.notes.store');
        Route::get('/jobs/{job}/notes/{log}/attachments/{attachmentId}', [JobController::class, 'noteAttachment'])->name('jobs.notes.attachments.show');
        Route::post('/jobs/{job}/attachments', [AttachmentController::class, 'store'])->name('jobs.attachments.store');
        Route::get('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'show'])->name('jobs.attachments.show');
        Route::delete('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'destroy'])->name('jobs.attachments.destroy');
        Route::prefix('/jobs/{job}/documents/{type}')->whereIn('type', ['quotation', 'proforma', 'invoice', 'receipt'])->group(function () {
            Route::get('/draft', [DocumentController::class, 'draft'])->name('jobs.documents.draft');
            Route::post('/preview', [DocumentController::class, 'preview'])->name('jobs.documents.preview');
            Route::post('/save', [DocumentController::class, 'save'])->name('jobs.documents.save');
            Route::post('/generate', [DocumentController::class, 'generate'])->name('jobs.documents.generate');
        });
        Route::get('/jobs/{job}/documents/{document}', [DocumentController::class, 'showDocument'])->name('jobs.documents.show');
        Route::post('/jobs/{job}/documents/combine', [DocumentController::class, 'combine'])->name('jobs.documents.combine');
        Route::post('/jobs/{job}/vendor-costs', [JobVendorCostController::class, 'store'])->name('jobs.vendor-costs.store');
        Route::put('/jobs/{job}/vendor-costs/{costId}', [JobVendorCostController::class, 'update'])->name('jobs.vendor-costs.update');
        Route::delete('/jobs/{job}/vendor-costs/{costId}', [JobVendorCostController::class, 'destroy'])->name('jobs.vendor-costs.destroy');
        Route::post('/jobs/{job}/vendor-costs/{costId}/mark-paid', [JobVendorCostController::class, 'markPaid'])->name('jobs.vendor-costs.mark-paid');
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('/leads/{lead}/mark-lost', [LeadController::class, 'markLost'])->name('leads.mark-lost');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    });

    Route::middleware(['auth', 'module:finance'])->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/reports/{report}', [FinanceReportController::class, 'show'])->name('finance.reports');
        Route::post('/finance/expense', [FinanceController::class, 'storeExpense'])->name('finance.expense.store');
        Route::post('/finance/opening-balance', [FinanceController::class, 'storeOpeningBalance'])->name('finance.opening-balance.store');
        Route::post('/finance/director-loan', [FinanceController::class, 'storeDirectorLoan'])->name('finance.director-loan.store');
        Route::post('/finance/bank-transfer', [FinanceController::class, 'storeBankTransfer'])->name('finance.bank-transfer.store');
    });
});
