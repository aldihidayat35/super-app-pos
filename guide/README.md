# Guide Book GudangToko

Dokumen ini adalah buku panduan penggunaan aplikasi GudangToko untuk lima kelompok akun pokok. Akun turunan dimasukkan ke bagian akun pokok yang paling dekat dengan pekerjaan hariannya.

## Versi HTML lengkap

Versi paling detail dan siap dibuka di browser/cetak tersedia di:

- [guide-book-gudangtoko.html](guide-book-gudangtoko.html)

## Pembagian guide

1. [Owner](owner.md)  
   Untuk `owner_viewer` dan `owner_approver`. Fokus pada kontrol bisnis, dashboard, approval, audit, margin, laporan, piutang, dan keputusan strategis.

2. [Super Admin](super-admin.md)  
   Untuk `super_admin`, `admin_user`, dan `admin_config`. Fokus pada akun, role, permission, lokasi kerja, master organisasi, konfigurasi sistem, backup, health check, import awal, dan go-live.

3. [Gudang](gudang.md)  
   Untuk `kepala_gudang`, `staff_gudang`, `picker_packer`, dan `purchasing`. Fokus pada pembelian, penerimaan barang, stok, mutasi, transfer, opname, retur, loss, fulfillment B2B, pengiriman, dan HPP.

4. [Toko Internal](toko-internal.md)  
   Untuk `kepala_toko`, `kasir`, `supervisor_shift`, dan karyawan toko. Fokus pada POS, shift kasir, closing, restock, terima transfer, retur toko, piutang toko, dan kehadiran.

5. [Langganan/B2B](langganan-b2b.md)  
   Untuk `langganan_owner` dan `langganan_staff`. Fokus pada portal pelanggan: katalog, keranjang, checkout, order, invoice, pembayaran, pengiriman, bukti terima, reorder, profil usaha, dan komplain.

## Akun demo lokal

Seeder demo hanya untuk environment `local` atau `testing`. Jangan dipakai untuk production.

Password seluruh akun demo: `password`

| Kelompok | Nama akun | Email | Username | Role |
|---|---|---|---|---|
| Owner | Owner | `owner@gudangtoko.test` | `owner` | `owner_approver` |
| Super Admin | Super Admin | `superadmin@gudangtoko.test` | `superadmin` | `super_admin` |
| Gudang | Manajemen Gudang | `manajemen-gudang@gudangtoko.test` | `manajemen-gudang` | `kepala_gudang`, `purchasing` |
| Gudang | Staff Gudang | `staff-gudang@gudangtoko.test` | `staff-gudang` | `staff_gudang` |
| Toko Internal | Toko Internal | `toko@gudangtoko.test` | `toko-internal` | `kepala_toko` |
| Toko Internal | Kasir / Kepala Toko | `kasir@gudangtoko.test` | `kasir` | `kasir`, `kepala_toko` |
| Langganan/B2B | Langganan / B2B | `langganan-b2b@gudangtoko.test` | `langganan-b2b` | `langganan_owner` |
| Langganan/B2B | Akun Pelanggan | `pelanggan@gudangtoko.test` | `pelanggan` | `langganan_staff` |

## Cara membaca panduan

- Bagian "Tujuan role" menjelaskan tanggung jawab pengguna.
- Bagian "Menu utama" menjelaskan halaman yang relevan.
- Bagian "Cara menjalankan fitur" menjelaskan langkah operasional.
- Bagian "Cara kerja di belakang layar" menjelaskan apa yang dilakukan sistem: validasi, permission, stok, HPP, piutang, audit, approval, queue, dan notifikasi.
- Bagian "Hal yang tidak boleh dilakukan" menjelaskan batas aman agar data stok, uang, dan audit tetap bisa direkonsiliasi.

## Prinsip umum aplikasi

GudangToko memakai pola warehouse-first. Artinya, gudang menjadi sumber kebenaran untuk stok, mutasi, HPP, pembelian, penerimaan, dan fulfillment. Toko internal dan portal B2B menggunakan stok yang sudah tersedia atau sudah di-reserve melalui proses yang diaudit.

Prinsip penting:

- Stok tidak boleh negatif.
- Perubahan stok hanya boleh lewat service inventory dan selalu menghasilkan mutasi append-only.
- Transaksi final tidak boleh dihapus permanen; koreksi dilakukan melalui void, reversal, retur, credit note, atau dokumen koreksi.
- Harga jual, HPP, margin, pembayaran, piutang, dan closing disimpan sebagai snapshot agar laporan historis tidak berubah.
- Akses menu dan aksi dikontrol oleh role, permission, dan scope lokasi kerja.
- Approval diperlukan untuk aksi sensitif seperti void, koreksi stok besar, approval harga, approval PO, credit note, dan pengecualian kredit.

## URL penting

| Kebutuhan | URL |
|---|---|
| Login internal | `/login` |
| Login pelanggan B2B | `/langganan/login` |
| Dashboard internal | `/dashboard` |
| Dashboard owner | `/owner/dashboard` |
| Dashboard gudang | `/warehouse/dashboard` |
| Dashboard toko | `/retail/dashboard` |
| Dashboard langganan | `/langganan/dashboard` |
| Health check local/admin | `/system/health` atau `/admin/system/health` |

## Catatan untuk production

- Seeder demo tidak boleh dijalankan di production.
- Password demo wajib diganti jika database lokal dipakai untuk training.
- Pastikan user production dibuat oleh Super Admin dengan email/username resmi.
- Pastikan assignment lokasi kerja benar sebelum user gudang/toko mulai transaksi.
- Pastikan backup dan restore drill selesai sebelum go-live.
