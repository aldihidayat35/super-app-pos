# Deployment, Go-Live, Backup, dan Migrasi Data Awal — P28

Dokumen ini menjadi SOP rilis production GudangToko. Tidak ada credential yang boleh disimpan di repository; gunakan `.env.production.example` sebagai template tanpa secret.

## Requirement server

- Ubuntu 22.04/24.04 LTS.
- Nginx stable.
- PHP 8.3+ dengan extension: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysql`, `openssl`, `pdo_mysql`, `redis` opsional, `tokenizer`, `xml`, `zip`.
- MySQL 8/MariaDB 10.6+.
- Node.js LTS untuk build Vite.
- Composer 2.
- Redis opsional untuk cache/queue jika volume sudah tinggi.
- SSL valid dari Let’s Encrypt atau certificate resmi.
- `mysqldump` tersedia untuk backup terenkripsi baseline.

## Struktur deployment

Contoh path:

```text
/var/www/gudangtoko/current
/var/www/gudangtoko/shared/.env
/var/www/gudangtoko/shared/storage
```

Pastikan `storage/` dan `bootstrap/cache/` writable oleh user PHP-FPM.

## Environment production

1. Copy `.env.production.example` ke `.env`.
2. Isi `APP_KEY`, database, mail, WA/Telegram token, dan secret lain di server saja.
3. Pastikan:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SECURITY_BACKUP_ENABLED=true
```

## Nginx

Contoh server block:

```nginx
server {
    listen 80;
    server_name gudangtoko.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name gudangtoko.example.com;
    root /var/www/gudangtoko/current/public;
    index index.php;

    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Script deploy idempotent

Script tersedia di `scripts/deploy-production.sh`.

Langkah utama:

```bash
export APP_DIR=/var/www/gudangtoko/current
bash scripts/deploy-production.sh
```

Urutan script:

1. Maintenance mode.
2. `git pull --ff-only`.
3. `composer install --no-dev --optimize-autoloader`.
4. `npm ci && npm run build`.
5. `php artisan migrate --force`.
6. `storage:link`.
7. Clear dan cache config/route/view.
8. Queue restart.
9. Backup dry-run.
10. Exit maintenance.
11. `php artisan about`.

## Queue worker

Contoh systemd service:

```ini
[Unit]
Description=GudangToko Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/gudangtoko/current/artisan queue:work database --sleep=3 --tries=3 --timeout=120
WorkingDirectory=/var/www/gudangtoko/current

[Install]
WantedBy=multi-user.target
```

Command:

```bash
sudo systemctl enable gudangtoko-queue
sudo systemctl start gudangtoko-queue
```

## Scheduler

Cron setiap menit:

```cron
* * * * * cd /var/www/gudangtoko/current && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler wajib menghasilkan heartbeat di `/admin/system/health`.

## Log rotation

Contoh `/etc/logrotate.d/gudangtoko`:

```text
/var/www/gudangtoko/current/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
```

## Backup dan restore drill

Backup database:

```bash
php artisan system:encrypted-backup --connection=mysql --dry-run
php artisan system:encrypted-backup --connection=mysql
```

File storage penting:

```bash
tar -czf storage-backup-$(date +%F).tar.gz storage/app/private storage/app/public
```

Retention default 14 hari. Copy offsite wajib ke storage terpisah/S3 terenkripsi.

Restore drill:

1. Download backup `.sql.enc` dari `/admin/system/backups`.
2. Dekripsi di staging menggunakan app key environment yang sesuai.
3. Restore DB staging.
4. Restore file storage staging.
5. Jalankan `composer quality` dan smoke test role.
6. Catat hasil restore dan sign-off owner.

## Staging dan smoke test setelah deploy

Minimal smoke test:

- Login super admin, owner, kepala gudang, staff gudang, kepala toko, kasir, langganan owner.
- Buka dashboard sesuai role.
- Cek `/admin/system/health`.
- Cek `/admin/system/backups`, `/admin/system/logs`, `/admin/system/imports`, `/admin/system/maintenance`.
- Buat transaksi dummy hanya di staging: PO -> receipt -> transfer -> POS -> closing.
- Cek queue worker dan scheduler heartbeat.
- Cek notifikasi dry-run/non-dry-run sesuai environment.

## Rollback

Rollback aplikasi:

```bash
php artisan down --render="errors::503"
git checkout <previous-tag-or-commit>
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Rollback database:

- Jangan rollback migration destruktif langsung di production.
- Migration yang drop/rename kolom/tabel harus ditandai tidak aman dan punya backup restore plan.
- Jika data berubah, restore dari backup terakhir ke staging dulu, validasi, baru production pada maintenance window.

## Migrasi data awal

Template wajib:

- Supplier.
- Customer.
- Product.
- Opening stock.
- User.
- Warehouse/branch.

Alur:

1. Freeze data manual.
2. Backup database dan file.
3. Upload template di `/admin/system/imports`.
4. Preview dry-run.
5. Perbaiki error validation report.
6. Reconcile total SKU, qty, nilai HPP, customer, supplier.
7. Opening stock diposting sebagai dokumen opname khusus melalui InventoryService, bukan insert saldo langsung.
8. Owner sign-off.
9. Go-live.

## Go-live checklist

- Freeze data manual.
- Backup final.
- Import master dan opening stock.
- Verifikasi stok dan nilai persediaan.
- Training user.
- Test printer receipt dan barcode scanner.
- Test POS shift.
- Test notification.
- Support channel aktif.
- Monitoring 7 hari pertama.
- Restore drill terdokumentasi.

## SOP incident

1. Catat waktu, user terdampak, modul, dan error.
2. Cek `/admin/system/logs`.
3. Jika transaksi stok/uang terdampak, hentikan modul terkait dan jangan hapus data.
4. Gunakan reversal/void/koreksi sesuai aturan audit.
5. Eskalasi ke owner dan developer.
6. Setelah fix, buat marker resolve di log dan audit.

## Handover 6 bulan

- Jadwal patch dependency bulanan.
- Review backup restore tiap bulan.
- Review slow query dan disk usage tiap bulan.
- Review user/role/permission tiap bulan.
- Review log security dan failed job mingguan.
