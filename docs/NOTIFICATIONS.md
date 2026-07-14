# Modul Notifikasi dan Laporan Harian

Modul P25 menyediakan konfigurasi WhatsApp API generic, Telegram Bot, template pesan, jadwal laporan, penerima bertingkat, log pengiriman, token laporan aman, dan aturan alert bisnis.

## Prinsip keamanan

- Secret channel disimpan pada kolom `credentials` dengan cast `encrypted:array`.
- Token laporan harian hanya disimpan dalam bentuk hash SHA-256 pada `secure_report_tokens`.
- Response provider disanitasi sebelum masuk `notification_logs`.
- Default `.env.example` memakai `NOTIFICATION_DRY_RUN=true` agar tidak ada pengiriman keluar tanpa konfigurasi eksplisit.

## Command

```bash
php artisan notifications:run-schedules
php artisan reports:send-daily --date=2026-07-14
php artisan reports:send-daily --sync
```

Scheduler menjalankan `notifications:run-schedules` setiap menit. Command membaca `notification_schedules` yang sudah jatuh tempo, membuat snapshot `daily_reports`, lalu mengirim via queue/job dengan idempotency key.

## Halaman

- `NTF-01 /admin/notifications/channels`
- `NTF-02 /admin/notifications/templates`
- `NTF-03 /admin/notifications/schedules`
- `NTF-04 /admin/notifications/recipients`
- `NTF-05 /admin/notifications/logs`
- `NTF-06 /reports/daily/{token}`
- `NTF-07 /admin/notifications/alerts`

## Permission

- `notifications.view`
- `notifications.update`
- `notifications.send`

Role `super_admin` mendapat akses penuh melalui wildcard. `admin_config` dapat mengelola konfigurasi, sedangkan owner dapat melihat log dan menjalankan pengiriman/retry sesuai kebutuhan.
