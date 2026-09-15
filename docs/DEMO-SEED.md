# Data Seeder Demo GudangToko

Seeder demo ini dibuat untuk environment `local` dan `testing` saja. Jangan jalankan pada production karena berisi akun dan data simulasi.

## Command

```bash
php artisan migrate
php artisan db:seed --class=DemoFullApplicationSeeder
```

Pada environment `local`, `php artisan migrate --seed` juga akan memanggil `DemoFullApplicationSeeder` melalui `DatabaseSeeder`.

## Akun Demo

Semua akun aktif, email sudah terverifikasi, dan memakai password:

```text
password
```

| Role | Email | Username | Lokasi kerja |
| --- | --- | --- | --- |
| super_admin | superadmin@gudangtoko.test | superadmin | Global |
| owner_approver | owner@gudangtoko.test | owner | Global |
| kepala_gudang + purchasing | manajemen-gudang@gudangtoko.test | manajemen-gudang | Gudang Demo Utama |
| staff_gudang | staff-gudang@gudangtoko.test | staff-gudang | Gudang Demo Utama |
| kepala_toko | toko@gudangtoko.test | toko-internal | Toko Demo Pusat |
| staf_toko | staf-toko@gudangtoko.test | staf-toko | Toko Demo Pusat |
| kasir + kepala_toko | kasir@gudangtoko.test | kasir | Toko Demo Pusat |
| langganan_owner | langganan-b2b@gudangtoko.test | langganan-b2b | Portal B2B |
| langganan_staff | pelanggan@gudangtoko.test | pelanggan | Portal B2B |

## Data yang Dibuat

- Organisasi: `Gudang Demo Utama`, `Toko Demo Pusat`, work location, zona/rak/bin.
- Master produk: kategori, brand, unit, barcode, supplier, supplier product, dan tiga produk demo.
- Inventory: saldo pembuka gudang/toko melalui `InventoryService`, stock batches, dan stock mutations append-only.
- Pricing: price rule dan product price POS.
- Etalase toko: lokasi pajang area/rak/tingkat untuk produk demo dan akun staf toko.
- Purchasing: PO parsial dan goods receipt posted dengan QC, histori HPP, dan supplier score.
- Transfer: restock request approved dan stock transfer `pending_approval` tanpa reserve/pick palsu.
- Opname/loss: stock opname counting dan inventory loss pending approval.
- Retail: shift kasir terbuka, POS sale demo, item, dan pembayaran tunai.
- B2B: customer B2B, alamat, credit limit, order, invoice, shipment, payment pending, receivable, dan complaint.
- Control/reporting: approval request, anomaly alert, audit log, report export, dan daily report.

## Catatan Integritas

- Seeder ini idempotent: aman dijalankan ulang karena memakai `updateOrCreate`, relasi sync, dan idempotency key untuk saldo pembuka.
- Perubahan saldo pembuka dibuat lewat `InventoryService`.
- Sample transfer sengaja dibuat `pending_approval`; stok belum di-reserve agar tidak ada saldo yang berubah tanpa menjalankan workflow/service transfer.
- Password demo tidak boleh dipakai di production.
