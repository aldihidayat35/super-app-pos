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
| super_admin | super_admin@gudangtoko.test | demo-super-admin | Global |
| owner_viewer | owner_viewer@gudangtoko.test | demo-owner-viewer | Global |
| owner_approver | owner_approver@gudangtoko.test | demo-owner-approver | Global |
| admin_user | admin_user@gudangtoko.test | demo-admin-user | Global |
| admin_config | admin_config@gudangtoko.test | demo-admin-config | Global |
| kepala_gudang | kepala_gudang@gudangtoko.test | demo-kepala-gudang | Gudang Demo Utama |
| staff_gudang | staff_gudang@gudangtoko.test | demo-staff-gudang | Gudang Demo Utama |
| picker_packer | picker_packer@gudangtoko.test | demo-picker-packer | Gudang Demo Utama |
| purchasing | purchasing@gudangtoko.test | demo-purchasing | Gudang Demo Utama |
| kepala_toko | kepala_toko@gudangtoko.test | demo-kepala-toko | Toko Demo Pusat |
| kasir | kasir@gudangtoko.test | demo-kasir | Toko Demo Pusat |
| supervisor_shift | supervisor_shift@gudangtoko.test | demo-supervisor-shift | Toko Demo Pusat |
| langganan_owner | langganan_owner@gudangtoko.test | demo-langganan-owner | Portal B2B |
| langganan_staff | langganan_staff@gudangtoko.test | demo-langganan-staff | Portal B2B |

## Data yang Dibuat

- Organisasi: `Gudang Demo Utama`, `Toko Demo Pusat`, work location, zona/rak/bin.
- Master produk: kategori, brand, unit, barcode, supplier, supplier product, dan tiga produk demo.
- Inventory: saldo pembuka gudang/toko melalui `InventoryService`, stock batches, dan stock mutations append-only.
- Pricing: price rule dan product price POS.
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
