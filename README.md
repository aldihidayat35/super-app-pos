# GudangToko

Baseline modular monolith Laravel 12 untuk Manajemen Gudang, Toko Internal, dan Pelanggan Langganan/B2B. Proyek menggunakan PHP 8.3+, Blade, Bootstrap 5, dan Metronic 8.

## Persyaratan

- PHP 8.3+ dengan ekstensi `intl`, `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, dan `fileinfo`.
- Composer 2.
- MySQL 8+/MariaDB yang kompatibel.
- Node.js dan NPM.
- Redis opsional untuk cache/queue production.

## Instalasi Windows/Laragon

```powershell
cd C:\laragon\www\super-app-pos
Copy-Item .env.example .env
composer install
php artisan key:generate
```

Buat database `gudangtoko` melalui HeidiSQL/phpMyAdmin, periksa `DB_*` pada `.env`, lalu jalankan:

```powershell
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan db:seed
```

Tambahkan virtual host Laragon `gudangtoko.test` atau jalankan `php artisan serve`.

## Instalasi Linux

```bash
cp .env.example .env
composer install --no-interaction
php artisan key:generate
php artisan migrate
php artisan storage:link
npm ci
npm run build
```

Pastikan web server mengarah ke folder `public/` dan user web server dapat menulis ke `storage/` serta `bootstrap/cache/`.

## Database, cache, session, dan queue

Default contoh memakai database:

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

Untuk Redis, ubah menjadi:

```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Jalankan queue worker melalui process manager di production:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Scheduler development:

```bash
php artisan schedule:work
```

Cron production:

```cron
* * * * * cd /path/to/gudangtoko && php artisan schedule:run >> /dev/null 2>&1
```

## Seeder lokal

`DatabaseSeeder` hanya memanggil data demo ketika `APP_ENV=local`. Semua akun lokal menggunakan kata sandi `password`:

| Role | Email |
|---|---|
| `super_admin` | `admin@gudangtoko.test` |
| `owner` | `owner@gudangtoko.test` |
| `kepala_gudang` | `gudang@gudangtoko.test` |
| `kepala_toko` | `retail@gudangtoko.test` |

Jangan menjalankan `LocalDatabaseSeeder` di production; seeder memiliki environment guard.

## Quality checks

```bash
composer lint
composer analyse
composer test
composer quality
```

Test menggunakan SQLite in-memory dan tidak menyentuh database development.

## Health page

Buka `/system/health`. Route dapat diakses tanpa login hanya pada environment `local`. Di environment lain user harus login dan memiliki permission `system.health.view`.

Pemeriksaan meliputi koneksi database, disk public/storage link, konfigurasi queue, heartbeat scheduler, serta versi Laravel/PHP. Status queue worker yang sesungguhnya tetap harus dipantau oleh Supervisor/systemd/Horizon.

## Asset Metronic

- CSS bundle resmi yang dipakai berada di `public/assets/vendor/metronic/css`.
- Runtime Metronic berada di `resources/js/vendor/metronic` dan dibangun melalui entry vendor Vite.
- Plugin frontend pihak ketiga dibundel melalui `resources/js/vendor.js`.
- CSS dan JavaScript khusus aplikasi berada di `resources/css/app.css` dan `resources/js/app.js`.

Jalankan `npm run build` setelah perubahan asset. Halaman aplikasi tidak bergantung pada CDN JavaScript atau tautan demo Metronic.

## Struktur modular

Kode bisnis ditempatkan per domain di `app/Domain/<Module>` dengan `Actions`, `Services`, `Enums`, `Events`, `Policies`, dan `Models` seperlunya. HTTP controller tetap di `app/Http/Controllers`, request validation di `app/Http/Requests`, job berat di `app/Jobs`, dan efek samping di listener/queue.

Tidak digunakan package modular pihak ketiga. Detail kontrak domain dan fase implementasi berada di folder `docs/`.
