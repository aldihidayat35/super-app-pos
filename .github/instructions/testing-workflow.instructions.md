---
description: "Use when menyelesaikan todo di proyek Laravel GudangToko. Menegakkan workflow testing (composer test, curl HTTP, render view CLI, browser visual check) setiap kali todo ditandai selesai."
name: "Testing Workflow Setelah Todo"
applyTo: "**"
---

# Workflow Testing Setelah Menyelesaikan Todo

Aturan ini memastikan setiap todo yang ditandai selesai sudah benar-benar teruji, bukan hanya "kelihatan benar".

## 1. Wajib jalankan pengujian setiap todo selesai

Setiap todo yang ditandai selesai **harus** disertai bukti pengujian. Pilih jenis pengujian yang relevan dengan perubahan:

| Jenis perubahan | Pengujian wajib |
|---|---|
| Backend (controller, service, action, policy, middleware) | `composer test` + `curl` ke endpoint terkait |
| View / Blade component | Render via PHP CLI + `curl` HTTP + cek visual via browser |
| Seeder / migration / factory | `php artisan migrate:fresh --seed --env=testing --force --seeder=NamaSeeder` + verifikasi data via tinker/sqlite3 |
| Route / config / middleware | `php artisan route:clear` + `composer test` + `curl` ke URL yang relevan |
| Asset (Vite, JS, CSS) | `npm run build` + `curl` + cek visual via browser |
| Frontend interaktif (form, modal, DataTable) | Cek visual via browser + interaksi (klik, submit) |

## 2. Urutan eksekusi standar

1. **Bersihkan cache**:

   ```bash
   php artisan view:clear
   php artisan config:clear
   php artisan route:clear
   ```

2. **Jalankan pengujian** sesuai tabel di atas.
3. **Verifikasi visual via browser** untuk perubahan UI: buka halaman terkait, cek render, interaksi, dan tidak ada error console.
4. **Cek log** untuk error terbaru:

   ```bash
   # lihat error terbaru
   tail -n 50 storage/logs/laravel.log
   ```

5. **Lapor hasil** ke user dalam format daftar bernomor sesuai [bahasa-indonesia.instructions.md](../../bahasa-indonesia.instructions.md) di root project.

## 3. Format laporan hasil ke user

Setiap todo selesai, laporkan dengan struktur:

1. ✅ **Yang sudah diuji** — jenis test + hasilnya (lulus/gagal + ukuran/angka).
2. ❌ **Yang masih gagal** (jika ada) — error + lokasi + langkah perbaikan.
3. ⚠️ **Yang perlu perhatian** — peringatan, risiko, atau asumsi.
4. 🔧 **Langkah berikutnya** — apa yang harus dilakukan selanjutnya.

Tandai todo sebagai **completed** hanya jika SEMUA pengujian relevan sudah hijau.

## 4. Aturan wajib dan larangan

### Wajib ✅

- ✅ Jalankan `composer test` minimal untuk perubahan yang menyentuh logika domain.
- ✅ `curl` ke endpoint dengan status code valid (200/302/422, BUKAN 500).
- ✅ Render view via PHP CLI (`storage/app/render_check.php`) untuk dapat stack trace jika error render.
- ✅ Buka halaman terkait via browser tool untuk verifikasi visual jika perubahan menyentuh UI.
- ✅ Bersihkan file debug `storage/app/*.php` atau `*.html` sebelum menandai todo selesai.
- ✅ Jalankan `php artisan view:clear` setelah mengubah file Blade.

### Larangan ❌

- ❌ **Jangan tandai todo "selesai" tanpa bukti pengujian** yang ditampilkan ke user.
- ❌ **Jangan skip test** hanya karena "kelihatan benar" atau "perubahan kecil".
- ❌ **Jangan tinggalkan file debug** di `storage/app/` setelah testing.
- ❌ **Jangan jalankan `composer ci`** di database development/production — hanya di testing.
- ❌ **Jangan nyatakan berhasil** jika `curl` mengembalikan 0 byte, status 500, atau HTML kosong.
- ❌ **Jangan abaikan error di log** — error baru di `storage/logs/laravel.log` harus dilaporkan.

## 5. Akun & endpoint untuk testing

- **Akun demo lokal**: `superadmin@gudangtoko.test` / `password` (lihat [docs/TESTING.md](../../docs/TESTING.md)).
- **Server dev lokal**: `php artisan serve` di `http://127.0.0.1:8000` atau virtual host Laragon `http://gudangtoko.test`.
- **PHP binary Laragon**: `C:\laragon\bin\php\php-8.3.31-nts-Win32-vs16-x64\php.exe`.
- **Cookie session**: `gudangtoko-session` untuk otentikasi via `curl`.
- **Field login**: `login` (menerima email atau username) — bukan `email`.

## 6. Referensi cepat

- Aturan bahasa & format jawaban: [bahasa-indonesia.instructions.md](../../bahasa-indonesia.instructions.md)
- Konvensi proyek Laravel: [AGENTS.md](../../AGENTS.md)
- Domain & kontrak: [docs/DOMAIN.md](../../docs/DOMAIN.md), [docs/SRS.md](../../docs/SRS.md)
- Daftar perintah testing: [docs/TESTING.md](../../docs/TESTING.md)
- Daftar akun deterministic: [docs/TESTING.md](../../docs/TESTING.md#akun-testing-deterministic)