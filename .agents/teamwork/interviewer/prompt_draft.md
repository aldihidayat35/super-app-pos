Implementasi pembaruan Pusat Laporan GudangToko berbasis peran dan lokasi kerja, menggantikan pusat laporan generik menjadi lima profil laporan tersendiri (Pengguna, Gudang, Toko, Owner, dan Super Admin) dengan navigasi dinamis, kontrol akses lokasi, dan proteksi margin untuk [OPEN: Tujuan implementasi (produksi/eksplorasi/showcase/evaluasi)].

Integrity mode: [OPEN: Integrity mode berdasarkan pembatasan tim]

## Requirements

### R1. Navigasi Dinamis, Konfigurasi Katalog, dan RBAC Laporan
- Hapus grup sidebar generik **Laporan** dari `config/navigation.php`.
- Tambahkan katalog profil pada `config/reports.php`, berisi role, permission, tipe lokasi, label, route, ikon, dan daftar sublaporan untuk lima profil:
  - Laporan Pengguna (`admin_user`)
  - Laporan Gudang (`kepala_gudang`, label memuat nama lokasi)
  - Laporan Toko (`kepala_toko`, label memuat nama lokasi)
  - Laporan Owner (`owner_viewer`, `owner_approver`)
  - Laporan Super Admin (`super_admin`)
- Buat resolver laporan yang mensyaratkan role dan permission profil, membatasi lokasi memakai `permittedWorkLocationIds()`, memilih lokasi dari query valid / session / default / lokasi pertama yang diizinkan, menampilkan nama lokasi pada label kepala gudang/toko, menampilkan semua profil yang sah untuk akun multi-role, dan menampilkan hanya grup Super Admin untuk `super_admin` dengan gateway/pintasan ke laporan bisnis.
- Tambahkan permission: `reports.users.view|export`, `reports.warehouse.view|export`, `reports.retail.view|export`, `reports.owner.view|export`, `reports.system.view|export`. Sinkronkan permission secara idempotent melalui `RolePermissionSeeder`.
- Cabut akses pusat laporan dari role non-profil (`purchasing`, `supervisor_shift`, staf, kasir, `admin_config`, `kepala_keuangan`).
- Jaga permission `margins.view_sensitive` agar tetap wajib untuk data HPP dan margin.
- Arahkan route lama ke laporan baru yang ekuivalen jika pengguna berhak; selain itu kembalikan response 403.

### R2. Katalog Halaman Laporan, Data Queries, dan FormRequests
Implementasikan controller action/service query, FormRequest, dan file Blade tersendiri untuk setiap profil:
- **Laporan Pengguna**: Ringkasan pengguna, aktivitas & login, distribusi role & permission, penempatan & masa berlaku akses lokasi, audit perubahan akun. Eksklusikan data credential/rahasia (password, token, credential).
- **Laporan Gudang**: Ringkasan operasional gudang, persediaan, lapisan HPP, nilai persediaan (dihitung dari sisa `stock_batches × cost_price`), critical stock & dead stock, mutasi & kartu stok, penerimaan supplier & pembelian, transfer & permintaan restock, opname, adjustment, loss & barang rusak, kehadiran & produktivitas tim gudang.
- **Laporan Toko**: Ringkasan toko, penjualan, omzet, produk terlaris, tren jam transaksi, profitabilitas & margin aktual dari alokasi lapisan HPP, shift kasir, metode pembayaran & selisih kas, stok toko & kebutuhan restock, retur, pembelian darurat, loss & stockout, piutang retail, kehadiran & produktivitas tim toko. Void, transaksi batal, dan reversal tidak dihitung sebagai omzet aktif.
- **Laporan Owner**: Ringkasan eksekutif, penghasilan per periode & channel, profitabilitas & margin, nilai & kesehatan persediaan, piutang & aging, pembelian & performa supplier, perbandingan toko/gudang, tenaga kerja, target & bonus, laporan keuangan tahunan & pajak. Dukungan pemilihan multi-lokasi / perbandingan lokasi.
- **Laporan Super Admin**: Ringkasan kesehatan sistem & data, audit trail, keamanan, login, role, permission, akses lokasi, anomali & approval tertunda, status notifikasi & integrasi, riwayat ekspor laporan, serta gateway menuju laporan Owner, Gudang, Toko, dan Pengguna tanpa impersonasi (memakai controller/policy profil tujuan dengan scope eksplisit Super Admin).

### R3. Tampilan Blade Metronic 8, Route Kanonis, dan Subsistem Ekspor
- Gunakan struktur route kanonis: `/reports/users/*`, `/reports/warehouse/*`, `/reports/retail/*`, `/reports/owner/*`, `/reports/system/*`.
- Pisahkan view Blade ke folder `reports/users`, `reports/warehouse`, `reports/retail`, `reports/owner`, dan `reports/system`. Seluruh halaman wajib memakai `@extends('layouts.metronic.app')`.
- Terapkan identitas visual spesifik per profil:
  - Pengguna: ungu, direktori & timeline aktivitas
  - Gudang: biru-oranye, kesehatan stok & arus barang
  - Toko: hijau, penjualan, kas, performa transaksi
  - Owner: navy-emas, ringkasan finansial & perbandingan
  - Super Admin: indigo/netral, console audit & kesehatan sistem
- Komposisi halaman tidak boleh kembali menjadi generic blade tunggal; komponen bersama dibatasi pada filter, KPI card, empty state, tabel, dan formatter.
- Sediakan fitur ekspor (CSV/XLSX/PDF) sesuai dataset dan filter aktif dengan verifikasi permission dan integritas scope data. Cache laporan wajib memperhitungkan profil, user, lokasi, periode, channel, dan filter pada cache key.

## Acceptance Criteria

### Akses & Navigasi
- [ ] Sidebar hanya menampilkan pusat laporan baru untuk 5 role profil yang sah (`admin_user`, `kepala_gudang`, `kepala_toko`, `owner_viewer`/`owner_approver`, `super_admin`); role lain (`purchasing`, `kasir`, staf, dll.) tidak melihat menu pusat laporan dan mendapatkan HTTP 403 jika mengakses route laporan.
- [ ] Label sidebar untuk Kepala Gudang dan Kepala Toko memuat nama lokasi kerja aktif secara dinamis (`Laporan Gudang {nama lokasi}` / `Laporan Toko {nama lokasi}`).
- [ ] Pengguna tidak dapat mengakses data lokasi di luar `permittedWorkLocationIds()` melalui manipulasi parameter query.
- [ ] Super Admin memiliki gateway langsung ke seluruh profil laporan tanpa impersonasi.
- [ ] Route laporan lama mengarahkan pengguna secara aman ke laporan ekuivalen baru atau mengembalikan HTTP 403 jika tidak berwenang.

### Data & Integritas Bisnis
- [ ] Nilai persediaan dihitung akurat berdasarkan sisa `stock_batches × cost_price` (bukan HPP global produk tunggal).
- [ ] Transaksi void, batal, dan reversal tidak dihitung sebagai omzet aktif toko.
- [ ] Data HPP dan margin tidak bocor ke dalam respon HTML, JSON, maupun file ekspor untuk pengguna tanpa permission `margins.view_sensitive`.
- [ ] Data sensitif kredensial pengguna (password hash, token, session id) tidak terpapar pada query maupun ekspor Laporan Pengguna.

### UI & Ekspor
- [ ] Seluruh file view Blade laporan menggunakan `@extends('layouts.metronic.app')`, tidak render kosong, dan menerapkan tema warna visual yang sesuai profilnya.
- [ ] Fitur ekspor menghasilkan data yang konsisten dengan filter dan parameter aktif halaman.
- [ ] Tampilan halaman responsif dan rapi pada desktop, tablet, dan mobile.

### Verifikasi & Kualitas Kode
- [ ] [OPEN: Kriteria verifikasi pengujian otomatis dan quality gate]
