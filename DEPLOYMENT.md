# Deploy Kretiv.OS ke Exabytes cPanel (AI Pro)

Kretiv.OS ialah **satu** app Laravel yang serve beberapa subdomain (satu
per module) — `kretivos.kretiv.co` (hub), `jobs.kretiv.co`, `finance.kretiv.co`,
dsb. Semua subdomain tu point ke **docroot yang sama** (`public/` repo ni);
Laravel sendiri yang dispatch berdasarkan Host header (`Route::domain(...)`
dalam `routes/web.php`) — bukan deployment berasingan setiap module.

Proses dan gotcha di bawah sama macam `jobs.kretiv.co`/`terra_lestari` yang
dah confirmed berfungsi atas akaun cPanel yang sama.

## 1. Cipta database MySQL

cPanel → **MySQL Database Wizard** → cipta database + user + assign All
Privileges. Simpan nama database/username/password untuk `.env`.

## 2. Upload kod

cPanel → **Git Version Control** → **Create**, Repository URL
`https://github.com/afiqazlan17/kretiv-os.git`, branch `main`, deploy ke
folder di luar `public_html` (contoh `/home/cpaneluser/kretiv-os`).

**Nota — repo private**: cPanel tak boleh clone/pull repo GitHub private
tanpa auth. Cara proven yang kita guna untuk `jobs.kretiv.co`: tukar repo
jadi **Public** sementara semasa clone/pull, then tukar balik **Private**
lepas siap. (Cubaan guna SSH deploy key + `.git/config` gagal sebab cPanel
punya UI "Remote URL" tak boleh diedit selepas repo dicipta — kekal guna
cara public-sementara ni.)

## 3. Set Document Root — SETIAP subdomain point ke `public/` yang SAMA

Ini bahagian paling penting untuk Kretiv.OS (beza dari `jobs.kretiv.co`
yang cuma satu domain): dalam **Domains**/**Subdomains**, cipta subdomain
untuk **setiap** module (`kretivos`, `jobs`, `finance`, `hr`) tapi set
Document Root semua sekali ke path **sama**:
`/home/cpaneluser/kretiv-os/public`. Jangan bagi mana-mana subdomain punya
docroot sendiri berasingan — satu app, satu docroot, banyak Host header.

## 4. Setup `.env`

```
APP_NAME="Kretiv.OS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kretivos.kretiv.co

KRETIVOS_HUB_DOMAIN=kretivos.kretiv.co
KRETIVOS_JOBS_DOMAIN=jobs.kretiv.co
KRETIVOS_FINANCE_DOMAIN=finance.kretiv.co
KRETIVOS_HR_DOMAIN=hr.kretiv.co

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<username>
DB_PASSWORD=<password>

SESSION_DOMAIN=.kretiv.co
QUEUE_CONNECTION=sync
```

`SESSION_DOMAIN=.kretiv.co` (leading dot) ialah **seluruh mekanisme SSO** —
satu login di `kretivos.kretiv.co` akan valid serta-merta di
`jobs.kretiv.co`/`finance.kretiv.co` sebab cookie session dikongsi merentasi
semua subdomain `*.kretiv.co`. Tiada OAuth/token berasingan diperlukan.

Generate `APP_KEY` locally (`php artisan key:generate --show`), copy nilai
ke `.env` server (takde Terminal untuk run kat server).

## 5. Migrate database, storage:link

Sama cara macam `jobs.kretiv.co` — run sekali via Cron Job guna
`/usr/local/bin/ea-php84`, padam cron job lepas confirm hasil (lihat
bahagian bawah).

## 6. Cloudflare DNS

Setiap subdomain (`kretivos`, `jobs`, `finance`, `hr`) perlukan A record
sendiri di Cloudflare (bukan cPanel Zone Editor), **DNS only** (bukan
proxied), point ke IP server yang sama.

## Realiti deployment di Exabytes (cPanel AI Pro, tiada Terminal/SSH)

Sama batasan macam `jobs.kretiv.co`:

- **PHP binary untuk cron**: `/usr/local/bin/ea-php84`
- **Composer tak boleh jalan via cron** (network outbound disekat) — build
  `vendor/` dan `public/build/` secara **local**, zip, upload via File
  Manager, extract ke folder repo (bukan dalam `public/`).
- **Jangan run `config:cache`** — OPcache serve bytecode lama.
- **Cron job "one-off"**: set masa akan datang, check log, **padam
  serta-merta** lepas confirm berjaya — jangan biar berulang.
- **Fail log/script sementara** (migrate log, bootstrap script yang ada
  plaintext password): padam serta-merta lepas guna.
