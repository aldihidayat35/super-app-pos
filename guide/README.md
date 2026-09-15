# Guide Book GudangToko

Dokumen ini adalah buku panduan penggunaan aplikasi GudangToko untuk seluruh kelompok akun pokok. Akun turunan dimasukkan ke bagian akun pokok yang paling dekat dengan pekerjaan hariannya. Versi di dalam aplikasi tersedia melalui menu **Dokumentasi Panduan** dan otomatis disaring berdasarkan role user yang login.

## Versi HTML satu berkas

Snapshot yang dapat dibuka tanpa menjalankan aplikasi tersedia di:

- [guide-book-gudangtoko.html](guide-book-gudangtoko.html)

Versi di menu **Dokumentasi Panduan** adalah sumber yang paling mutakhir dan memuat diagram alur visual untuk fitur toko serta gudang.

## Pembagian guide

1. [Panduan Umum](umum.md)
   Untuk seluruh user. Fokus pada login, navigasi, role, permission, status dokumen, keamanan, troubleshooting, dan eskalasi.

2. [Owner](owner.md)
   Untuk `owner_viewer` dan `owner_approver`. Fokus pada kontrol bisnis, dashboard, approval, audit, margin, laporan, piutang, dan keputusan strategis.

3. [Super Admin](super-admin.md)
   Untuk `super_admin`, `admin_user`, dan `admin_config`. Fokus pada akun, role, permission, lokasi kerja, master organisasi, konfigurasi sistem, backup, health check, import awal, dan go-live.

4. [Gudang](gudang.md)
   Untuk `kepala_gudang`, `staff_gudang`, dan `picker_packer`. Fokus pada penerimaan barang, stok, mutasi, transfer, opname, retur, loss, fulfillment B2B, pengiriman, dan HPP.

5. [Purchasing & Supplier](purchasing-supplier.md)
   Untuk `purchasing`. Fokus pada master supplier, permintaan pembelian, PO, koordinasi receipt, HPP, selisih, dan evaluasi supplier.

6. [Toko Internal](toko-internal.md)
   Untuk `kepala_toko`, `staf_toko`, `kasir`, `supervisor_shift`, dan karyawan toko. Fokus pada etalase, POS, pembelian langsung toko, pengajuan produk baru, stok reguler dan darurat, shift, retur toko, piutang toko, serta kehadiran.

7. [Langganan/B2B](langganan-b2b.md)
   Untuk `langganan_owner` dan `langganan_staff`. Fokus pada portal pelanggan: katalog, keranjang, checkout, order, invoice, pembayaran, pengiriman, bukti terima, reorder, profil usaha, dan komplain.

8. [Checklist Kerja](checklist-kerja.md)
   Untuk seluruh role internal. Fokus pada checklist harian dan mingguan per akun/lokasi, koreksi, riwayat, rekap tim, ekspor CSV, dan versi template.

9. [Target dan Bonus Staf](target-bonus.md)
   Untuk staf gudang, picker/packer, staf toko, kasir, Sales, kepala lokasi, dan Owner. Fokus pada target bulanan, KPI, estimasi, persetujuan, pembayaran, dan rekap bonus.

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
- Diagram alur di bawah setiap fitur memperlihatkan urutan proses, titik keputusan, dampak stok, dan hasil akhir berdasarkan controller, service, policy, serta state machine aplikasi.
- Bagian "Hal yang tidak boleh dilakukan" menjelaskan batas aman agar data stok, uang, dan audit tetap bisa direkonsiliasi.

Diagram alur dirender oleh aplikasi tanpa layanan eksternal. Definisinya berada di `config/guide-flows.php` serta berkas `config/guide-flows-*` untuk setiap kelompok panduan; setiap definisi menyimpan daftar file backend yang menjadi dasar verifikasinya.

## Prinsip umum aplikasi

GudangToko mengelola persediaan berdasarkan lokasi kerja. Barang toko dapat berasal dari transfer gudang utama atau pembelian pemasok yang diterima langsung di toko. Keduanya tetap memakai dokumen, persetujuan, penerimaan, mutasi stok, dan perhitungan HPP yang dapat diaudit. Rincian proses toko tersedia pada [Pembelian Langsung dan Persediaan Toko](../docs/STORE-INBOUND-AND-EMERGENCY-STOCK.md).

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
| Checklist kerja internal | `/checklist-kerja` |
| Target dan bonus staf | `/target-bonus` |
| Health check local/admin | `/system/health` atau `/admin/system/health` |

## Catatan untuk production

- Seeder demo tidak boleh dijalankan di production.
- Password demo wajib diganti jika database lokal dipakai untuk training.
- Pastikan user production dibuat oleh Super Admin dengan email/username resmi.
- Pastikan assignment lokasi kerja benar sebelum user gudang/toko mulai transaksi.
- Pastikan backup dan restore drill selesai sebelum go-live.
