# Deploy ke Exabytes cPanel (AI Pro)

Sistem ni Laravel + MySQL, dibina untuk jalan kat shared hosting cPanel
(akaun sama yang host `kretiv.co`), guna subdomain `jobs.kretiv.co`. Proses
dan gotcha di bawah adalah sama macam yang confirmed berfungsi untuk sistem
`terra_lestari` (Sajian Baginda) atas akaun cPanel yang sama — rujuk
`DEPLOYMENT.md` repo tu untuk konteks lanjut.

## 1. Cipta database MySQL

Dalam cPanel → **MySQL Database Wizard**:
1. Cipta database (contoh `cpaneluser_jobskretiv`)
2. Cipta user database + password kuat
3. Assign user tu ke database dengan **All Privileges**

Simpan nama database, username, password — akan diperlukan dalam `.env`.

## 2. Upload kod

Pilihan A — **Git Version Control** (disyorkan, dalam cPanel):
1. cPanel → **Git Version Control** → **Create**
2. Repository URL: `https://github.com/afiqazlan17/jobs.kretiv.co.git`
3. Branch: `main` (atau branch yang dah di-merge)
4. Deploy ke folder di luar `public_html` root (contoh
   `/home/cpaneluser/jobs-kretiv`)

Pilihan B — Upload ZIP:
1. Zip seluruh repo (kecuali `node_modules`, `.git`, `vendor`)
2. Upload & extract guna **File Manager**

## 3. Set Document Root ke folder `public/`

**PENTING**: Laravel punya entry point ialah folder `public/`, bukan root
projek. Jangan point domain terus ke root repo — nanti semua fail source
boleh diakses terus dari browser (risiko keselamatan).

Dalam **Domains**/**Subdomains**, set "Document Root" terus ke
`/home/cpaneluser/jobs-kretiv/public`.

## 4. Setup `.env`

Copy `.env.example` ke `.env`, isi:
```
APP_NAME="Kretivco Jobs"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jobs.kretiv.co

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_jobskretiv
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=<password database>

QUEUE_CONNECTION=sync
```

Generate app key locally dan copy nilai `APP_KEY` ke `.env` server (tiada
Terminal untuk run `php artisan key:generate` di server — lihat bahagian
"Realiti deployment" di bawah).

## 5. Migrate & seed database

Run sekali via Cron Jobs (lihat bahagian bawah untuk cara).

## 6. Storage link (untuk attachment job)

```bash
php artisan storage:link
```

Kalau symlink tak jalan sebab batasan hosting, boleh guna cPanel File
Manager untuk buat symlink manual, atau serve fail melalui route Laravel
yang ada auth check (lihat pelan migrasi — attachment routes dilindungi
oleh auth middleware, bukan bergantung sepenuhnya kepada symlink public).

## 7. Permission fail

```bash
chmod -R 775 storage bootstrap/cache
```

## Nota keselamatan

- Padam semua fail `deploy*.log` / `diag.log` di root repo lepas setiap
  sesi update — fail ni boleh dedah struktur server.
- Jangan biarkan cron job "one-off" kekal aktif lepas siap digunakan —
  selalu padam di **Cron Jobs** lepas confirm hasil.

---

## Realiti deployment di Exabytes (cPanel AI Pro, tiada Terminal/SSH)

Plan hosting ni **takde Terminal/SSH access** (confirmed sama akaun yang
host `terra_lestari`). Semua command CLI (composer, artisan) kena jalan
melalui **Cron Jobs** (run sekali, dengan jadual masa akan datang, lepas tu
padam job tu).

### Setup yang confirmed berfungsi (dari pengalaman `terra_lestari`)

- **PHP binary untuk cron**: `/usr/local/bin/ea-php84` (bukan `php`
  biasa — cron punya `PATH` default terhad)
- **Composer binary**: wujud di `/usr/local/bin/composer`, tapi **tak
  boleh dipanggil dari cron** — network outbound dari proses cron nampak
  disekat (composer install hang tanpa habis, tanpa error). Jangan cuba
  run `composer install` via cron.
- **DNS**: domain `kretiv.co` guna Cloudflare (bukan DNS Exabytes) —
  rekod DNS baru (`jobs.kretiv.co`) kena ditambah di **Cloudflare
  dashboard** (proxy status: **DNS only**), BUKAN cPanel Zone Editor.
- **`config:cache`**: jangan guna. OPcache PHP-FPM server ni nampak
  simpan bytecode lama fail `bootstrap/cache/config.php` walaupun fail tu
  berubah (`opcache.validate_timestamps` kemungkinan `0`). Biarkan config
  uncached (`.env` dibaca terus setiap request).

### Cara update kod (bila ada perubahan)

1. **Aku (Claude) push kod baru ke branch `main`** di GitHub.
2. Buka cPanel → **Git Version Control** → **Manage** repo "Kretivco Jobs"
   → tab **Pull or Deploy** → **Update from Remote** (git pull).
3. **Kalau composer.json berubah** (dependency PHP baru) — `vendor/` kena
   dibina semula secara **local** (bukan di server) sebab composer tak
   boleh run di server ni:
   - Run `scripts/build-deploy-package.sh` — automate `composer install
     --no-dev --optimize-autoloader`, strip `.git`/`tests`/`docs` dari
     vendor packages (elak zip jadi bloated), `npm run build`, dan zip
     kedua-dua `vendor/` dan `public/build/` sekali gus (output dalam
     `./deploy-package/`).
   - Upload `vendor.zip` ke `jobs-kretiv/` (root, bukan dalam `public/`) via
     File Manager, extract, replace folder `vendor/` lama.
4. **Kalau ada migration database baru** — run sekali via Cron Jobs:
   ```
   cd /home/cpaneluser/jobs-kretiv && /usr/local/bin/ea-php84 artisan migrate --force > /home/cpaneluser/jobs-kretiv/deploy-update.log 2>&1
   ```
   (Set jadual 2-3 minit akan datang, check log, **padam cron job lepas
   siap** — jangan biar cron ni kekal berulang.)
5. **Kalau CSS/JS (Tailwind/Blade) berubah** — fail compiled
   (`public/build/`) kena dibina semula secara local (`npm run build`)
   dan upload/extract macam vendor/ di atas.
6. **Blade view (.blade.php) yang berubah tanpa kelas Tailwind baru** —
   biasanya auto-refresh sendiri, tak perlu apa-apa command tambahan.

## Kretiv OS domains (os.kretiv.co + jobs.kretiv.co)

One codebase serves every module; the host decides which module answers.
**Until the `*_HOST` vars are set the app behaves as one site**: login at
`/login`, launcher at `/os`. So it is safe to deploy the code first and switch
the domains on afterwards.

1. **cPanel → Domains**: add subdomain `os.kretiv.co` with the *same* document
   root as jobs (`repositories/jobs.kretiv.co/public`).
2. **Cloudflare DNS**: add `os` (same target as `jobs`). Wait for SSL/AutoSSL.
3. **`.env` on the server**, then nothing else to run (never `config:cache`):

   ```
   OS_HOST=os.kretiv.co
   JOBS_HOST=jobs.kretiv.co
   SESSION_DOMAIN=.kretiv.co
   SESSION_SECURE_COOKIE=true
   ```

4. Run the one-off migrate cron (adds `users.modules`), then delete the cron.
5. Everyone is signed out once; they sign in at `os.kretiv.co` and land on the
   launcher. Opening any `jobs.` URL while signed out redirects to OS login and
   back. `jobs.kretiv.co/login` no longer exists (404) once `OS_HOST` is set.

Local testing of the domain mode: use `lvh.me` (resolves to 127.0.0.1) — e.g.
`OS_HOST=os.lvh.me JOBS_HOST=jobs.lvh.me SESSION_DOMAIN=.lvh.me php artisan
serve --port=8001`. (`*.localhost` cookies can't be shared across subdomains.)


## Demo install (demo.kretiv.co)

A second, separate copy of KretivOS for prospects, running the made-up
company **Mirul Enterprise** (`DEMO_MODE=true`, see `config/demo.php`). It has
its own folder, database and `.env`; nothing is shared with the real system.
In demo mode: documents show the demo company (no Kretivco stamp, no DuitNow
QR, fake bank numbers), no email goes out, the demo accounts can't be changed
or locked, every page carries a "Demo mode" strip, and search engines are told
not to index it.

**One-time setup**

1. **cPanel -> MySQL Databases**: new database and user (e.g. `kretivco_demo`),
   user gets ALL privileges on that database only.
2. **cPanel -> Git Version Control -> Create**: clone the same GitHub repo (same
   way as the "KretivOS" repo) into `repositories/kretiv-os-demo`.
   Set the folder and its `public/` to **0755** (see the 404 gotcha above).
3. **File Manager**: copy `repositories/kretiv-os/vendor` into the demo folder
   (composer can't run on the server), and extract `build.zip` there too.
4. **cPanel -> Domains -> Create A New Domain**: `demo.kretiv.co`, document root
   `repositories/kretiv-os-demo/public`. **Cloudflare DNS**: add `demo` with the
   same target as `jobs`. Wait for SSL.
5. **`.env`** in the demo folder (copy `.env.example`), and fill in:

   ```
   APP_NAME="KretivOS Demo"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://demo.kretiv.co
   APP_KEY=
   DEMO_MODE=true
   DEMO_PASSWORD=demo1234
   DB_CONNECTION=mysql
   DB_DATABASE=kretivco_demo
   DB_USERNAME=kretivco_demo
   DB_PASSWORD=(the demo database password)
   MAIL_MAILER=log
   SESSION_DOMAIN=
   SESSION_SECURE_COOKIE=true
   ```

   Leave all `*_HOST` values empty (the demo runs on one host) and keep
   `SESSION_DOMAIN` empty: `.kretiv.co` there would mix the demo's login
   cookie with the real system's.
6. **One-off cron** (then delete it). It generates the key, builds and seeds
   the demo, and links uploaded files:

   ```
   cd /home/kretivco/repositories/kretiv-os-demo && /usr/local/bin/ea-php84 artisan key:generate --force > /home/kretivco/demo-setup.log 2>&1 && /usr/local/bin/ea-php84 artisan demo:reset >> /home/kretivco/demo-setup.log 2>&1 && ln -sfn /home/kretivco/repositories/kretiv-os-demo/storage/app/public /home/kretivco/repositories/kretiv-os-demo/public/storage && /usr/local/bin/ea-php84 artisan view:clear >> /home/kretivco/demo-setup.log 2>&1
   ```

7. **Permanent nightly cron** (this one stays), 3am:

   ```
   0 3 * * * cd /home/kretivco/repositories/kretiv-os-demo && /usr/local/bin/ea-php84 artisan demo:reset > /home/kretivco/demo-reset.log 2>&1
   ```

`demo:reset` wipes the database and uploads, so it refuses to run unless
`DEMO_MODE` is on, `APP_URL` is a demo address, and the database is empty or
already the demo. On the real system it always refuses.

**Accounts**: `boss@`, `finance@`, `hr@`, `staff@demo.kretiv.co`, password
`DEMO_PASSWORD`. The login page shows them as one-click buttons.

**Updating the demo** after a `git push`: Git Version Control -> the demo repo
-> **Update from Remote**; extract the same `build.zip` when CSS/JS changed and
copy `vendor/` again when `composer.lock` changed. New migrations apply at the
next nightly reset (or run the reset cron once to apply them now).
