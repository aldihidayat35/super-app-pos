# Seeder Akun Administrasi GudangToko

Seeder ini dibuat untuk environment `local` dan `testing` saja. Seeder hanya
membuat akun administrasi, role, dan permission; tidak ada data bisnis simulasi.

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

| Role | Email | Username | Lingkup awal |
| --- | --- | --- | --- |
| super_admin | superadmin@gudangtoko.test | superadmin | Global |
| owner_approver | owner@gudangtoko.test | owner | Global |
| kepala_gudang + purchasing | manajemen-gudang@gudangtoko.test | manajemen-gudang | Belum ditugaskan |
| staff_gudang | staff-gudang@gudangtoko.test | staff-gudang | Belum ditugaskan |
| kepala_toko | toko@gudangtoko.test | toko-internal | Belum ditugaskan |
| staf_toko | staf-toko@gudangtoko.test | staf-toko | Belum ditugaskan |
| kasir + kepala_toko | kasir@gudangtoko.test | kasir | Belum ditugaskan |
| langganan_owner | langganan-b2b@gudangtoko.test | langganan-b2b | Portal B2B |
| langganan_staff | pelanggan@gudangtoko.test | pelanggan | Portal B2B |

## Data yang Dibuat

- Semua role dan permission dari `config/rbac.php`.
- Sembilan akun login sesuai tabel di atas, dengan role yang sama seperti
  seeder sebelumnya.

Produk, kategori, unit, gudang, cabang, lokasi kerja, stok, supplier,
pelanggan, order, transaksi POS, B2B, laporan, approval, dan audit tetap kosong.

## Catatan Integritas

- Seeder ini idempotent: aman dijalankan ulang untuk memperbarui akun dan role.
- Akun operasional belum memiliki lokasi kerja. Buat gudang/cabang asli terlebih
  dahulu, lalu tetapkan lokasi kerja melalui menu administrasi.
- Password `password` hanya untuk local/testing dan tidak boleh dipakai di production.
