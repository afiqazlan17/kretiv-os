<?php

use Illuminate\Support\Facades\Route;

// Kretiv.OS is one Laravel app serving several subdomains, one per module.
// Route::domain() dispatches by Host header to a per-module route file —
// no separate apps, no API layer between modules. The hub domain also
// owns auth (login/register/etc, see auth.php): a module subdomain never
// shows its own login form, it relies on the shared session cookie set
// there (SESSION_DOMAIN=.kretiv.co) and redirects to the hub's login when
// a guest hits it.
Route::domain(config('kretivos.domains.hub'))->group(function () {
    require __DIR__.'/hub.php';
    require __DIR__.'/auth.php';
});

Route::domain(config('kretivos.domains.jobs'))->group(base_path('routes/jobs.php'));

Route::domain(config('kretivos.domains.finance'))->group(base_path('routes/finance.php'));
