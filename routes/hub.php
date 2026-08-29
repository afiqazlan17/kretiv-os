<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// kretivos.kretiv.co — the landing page after login, and the one place
// auth (login/register/etc, see auth.php) lives. Module subdomains carry
// no login form of their own; they share this session via SESSION_DOMAIN.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    return view('hub.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
