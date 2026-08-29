<?php

use Illuminate\Support\Facades\Route;

// jobs.kretiv.co — Jobs module. Populated in Phase 2 when the Jobs
// module is ported in from the standalone jobs.kretiv.co app; for now
// this just confirms the subdomain resolves and the shared session
// carries across from the hub.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'jobs.placeholder' : 'login');
});

Route::get('/placeholder', function () {
    return 'Jobs module — coming in Phase 2. Logged in as: '.(auth()->user()->name ?? 'guest');
})->middleware(['auth'])->name('jobs.placeholder');
