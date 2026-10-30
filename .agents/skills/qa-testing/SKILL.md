---
name: qa-testing
description: >-
  Prosedur pengujian otomatis, analisis statis, formatting kode, dan skenario seeder
  untuk aplikasi GudangToko. Gunakan saat menjalankan pengujian (PHPUnit), memeriksa kualitas
  (Larastan & Pint), menguji seeder, atau mendiagnosis error/kegagalan test.
---

# QA & Testing Skill — GudangToko

Gunakan skill ini untuk menjalankan seluruh pipeline pengujian, validasi kualitas kode, dan eksekusi skenario seeder pada aplikasi **GudangToko**.

---

## 1. Perintah Quality Gate Utama

Jalankan perintah ini secara berurutan atau kombinasikan sebelum menandai suatu task selesai:

```bash
# 1. Formatting kode (Laravel Pint)
composer lint

# 2. Analisis Statis (Larastan / PHPStan Level 6)
composer analyse

# 3. Unit & Feature Testing (PHPUnit dengan SQLite In-Memory)
composer test

# 4. Pengujian Fitur Kritis (Unit + E2E + Daily Report)
composer test:critical

# 5. Full Quality Suite (Lint + Analyse + Test)
composer quality
```

---

## 2. Lingkungan & Akun Pengujian

- Konfigurasi test default menggunakan database SQLite in-memory (`:memory:`).
- Akun testing deterministik merujuk ke [docs/TESTING.md](file:///c:/laragon/www/super-app-pos/docs/TESTING.md).
- Seluruh password akun testing adalah `password`.
- Seeder pengujian:
  ```bash
  php artisan migrate:fresh --seed --env=testing --force --seeder=TestingScenarioSeeder
  ```

> [!CAUTION]
> - ❌ **Dilarang keras menjalankan `composer ci`** pada database development MySQL atau production. Perintah ini membersihkan database.
> - ❌ **Dilarang menjalankan `LocalDatabaseSeeder`** di environment production.

---

## 3. Investigasi & Debugging Cepat

1. **View Render Check**:
   Jika halaman merender 0 byte atau blank, buat skrip render cepat di `storage/app/render_check.php` untuk melihat error trace, jalankan via CLI:
   ```bash
   php storage/app/render_check.php
   ```
   *Wajib hapus file script tersebut setelah investigasi selesai.*
2. **Cek Log Aplikasi**:
   Periksa `storage/logs/laravel.log` untuk baris error terakhir.
3. **Bersihkan Cache**:
   ```bash
   php artisan view:clear
   php artisan route:clear
   php artisan config:clear
   ```

