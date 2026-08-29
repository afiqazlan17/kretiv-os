# Module convention

Each module gets its own namespace here — `App\Domain\Jobs`,
`App\Domain\Finance`, `App\Domain\HR` — mirroring the flat `app/`
structure a single-purpose Laravel app would use (Models, Http/Controllers,
Policies, Services), just namespaced per module instead of shared at the
top level.

Genuinely cross-module code (the `User` model, base `Controller`, shared
Blade layout components) stays in `app/Models` / `app/Http/Controllers` at
the top level — it does not belong to any one module.

A module's controllers may call another module's Services directly (e.g.
`App\Domain\Jobs\Http\Controllers\DocumentController` calling
`App\Domain\Finance\Services\LedgerService::postInvoiceEntry()`) — that's
the whole point of "one app, many modules": cross-module links are plain
PHP method calls, not an API.

Each module owns its own route file (`routes/jobs.php`, `routes/finance.php`,
...), wired to its subdomain in `routes/web.php` via `Route::domain()`.
