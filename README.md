# Kretiv.OS

Unified company platform for Kretivco Mediaworks — one Laravel app serving
multiple business modules, each on its own subdomain, self-hosted on
Exabytes cPanel.

- **Hub** (`kretivos.kretiv.co`) — login, dashboard, module launcher.
- **Jobs** (`jobs.kretiv.co`) — job queue, customers, vendors, leads/CRM.
  Ported from the standalone [`jobs.kretiv.co`](https://github.com/afiqazlan17/jobs.kretiv.co) app.
- **Finance** (`finance.kretiv.co`) — ledger, reports, invoices/receipts.
  Called directly by the Jobs module (same app, no API layer).
- **HR** — planned, scoped once Finance's cross-module pattern is proven.

## Architecture

One Laravel app, one database, one shared login — not separate
microservices per module. `Route::domain()` dispatches each subdomain to
its own route file (`routes/hub.php`, `routes/jobs.php`,
`routes/finance.php`); `SESSION_DOMAIN=.kretiv.co` (leading dot) makes the
login session valid across every subdomain, which is the entire
cross-module "SSO" mechanism — no OAuth/token service involved. Modules
call each other as plain PHP service classes (`app/Domain/{Module}/`),
not HTTP.

## Stack

- Laravel 13, Breeze (Blade + Alpine.js), MySQL, Tailwind CSS, Vite
- No SSH/Terminal on the target hosting plan — see `DEPLOYMENT.md`

## Local development

Subdomain routing needs `/etc/hosts` entries since `php artisan serve`
alone won't route by Host header:

```
127.0.0.1 kretivos.test jobs.kretivos.test finance.kretivos.test
```

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build   # or `npm run dev` for hot-reload
php artisan serve --host=0.0.0.0 --port=8000
# visit http://kretivos.test:8000
```

## Deployment

See `DEPLOYMENT.md` for the full Exabytes cPanel process — every module
subdomain points to the same `public/` docroot, since it's one app.
