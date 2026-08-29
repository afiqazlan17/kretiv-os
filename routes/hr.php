<?php

use App\Domain\HR\Http\Controllers\AttendanceController;
use App\Domain\HR\Http\Controllers\LeaveController;
use App\Domain\HR\Http\Controllers\PayrollController;
use App\Domain\HR\Http\Controllers\StaffProfileController;
use App\Domain\HR\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// hr.kretiv.co — HR module. Auth relies entirely on the shared session
// set at the hub (SESSION_DOMAIN=.kretiv.co); no login form here.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'staff.index' : 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/staff', [UserController::class, 'index'])->name('staff.index');
    Route::post('/staff', [UserController::class, 'store'])->name('staff.store');
    Route::put('/staff/{user}', [UserController::class, 'update'])->name('staff.update');
    Route::post('/staff/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('staff.toggle-active');
    Route::put('/staff/{user}/profile', [StaffProfileController::class, 'update'])->name('staff.profile.update');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('/payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('/payroll/{run}/post', [PayrollController::class, 'post'])->name('payroll.post');
});
