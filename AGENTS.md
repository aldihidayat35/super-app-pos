# AGENTS.md — GudangToko

Panduan ini untuk AI coding agent (GitHub Copilot, Claude Code, dll.) yang membantu pengembangan aplikasi GudangToko. Baca dokumen ini sebelum menjalankan task apa pun.

## 1. Identitas proyek

- **Nama**: GudangToko (`gudangtoko/application`)
- **Tipe**: Modular monolith Laravel 12 untuk manajemen gudang, toko internal, dan pelanggan langganan/B2B.
- **Stack**: PHP 8.3+, Laravel 12, Blade, Bootstrap 5, Metronic 8, Vite, MySQL/SQLite.
- **Bahasa**: Wajib jawab dan dokumentasikan dalam bahasa Indonesia. Lihat [bahasa-indonesia.instructions.md](bahasa-indonesia.instructions.md).

## 2. Struktur direktori penting

| Path | Fungsi |
|---|---|
| `app/Http/Controllers/<Module>/` | Controller per modul: `Admin`, `Warehouse`, `Retail`, `B2B`, `Purchasing`, `Attendance`, `Receivables`, `Reports`, `Auth`, `System`, `Notifications`, `Pricing`, `Returns`, `Shipment`, `Payment`, `Invoice`, `Dashboard`. |
| `app/Models/` | Eloquent model. |
| `app/Policies/` | Policy Spatie Permission (`StockOpnamePolicy`, dll). |
| `app/Services/` | Service layer domain (mis. `Warehouse/StockOpnameService`). |
| `app/Actions/` | Action class untuk use case tertentu (mis. `Auth/AuthenticateUserAction`). |
| `app/Enums/` | Enum domain (`StockOpnameStatus`, `AttendanceStatus`, dll). |
| `app/Http/Requests/` | FormRequest untuk validasi. |
| `app/Http/Middleware/` | Middleware (`SecureResponseHeaders`, `ResolveWorkLocation`, `EnsureUserIsActive`). |
| `app/Support/` | Helper global (`qty()`, `qty_input()`, `CurrencyFormatter`). |
| `resources/views/<module>/<page>.blade.php` | View Blade per modul. Layout utama: `layouts/metronic.app`. |
| `resources/views/components/metronic/` | Komponen Blade: `page-title`, `card`, `form-group`, `status-badge`, `page-guide`, `empty-state`. |
| `resources/js/vendor.js`, `resources/js/app.js` | Entry Vite. Frontend stack: jQuery, Bootstrap 5, DataTables, Select2, SweetAlert2, Flatpickr. |
| `resources/css/app.css` | Custom CSS (Tailwind v4). |
| `database/seeders/` | `DemoFullApplicationSeeder`, `TestingScenarioSeeder`, `WarehouseSeeder`, `LocalDatabaseSeeder`. |
| `database/migrations/` | Migration urutan waktu. |
| `database/factories/` | Factory per model. |
| `config/` | Konfigurasi (`rbac.php`, `gudangtoko.php`, `security.php`, `permission.php`, `navigation.php`). |
| `routes/web.php` | Semua route web (filament-style grouping per modul). |
| `tests/Feature/`, `tests/Unit/` | PHPUnit + SQLite in-memory. |
| `docs/` | Dokumen kontrak domain (`DOMAIN.md`, `SRS.md`, `STATE-MACHINES.md`, `TESTING.md`, `SECURITY.md`, `PERFORMANCE.md`, `DEPLOYMENT.md`, `NOTIFICATIONS.md`, `BACKLOG.md`, `DEMO-SEED.md`). |
| `guide/` | Guidebook per role (`super-admin.md`, `gudang.md`, `owner.md`, `toko-internal.md`, `langganan-b2b.md`). |

## 3. Command penting

### Setup & Development

```bash
# Install dependencies
composer install
npm install

# Build asset
npm run build              # production
npm run dev                # watch

# Server lokal Laragon
php artisan serve
# atau gunakan virtual host Laragon http://gudangtoko.test

# Storage link
php artisan storage:link
```

### Quality (wajib dijalankan sebelum commit)

```bash
composer lint              # Laravel Pint
composer analyse           # Larastan / PHPStan level 6
composer test              # PHPUnit (SQLite in-memory)
composer quality           # lint + analyse + test
composer test:critical     # unit + e2e + daily report test
composer ci                # full pipeline: config:clear + migrate:fresh seed --env=testing + lint + analyse + test
```

### Database

```bash
php artisan migrate
php artisan migrate:fresh --seed --env=testing --force --seeder=TestingScenarioSeeder
php artisan db:seed --class=DemoFullApplicationSeeder       # APP_ENV=local only
php artisan db:seed --class=TestingScenarioSeeder            # local/testing only
```

### Cache & queue

```bash
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan queue:work --tries=3 --timeout=120
php artisan schedule:work          # dev
```

## 4. Konvensi Laravel spesifik proyek

### View Blade

- Setiap view halaman **wajib** memulai dengan `@extends('layouts.metronic.app')`. View tanpa `@extends` akan render kosong tanpa error.
- Komponen Blade reusable ada di `resources/views/components/metronic/`. Gunakan `<x-metronic.card>`, `<x-metronic.form-group>`, `<x-metronic.status-badge>`, `<x-metronic.page-title>`, `<x-metronic.empty-state>`, `<x-metronic.page-guide>`.
- Komponen `page-guide` memakai slot `function`, `workflow`, `parts`, `impacts`, `operation`, `warnings`, `example`. **Jangan** buat `<x-slot:parts>` bertingkat karena Blade tidak mendukung nested slot dengan nama yang sama — itu akan menyebabkan view render kosong.
- Form section `@section('page_guide')` membuat tombol panduan (icon "?" di header halaman). Slot wajib diisi ringkas.

### Controller

- Penamaan: `app/Http/Controllers/<Module>/<Entity>Controller.php`.
- `index()` harus memanggil `$this->authorize('viewAny', <Model>::class)` sebelum akses data.
- Akses lokasi kerja user via `$request->user()->permittedWorkLocationIds()`.
- Filter request pakai `$request->only(['field1', 'field2'])`.

### Form & Validasi

- FormRequest disimpan di `app/Http/Requests/<Module>/<Action><Entity>Request.php`.
- Atribut label Bahasa Indonesia menggunakan method `attributes()`.

### Middleware

- `web` group sudah menambahkan `SecureResponseHeaders` (CSP, X-Frame-Options, dll).
- Alias middleware: `permission`, `role`, `work.location`, `active.user`, `health.access`, `b2b.customer`, `internal.access`. Definisikan di `bootstrap/app.php`.

### Enums & Domain

- Pakai enum di `app/Enums/` (mis. `StockOpnameStatus`, `AttendanceStatus`).
- Ubah state pakai method spesifik (`post()`, `approve()`, `reject()`, `void()`, `reverse()`) — **jangan** pakai method generik `process()`.
- Transaksi final hanya `void` atau `reverse`; `delete` hanya untuk draft/master aman.

### RBAC

- Permission Spatie: mis. `stock.view`, `stock.create`, `stock_adjustments.view`, `admin.users.view`. Granular dan hindari permission terlalu luas.
- Cek permission via `$user->can(...)` di policy atau `->middleware('permission:nama.permission')` di route.

### Frontend (Vite + Metronic 8)

- CSS bundle Metronic ada di `public/assets/vendor/metronic/css/`.
- Runtime Metronic di `resources/js/vendor/metronic/`.
- Plugin pihak ketiga dibundel lewat `resources/js/vendor.js`.
- Custom CSS di `resources/css/app.css` (Tailwind v4).
- **Jangan** pakai CDN untuk JS Metronic — semua lewat Vite.

## 5. Aturan penting & jebakan umum

- ❌ **Jangan buat view tanpa `@extends('layouts.metronic.app')`** — akan render body 0 byte tanpa error log.
- ❌ **Jangan buat nested slot dengan nama yang sama** pada komponen Blade (`<x-slot:parts>` di dalam `<x-slot:parts>`).
- ❌ **Jangan pakai `float` untuk nominal/qty/HPP** — decimal(18,2) atau decimal(18,4). Lihat [docs/DOMAIN.md](docs/DOMAIN.md).
- ❌ **Jangan edit histori** (mutation, payment, invoice issued). Pakai `void` atau `reverse`.
- ❌ **Jangan jalankan `LocalDatabaseSeeder` di production** — seeder punya guard `APP_ENV=local`.
- ❌ **Jangan jalankan `composer ci` di database development/production** — hanya di `testing`.
- ✅ Login form menggunakan field `login` (bukan `email`) — menerima email atau username.
- ✅ Akun demo lokal password selalu `password`.
- ✅ Akun deterministic testing di [docs/TESTING.md](docs/TESTING.md).
- ✅ Number formatting: pakai helper `qty($value)` atau `App\Support\CurrencyFormatter::rupiah($value)`.
- ✅ PHP binary lokal Laragon: `C:\laragon\bin\php\php-8.3.31-nts-Win32-vs16-x64\php.exe`.

## 6. Alur kerja perbaikan bug

1. Cek HTTP response aktual dengan `curl` ke endpoint, lihat ukuran body dan status code.
2. Render view langsung via CLI untuk dapat stack trace lengkap (`storage/app/render_check.php`).
3. Cek `storage/logs/laravel.log` untuk error terbaru.
4. Untuk otentikasi test, gunakan cookie `gudangtoko-session` dan field `login`+`password` di POST `/login`.
5. Setelah perbaikan, jalankan `php artisan view:clear` lalu `composer test`.
6. Hapus file debug di `storage/app/*.php` atau `*.html` sebelum commit.

## 7. Daftar peran (role) dan modul utama

| Role | Modul/fokus |
|---|---|
| `super_admin` | Akses penuh sistem. |
| `owner_approver` | Owner melihat approval + laporan margin sensitif. |
| `kepala_gudang`, `staff_gudang`, `purchasing` | Warehouse, PO, GR, transfer, opname. |
| `kepala_toko`, `kasir` | POS, shift, retail return. |
| `langganan_owner`, `langganan_pic` | B2B order, katalog pribadi. |
| `admin_user`, `admin_config` | Master data dan konfigurasi. |

Daftar route + permission setiap modul ada di [guide/super-admin.md](guide/super-admin.md), [guide/gudang.md](guide/gudang.md), [guide/toko-internal.md](guide/toko-internal.md), [guide/langganan-b2b.md](guide/langganan-b2b.md).

## 8. Sumber referensi cepat

- SRS & kontrak domain: [docs/SRS.md](docs/SRS.md), [docs/DOMAIN.md](docs/DOMAIN.md)
- State machine: [docs/STATE-MACHINES.md](docs/STATE-MACHINES.md)
- Aturan security: [docs/SECURITY.md](docs/SECURITY.md)
- Performa target: [docs/PERFORMANCE.md](docs/PERFORMANCE.md)
- Notifikasi channel: [docs/NOTIFICATIONS.md](docs/NOTIFICATIONS.md)
- Deployment & backup: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- Demo seed: [docs/DEMO-SEED.md](docs/DEMO-SEED.md)
- Backlog fase: [docs/BACKLOG.md](docs/BACKLOG.md)
- Testing: [docs/TESTING.md](docs/TESTING.md)
- Bahasa & format jawaban: [bahasa-indonesia.instructions.md](bahasa-indonesia.instructions.md)