# Modul Notifikasi dan Laporan Harian

Modul P25 menyediakan konfigurasi WhatsApp API generic, Telegram Bot, template pesan, jadwal laporan, penerima bertingkat, log pengiriman, token laporan aman, dan aturan alert bisnis.

Provider WhatsApp Baileys perusahaan dikelola dari `/admin/integrasi/whatsapp`. Panduan konfigurasi, QR, session persisten, dan queue tersedia di [WHATSAPP-GATEWAY.md](WHATSAPP-GATEWAY.md). Provider generic lama tetap didukung.

## Prinsip keamanan

- Secret channel disimpan pada kolom `credentials` dengan cast `encrypted:array`.
- Token laporan harian hanya disimpan dalam bentuk hash SHA-256 pada `secure_report_tokens`.
- Response provider disanitasi sebelum masuk `notification_logs`.
- Default `.env.example` memakai `NOTIFICATION_DRY_RUN=true` agar tidak ada pengiriman keluar tanpa konfigurasi eksplisit.

## Command

```bash
php artisan notifications:run-schedules
php artisan notifications:business
php artisan notifications:business --owner-report
php artisan reports:send-daily --date=2026-07-14
php artisan reports:send-daily --sync
```

Scheduler menjalankan `notifications:run-schedules` setiap menit. Command membaca `notification_schedules` yang sudah jatuh tempo, membuat snapshot `daily_reports`, lalu mengirim via queue/job dengan idempotency key.

`notifications:business` berjalan setiap 15 menit untuk memeriksa stok kritis, piutang jatuh tempo, dan order B2B yang tertunda. Pemeriksaan memakai jeda serta kunci antiduplikat dari pengaturan kejadian bisnis. `notifications:business --owner-report` dijalankan otomatis pukul 21.00 zona waktu Asia/Jakarta.

Notifikasi langsung dibuat dari service domain ketika:

- permintaan restok diajukan, disetujui, atau ditolak;
- pembelian darurat dibuat atau pembeliannya dicatat;
- approval umum dibuat, disetujui, atau ditolak;
- closing shift memiliki selisih kas;
- status penting order B2B berubah;
- kepala toko atau kepala gudang menyelesaikan checklist harian.

Semua pesan masuk `notification_logs` dan dikirim oleh `SendNotificationJob`. Karena itu perubahan domain tidak menunggu respons gateway WhatsApp.

## Halaman

- `NTF-01 /admin/notifications/channels`
- `NTF-02 /admin/notifications/templates`
- `NTF-03 /admin/notifications/schedules`
- `NTF-04 /admin/notifications/recipients`
- `NTF-05 /admin/notifications/logs`
- `NTF-06 /reports/daily/{token}`
- `NTF-07 /admin/notifications/alerts`
- `NTF-08 /admin/notifications/pengaturan-bisnis`

Halaman **Aktif/Nonaktif Notifikasi** mengatur setiap kejadian bisnis secara terpisah. Perubahan berlaku untuk pesan baru dan tidak menghapus riwayat lama. `cooldown_minutes` mencegah hasil scanner yang sama dikirim berulang kali dalam rentang yang ditentukan.

## Permission

- `notifications.view`
- `notifications.update`
- `notifications.send`

Role `super_admin` mendapat akses penuh melalui wildcard. `admin_config` dan `owner_approver` dapat mengelola aktif/nonaktif kejadian bisnis. `owner_viewer` hanya dapat membaca.
