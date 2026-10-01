<?php

use App\Http\Controllers\AccountantPackController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BankImportController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\Hr\AnnouncementController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\CompanyController;
use App\Http\Controllers\Hr\EaFormController;
use App\Http\Controllers\Hr\HolidayController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\MyClaimController;
use App\Http\Controllers\Hr\MyProfileController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\ProfileRequestController;
use App\Http\Controllers\Hr\StaffController;
use App\Http\Controllers\ItemImageController;
use App\Http\Controllers\ItemLibraryController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobFormController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\JobVendorCostController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OsController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RizqController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TaxSummaryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Support\DocumentData;
use Illuminate\Support\Facades\Route;

// Kretiv OS: with the *_HOST env vars set each module answers only on its own
// subdomain; with none set everything runs on one host as before.
$host = fn (string $module) => config("kretivco.hosts.{$module}");
$onHost = fn (string $module, Closure $routes) => $host($module) ? Route::domain($host($module))->group($routes) : $routes();

// OS: login, launcher, Users & Access.
$osRoutes = function () {
    Route::middleware('auth')->group(function () {
        Route::get('/', [OsController::class, 'home'])->name('os.home');
        Route::post('/clock-in', [AttendanceController::class, 'clockIn'])->name('os.clock-in');
        Route::get('/notifications/{key}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/clock-out', [AttendanceController::class, 'clockOut'])->name('os.clock-out');
        Route::post('/clock-out/undo', [AttendanceController::class, 'undoClockOut'])->name('os.clock-out.undo');
        Route::get('/privacy', [PrivacyController::class, 'staff'])->name('privacy.staff');
        Route::post('/privacy/acknowledge', [PrivacyController::class, 'acknowledge'])->name('privacy.acknowledge');
        Route::post('/rizq', [RizqController::class, 'store'])->middleware('throttle:30,1')->name('os.rizq.store');
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

    // Signed, expiring link to a generated document, sent to customers on WhatsApp.
    Route::get('/d/{document}', [DocumentController::class, 'shared'])->middleware(['signed', 'throttle:60,1'])->name('documents.shared');
    Route::get('/soa/{customer}', [StatementController::class, 'shared'])->middleware(['signed', 'throttle:60,1'])->name('statement.shared');

    // Customer artwork approval: the link itself is the key (no login).
    Route::get('/approval/{token}', [ApprovalController::class, 'show'])->middleware('throttle:60,1')->name('approval.show');
    Route::get('/approval/{token}/file/{attachmentId}', [ApprovalController::class, 'file'])->middleware('throttle:60,1')->name('approval.file');
    Route::post('/approval/{token}', [ApprovalController::class, 'respond'])->middleware('throttle:10,1')->name('approval.respond');
    Route::get('/approval/{token}/record', [ApprovalController::class, 'record'])->middleware('throttle:60,1')->name('approval.record');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::middleware(['auth', 'module:jobs'])->group(function () {
        Route::get('/settings', [UserController::class, 'index'])->name('settings.index');
        Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
        Route::post('/settings/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('settings.users.toggle-active');
        Route::post('/settings/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('settings.users.reset-password');
        Route::post('/settings/reset-jobs', [UserController::class, 'resetJobs'])->name('settings.reset-jobs');
        Route::post('/settings/reset-all-data', [UserController::class, 'resetAllData'])->name('settings.reset-all-data');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/postcode-lookup/{postcode}', [CustomerController::class, 'postcodeLookup'])->name('postcode.lookup');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/customers/{customer}/statement', [StatementController::class, 'show'])->name('customers.statement');
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
        Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
        Route::put('/jobs/{job}/line-items', [JobController::class, 'updateLineItems'])->name('jobs.line-items.update');
        Route::post('/jobs/{job}/take-in', [JobController::class, 'takeIn'])->name('jobs.take-in');
        Route::post('/jobs/{job}/advance', [JobController::class, 'advance'])->name('jobs.advance');
        Route::post('/jobs/{job}/duplicate', [JobController::class, 'duplicate'])->name('jobs.duplicate');
        Route::get('/jobs/{job}/forms/{key}', [JobFormController::class, 'edit'])->name('jobs.forms.edit');
        Route::put('/jobs/{job}/forms/{key}', [JobFormController::class, 'update'])->name('jobs.forms.update');
        Route::get('/jobs/{job}/forms/{key}/pdf', [JobFormController::class, 'pdf'])->name('jobs.forms.pdf');
        Route::post('/jobs/{job}/deposit', [JobController::class, 'settleDeposit'])->name('jobs.deposit.settle');
        Route::post('/jobs/{job}/po', [JobController::class, 'updatePo'])->name('jobs.po.update');
        Route::post('/jobs/{job}/approvals', [ApprovalController::class, 'send'])->name('jobs.approvals.send');
        Route::get('/jobs/{job}/po', [JobController::class, 'poFile'])->name('jobs.po.file');
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
        Route::post('/jobs/{job}/photos', [JobPhotoController::class, 'store'])->middleware('throttle:20,1')->name('jobs.photos.store');
        Route::get('/jobs/{job}/photos/{photo}', [JobPhotoController::class, 'show'])->name('jobs.photos.show');
        Route::patch('/jobs/{job}/photos/{photo}', [JobPhotoController::class, 'update'])->name('jobs.photos.update');
        Route::delete('/jobs/{job}/photos/{photo}', [JobPhotoController::class, 'destroy'])->name('jobs.photos.destroy');
        Route::get('/rizq', [RizqController::class, 'index'])->name('rizq.index');
        Route::post('/rizq', [RizqController::class, 'store'])->middleware('throttle:30,1')->name('rizq.store');
        Route::post('/rizq/{note}/take', [RizqController::class, 'take'])->name('rizq.take');
        Route::post('/rizq/{note}/reply', [RizqController::class, 'reply'])->name('rizq.reply');
        Route::post('/rizq/{note}/done', [RizqController::class, 'done'])->name('rizq.done');
        Route::post('/rizq/{note}/reopen', [RizqController::class, 'reopen'])->name('rizq.reopen');
        Route::get('/rizq/{note}/photo', [RizqController::class, 'photo'])->name('rizq.photo');
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
        Route::get('/portfolio/download', [PortfolioController::class, 'download'])->name('portfolio.download');
        Route::post('/jobs/{job}/item-images', [ItemImageController::class, 'store'])->middleware('throttle:30,1')->name('jobs.item-images.store');
        Route::get('/jobs/{job}/item-images/{name}', [ItemImageController::class, 'show'])->name('jobs.item-images.show');
        Route::prefix('/jobs/{job}/documents/{type}')->whereIn('type', DocumentData::TYPES)->group(function () {
            Route::get('/draft', [DocumentController::class, 'draft'])->name('jobs.documents.draft');
            Route::post('/preview', [DocumentController::class, 'preview'])->name('jobs.documents.preview');
            Route::post('/save', [DocumentController::class, 'save'])->name('jobs.documents.save');
            Route::post('/generate', [DocumentController::class, 'generate'])->name('jobs.documents.generate');
        });
        Route::get('/jobs/{job}/documents/{document}', [DocumentController::class, 'showDocument'])->name('jobs.documents.show');
        Route::post('/jobs/{job}/documents/combine', [DocumentController::class, 'combine'])->name('jobs.documents.combine');
        Route::post('/jobs/{job}/payments', [DocumentController::class, 'recordPayment'])->name('jobs.payments.store');
        Route::post('/jobs/{job}/payments/{entry}/void', [DocumentController::class, 'voidPayment'])->name('jobs.payments.void');
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
        // Departments moved to HR; keep old links working.
        Route::get('/departments', fn () => redirect()->route('hr.departments'))->name('departments.index');
    });

});

// Finance: its own subdomain (finance.kretiv.co) when FINANCE_HOST is set,
// otherwise under /finance on the single host (local dev, tests).
$financeRoutes = function () {
    Route::middleware(['auth', 'module:finance'])->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/reports/{report}', [FinanceReportController::class, 'show'])->name('finance.reports');
        Route::post('/expense', [FinanceController::class, 'storeExpense'])->name('finance.expense.store');
        Route::post('/opening-balance', [FinanceController::class, 'storeOpeningBalance'])->name('finance.opening-balance.store');
        Route::post('/director-loan', [FinanceController::class, 'storeDirectorLoan'])->name('finance.director-loan.store');
        Route::post('/bank-transfer', [FinanceController::class, 'storeBankTransfer'])->name('finance.bank-transfer.store');
        Route::get('/ledger/{entry}/receipt', [FinanceController::class, 'showReceipt'])->name('finance.ledger.receipt');
        Route::get('/collections', [CollectionsController::class, 'index'])->name('finance.collections');
        Route::get('/recurring', [RecurringExpenseController::class, 'index'])->name('finance.recurring');
        Route::post('/recurring', [RecurringExpenseController::class, 'store'])->name('finance.recurring.store');
        Route::post('/recurring/{recurring}/record', [RecurringExpenseController::class, 'record'])->name('finance.recurring.record');
        Route::post('/recurring/{recurring}/toggle', [RecurringExpenseController::class, 'toggle'])->name('finance.recurring.toggle');
        Route::delete('/recurring/{recurring}', [RecurringExpenseController::class, 'destroy'])->name('finance.recurring.destroy');
        Route::get('/audit-log', [AuditLogController::class, 'finance'])->name('finance.audit');
        Route::get('/claims', [ClaimController::class, 'index'])->name('finance.claims');
        Route::post('/claims', [ClaimController::class, 'store'])->name('finance.claims.store');
        Route::post('/claims/{claim}/approve', [ClaimController::class, 'approve'])->name('finance.claims.approve');
        Route::post('/claims/{claim}/reject', [ClaimController::class, 'reject'])->name('finance.claims.reject');
        Route::post('/claims/{claim}/pay', [ClaimController::class, 'pay'])->name('finance.claims.pay');
        Route::get('/claims/{claim}/receipt', [ClaimController::class, 'receipt'])->name('finance.claims.receipt');
        Route::delete('/claims/{claim}', [ClaimController::class, 'destroy'])->name('finance.claims.destroy');
        Route::post('/ledger/{entry}/void', [FinanceController::class, 'voidEntry'])->name('finance.ledger.void');
        Route::get('/assets', [AssetController::class, 'index'])->name('finance.assets');
        Route::post('/assets', [AssetController::class, 'store'])->name('finance.assets.store');
        Route::post('/assets/{asset}/dispose', [AssetController::class, 'dispose'])->name('finance.assets.dispose');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('finance.assets.destroy');
        Route::get('/tax', [TaxSummaryController::class, 'index'])->name('finance.tax');
        Route::post('/drawings', [FinanceController::class, 'storeDrawings'])->name('finance.drawings.store');
        Route::get('/accountant', [AccountantPackController::class, 'index'])->name('finance.accountant');
        Route::get('/accountant/download', [AccountantPackController::class, 'download'])->name('finance.accountant.download');
        Route::get('/bank-import', [BankImportController::class, 'index'])->name('finance.bank-import');
        Route::post('/bank-import', [BankImportController::class, 'upload'])->name('finance.bank-import.upload');
        Route::delete('/bank-import', [BankImportController::class, 'clear'])->name('finance.bank-import.clear');
    });
};
// HR: its own subdomain (hr.kretiv.co) when HR_HOST is set, otherwise /hr.
$hrRoutes = function () {
    Route::middleware(['auth', 'module:hr'])->group(function () {
        Route::get('/', [MyProfileController::class, 'show'])->name('hr.home');
        Route::put('/profile', [MyProfileController::class, 'update'])->name('hr.profile.update');
        Route::get('/staff', [StaffController::class, 'index'])->name('hr.staff.index');
        Route::get('/staff/new', [StaffController::class, 'create'])->name('hr.staff.create');
        Route::get('/staff/suggest-email', [StaffController::class, 'suggestEmail'])->name('hr.staff.suggest-email');
        Route::post('/staff', [StaffController::class, 'store'])->name('hr.staff.store');
        Route::get('/staff/{user}', [StaffController::class, 'show'])->name('hr.staff.show');
        Route::put('/staff/{user}', [StaffController::class, 'update'])->name('hr.staff.update');
        Route::get('/attendance', [AttendanceController::class, 'mine'])->name('hr.attendance.mine');
        Route::get('/team/attendance', [AttendanceController::class, 'team'])->name('hr.attendance.team');
        Route::post('/team/attendance', [AttendanceController::class, 'correct'])->name('hr.attendance.correct');
        Route::get('/team/overtime', [AttendanceController::class, 'overtime'])->name('hr.overtime');
        Route::post('/team/overtime/{attendance}', [AttendanceController::class, 'decideOvertime'])->name('hr.overtime.decide');
        Route::get('/requests', [ProfileRequestController::class, 'index'])->name('hr.requests');
        Route::post('/requests/{changeRequest}', [ProfileRequestController::class, 'decide'])->name('hr.requests.decide');
        Route::get('/leave', [LeaveController::class, 'mine'])->name('hr.leave');
        Route::post('/leave', [LeaveController::class, 'store'])->name('hr.leave.store');
        Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('hr.leave.cancel');
        Route::get('/leave/{leave}/attachment', [LeaveController::class, 'attachment'])->name('hr.leave.attachment');
        Route::get('/team/leave', [LeaveController::class, 'team'])->name('hr.leave.team');
        Route::get('/team/leave/calendar', [LeaveController::class, 'calendar'])->name('hr.leave.calendar');
        Route::post('/team/leave/{leave}', [LeaveController::class, 'decide'])->name('hr.leave.decide');
        Route::get('/claims', [MyClaimController::class, 'mine'])->name('hr.claims');
        Route::post('/claims', [MyClaimController::class, 'store'])->name('hr.claims.store');
        Route::delete('/claims/{claim}', [MyClaimController::class, 'destroy'])->name('hr.claims.destroy');
        Route::get('/claims/{claim}/receipt', [MyClaimController::class, 'receipt'])->name('hr.claims.receipt');
        Route::get('/team/claims', [MyClaimController::class, 'team'])->name('hr.claims.team');
        Route::post('/team/claims/{claim}', [MyClaimController::class, 'verify'])->name('hr.claims.verify');
        Route::get('/payslips', [PayrollController::class, 'mine'])->name('hr.payslips');
        Route::get('/payslips/{payslip}/pdf', [PayrollController::class, 'pdf'])->name('hr.payslips.pdf');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('hr.payroll');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('hr.payroll.store');
        Route::get('/payroll/{run}', [PayrollController::class, 'show'])->name('hr.payroll.show');
        Route::post('/payroll/{run}/recalculate', [PayrollController::class, 'recalculate'])->name('hr.payroll.recalculate');
        Route::post('/payroll/{run}/finalize', [PayrollController::class, 'finalize'])->name('hr.payroll.finalize');
        Route::post('/payroll/{run}/statutory', [PayrollController::class, 'payStatutory'])->name('hr.payroll.statutory');
        Route::post('/payroll/{run}/reopen', [PayrollController::class, 'reopen'])->name('hr.payroll.reopen');
        Route::delete('/payroll/{run}', [PayrollController::class, 'destroy'])->name('hr.payroll.destroy');
        Route::put('/payroll/slip/{payslip}', [PayrollController::class, 'updateSlip'])->name('hr.payroll.slip');
        Route::get('/org-chart', [CompanyController::class, 'orgChart'])->name('hr.org-chart');
        Route::get('/departments', [CompanyController::class, 'departments'])->name('hr.departments');
        Route::put('/departments/{department}', [CompanyController::class, 'updateDepartment'])->name('hr.departments.update');
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('hr.announcements');
        Route::get('/announcements/new', [AnnouncementController::class, 'create'])->name('hr.announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('hr.announcements.store');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('hr.announcements.show');
        Route::post('/announcements/{announcement}/acknowledge', [AnnouncementController::class, 'acknowledge'])->name('hr.announcements.acknowledge');
        Route::get('/announcements/{announcement}/attachment', [AnnouncementController::class, 'attachment'])->name('hr.announcements.attachment');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('hr.announcements.destroy');
        Route::get('/ea-forms', [EaFormController::class, 'index'])->name('hr.ea');
        Route::post('/ea-forms/release', [EaFormController::class, 'release'])->name('hr.ea.release');
        Route::get('/ea-forms/{user}/{year}', [EaFormController::class, 'pdf'])->whereNumber('year')->name('hr.ea.pdf');
        Route::get('/audit-log', [AuditLogController::class, 'hr'])->name('hr.audit');
        Route::get('/holidays', [HolidayController::class, 'index'])->name('hr.holidays');
        Route::post('/holidays', [HolidayController::class, 'store'])->name('hr.holidays.store');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('hr.holidays.destroy');
    });
};
if ($host('hr')) {
    Route::domain($host('hr'))->group($hrRoutes);
} else {
    Route::prefix('hr')->group($hrRoutes);
}

if ($host('finance')) {
    Route::domain($host('finance'))->group($financeRoutes);
} else {
    Route::prefix('finance')->group($financeRoutes);
}
