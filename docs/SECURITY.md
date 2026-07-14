# Security Hardening — P27

Dokumen ini mencatat audit keamanan aplikasi GudangToko setelah modul P01-P26 dan perubahan P27. Tujuannya memastikan tidak ada temuan high severity yang dibiarkan terbuka sebelum production.

## Ringkasan hasil

| Area | Status | Severity | Bukti/Fix |
|---|---:|---:|---|
| Authorization server-side | Selesai | Low | Route bisnis memakai middleware `permission:*`, `auth`, `active.user`, `work.location`; halaman P27 `/admin/system/health` dan `/audit/security` dibatasi permission. |
| Active user dan session security | Selesai | Low | Middleware `active.user`, login rate limit, session regenerate, reset password, dan test P04/P26 tersedia. |
| Role/permission cache | Selesai | Low | Spatie permission dipakai dan seeder memanggil `forgetCachedPermissions()`. |
| Audit keamanan | Selesai | Low | `AUD-03 /audit/security` menampilkan login sukses/gagal, rate limit, reset password, session revoke, perubahan role/user, IP, device hash, filter, dan alert threshold. |
| Health system admin | Selesai | Low | `SYS-01 /admin/system/health` menampilkan DB/cache/session/queue/scheduler/storage/permission folder/versi/waktu tanpa secret. |
| Security response headers | Selesai | Low | Middleware `SecureResponseHeaders` menambahkan `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, dan `Permissions-Policy`. |
| Secret/token masking | Selesai | Low | `AuditLogService` dan notification dispatch melakukan redaction untuk password/token/secret/file sensitif. Test memastikan token tidak muncul mentah. |
| Private/signed files | Selesai | Medium | Route bukti pembayaran memakai signed URL; dokumen P25 daily report memakai token akses. Test P27 menambah bukti signed URL expiry. |
| File upload validation | Perlu audit lanjutan per modul | Medium | Modul avatar/bukti memakai validasi file yang ada. Sebelum production perlu sampling manual semua endpoint upload dan pembatasan storage private. |
| CSP ketat | Termitigasi bertahap | Low | Header `Content-Security-Policy-Report-Only` aktif secara default melalui `SecureResponseHeaders`. Mode enforce menunggu nonce/hash Metronic agar tidak mematahkan UI. |
| Backup/restore encrypted | Selesai baseline | Low | Command `system:encrypted-backup` tersedia, hasil terenkripsi dengan app key, dapat dijadwalkan melalui `SECURITY_BACKUP_ENABLED=true`, dan health page memunculkan status backup. |
| Webhook validation | Tidak aktif | Low | Belum ada webhook inbound production. Jika WA/payment gateway inbound ditambahkan, wajib HMAC/signature validation. |

## Checklist OWASP dan kontrol proyek

### Access control

- Semua halaman internal berada di group `auth`, `active.user`, `internal.access`, dan `work.location`.
- Aksi sensitif memakai permission eksplisit, misalnya `approvals.approve`, `pos.void`, `receivables.adjust`, `margins.view_sensitive`.
- Akses horizontal lokasi kerja dibatasi oleh `ResolveWorkLocation`, helper `permittedWorkLocationIds()`, dan test scope lokasi dari modul inventory/transfer/B2B.
- Data HPP, margin sensitif, kredit, dan piutang ditampilkan hanya pada permission terkait.

### Input, output, dan injection

- Query memakai Eloquent/query builder dan binding parameter; tidak ditemukan SQL raw dari input user pada jalur utama.
- Blade default escaping digunakan untuk output user. JSON audit ditampilkan melalui `{{ json_encode(...) }}` sehingga tetap di-escape oleh Blade.
- Form memakai CSRF Laravel. Endpoint publik terbatas memakai throttle/signed URL/token.
- Mass assignment: model utama memakai `$fillable` dan field sensitif tidak diisi langsung dari request tanpa Form Request/service.

### Authentication dan session

- Login email/username, akun nonaktif ditolak.
- Login dan reset password rate-limited.
- Session regenerate saat login, password confirmation untuk aksi sensitif, dan invalidasi session lain saat password berubah sudah berada pada fondasi auth.
- Production wajib memakai HTTPS, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax/strict`, dan `APP_DEBUG=false`.

### Audit dan privacy

- `audit_logs` append-style untuk aksi approval/security.
- Secret, token, password, attachment path, dan proof direduksi sebelum disimpan.
- Halaman `AUD-03` memberikan filter event/severity/user/IP/tanggal serta alert login gagal.
- PII hanya ditampilkan sesuai permission audit/admin. Export audit tetap perlu review scope sebelum production.

### File dan URL privat

- Bukti pembayaran memakai signed route `payments.proof`.
- Laporan harian P25 memakai token aman, bukan URL publik acak tanpa validasi.
- Upload produksi harus disimpan pada disk privat jika mengandung data pelanggan/pembayaran; public disk hanya untuk aset publik.

### Backup terenkripsi

Baseline backup tersedia melalui command berikut:

```bash
php artisan system:encrypted-backup --connection=mysql --dry-run
php artisan system:encrypted-backup --connection=mysql
```

Aktifkan scheduler production dengan konfigurasi:

```env
SECURITY_BACKUP_ENABLED=true
SECURITY_BACKUP_DISK=local
SECURITY_BACKUP_PATH=private/backups
SECURITY_BACKUP_RETENTION_DAYS=14
SECURITY_BACKUP_SCHEDULE_TIME=02:30
```

File backup disimpan terenkripsi menggunakan app key Laravel. Uji restore tetap wajib dilakukan di staging secara berkala.

### Production readiness

Wajib sebelum go-live:

1. `APP_ENV=production`.
2. `APP_DEBUG=false`.
3. `APP_KEY` unik dan tidak pernah dikomit.
4. HTTPS aktif dan `SESSION_SECURE_COOKIE=true`.
5. Queue worker dan scheduler dipantau process manager.
6. Backup database/file terenkripsi, dengan uji restore.
7. Rotasi secret WA/API/payment gateway.
8. Log akses server disimpan sesuai kebijakan retensi.
9. Pantau laporan CSP report-only sebelum mengubah `SECURITY_CSP_REPORT_ONLY=false`.

## Evidence test

- `tests/Feature/System/SecurityHardeningTest.php`
  - admin health hanya untuk permission `system.health.view`;
  - health tidak menampilkan secret;
  - security headers aktif;
  - audit security filter berjalan;
  - user tanpa permission tidak bisa membuka audit;
  - signed URL proof kedaluwarsa ditolak.
- Test P23/P25/P26 tetap memvalidasi approval/audit, token masking, dan critical journey.

## Risiko tersisa

- CSP enforce belum diaktifkan karena risiko breaking change pada Metronic. Severity low setelah report-only aktif; enforce dilakukan setelah violation report bersih.
- Backup command memakai `mysqldump` dan enkripsi berbasis APP_KEY. Untuk database sangat besar, gunakan backup engine storage/database native yang tetap terenkripsi.
- Upload endpoint utama sudah memakai validasi `file/image`, `mimes`, dan `max`; sampling manual tetap dilakukan saat UAT production.
