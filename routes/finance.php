<?php

use Illuminate\Support\Facades\Route;

// finance.kretiv.co — Finance module. Populated in Phase 3 when Finance
// is exploded out of the Jobs module into its own standalone module.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'finance.placeholder' : 'login');
});

Route::get('/placeholder', function () {
    return 'Finance module — coming in Phase 3. Logged in as: '.(auth()->user()->name ?? 'guest');
})->middleware(['auth'])->name('finance.placeholder');
