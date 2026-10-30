This is a single self-contained fix; keep it small and focused. Rombak dan rampingkan tampilan UI statistik pada Dashboard Retail (resources/views/dashboards/partials/retail-content.blade.php) agar lebih fresh, rapi, dan terpadu melalui pengelompokan tematik (referensi ala dashboard Accurate), menggantikan deretan 11 kartu statistik terpisah yang memakan ruang.

Integrity mode: development

## Requirements

### R1. Pengelompokan dan Perampingan UI Statistik Dashboard Toko
Menata ulang 11 metrik statistik pada Dashboard Retail (`resources/views/dashboards/partials/retail-content.blade.php`) ke dalam kelompok-kelompok tematik yang fresh, ringkas, dan terpadu (mengacu pada referensi dashboard Accurate), menghindari bentuk 11 card terpisah yang memakan ruang. Seluruh metrik (omzet, transaksi, rata-rata nota, stok tersedia, stok kritis/kosong, shift aktif, closing pending, selisih kas, piutang terbit, transaksi batal, nilai retur, serta margin jika diizinkan) tetap dipertahankan dan disajikan dalam tata letak yang padat, proporsional, dan informatif.

### R2. Keselarasan Metronic 8, Responsivitas, dan Dukungan AJAX Cabang
Menggunakan styling dan komponen yang selaras dengan Metronic 8 / Bootstrap 5 yang ada pada proyek. Memastikan tata letak tetap rapi dan responsif pada perangkat mobile, tablet, dan desktop tanpa layout overflow. Mempertahankan kompatibilitas dengan pembaruan data AJAX saat pengguna mengganti filter cabang aktif atau rentang tanggal, serta menjaga pemformatan angka/mata uang sesuai helper yang berlaku.

## Acceptance Criteria

### UI & Tata Letak Statistik
- [ ] Statistik pada `resources/views/dashboards/partials/retail-content.blade.php` tidak lagi berjejer sebagai 11 card besar terpisah, melainkan dikelompokkan secara tematik ke dalam struktur yang lebih ramping, fresh, dan terpadu (referensi gaya Accurate).
- [ ] Seluruh 11 metrik data operasional dan finansial toko (omzet, transaksi, rata-rata nota, stok tersedia, stok kritis/kosong, shift aktif, closing pending, selisih kas, piutang terbit, transaksi batal, nilai retur, serta margin bagi akun yang memiliki hak akses) tetap ditampilkan dan dapat dibaca dengan jelas.
- [ ] Tampilan halaman responsif di resolusi layar mobile (375px+), tablet (768px+), dan desktop (1200px+) tanpa overflow horizontal atau komponen rusak.

### Fungsionalitas & Reaktivitas
- [ ] Filter pemilihan cabang dan tombol refresh/terapkan AJAX tetap memperbarui nilai-nilai statistik secara dinamis tanpa merusak susunan DOM atau memicu error JavaScript di konsol browser.
- [ ] View Blade `resources/views/dashboards/retail.blade.php` dan parsialnya berhasil dirender tanpa error sintaks maupun runtime PHP.
- [ ] Kode lulus pengecekan linting proyek (`composer lint`).
