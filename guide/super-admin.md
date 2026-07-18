# Guide Book Super Admin

Panduan ini untuk `super_admin`, `admin_user`, dan `admin_config`. Super Admin menjaga pondasi aplikasi: user, role, permission, master organisasi, konfigurasi, nomor dokumen, health check, backup, log, import data awal, dan kesiapan go-live.

## 1. Tujuan role Super Admin

Super Admin bertanggung jawab memastikan aplikasi aman, rapi, dan siap dipakai oleh semua tim.

Role terkait:

| Role | Fokus |
|---|---|
| `super_admin` | Akses penuh seluruh sistem. Gunakan dengan hati-hati. |
| `admin_user` | Mengelola user dan lokasi kerja. |
| `admin_config` | Mengelola master organisasi, produk, supplier, pelanggan, harga, notifikasi, dan konfigurasi sistem. |

## 2. Menu utama Super Admin

| Menu | URL | Fungsi |
|---|---|---|
| Pengguna | `/admin/users` | Daftar, tambah, edit, detail, reset password, nonaktifkan user. |
| Role | `/admin/roles` | Membuat dan mengubah role serta permission. |
| Permission | `/admin/permissions` | Melihat daftar permission sistem. |
| Lokasi kerja user | `/admin/users/{id}/locations` | Menentukan gudang/cabang default dan akses user. |
| Gudang | `/admin/warehouses` | Master gudang. |
| Cabang/Toko | `/admin/branches` | Master cabang/toko. |
| Pengaturan Umum | `/admin/settings/general` | Nama perusahaan, timezone, locale, currency, batas upload, margin default. |
| Nomor Dokumen | `/admin/settings/document-numbers` | Sequence dokumen seperti PO, receipt, transfer, invoice. |
| Kesehatan Sistem | `/admin/system/health` atau `/system/health` | Cek database, storage, queue, scheduler, versi aplikasi. |
| Backup | `/admin/system/backups` | Menjalankan dan mengunduh backup. |
| Log & Queue | `/admin/system/logs` | Melihat log aplikasi, failed jobs, retry job. |
| Import Data Awal | `/admin/system/imports` | Preview dan import data awal terkontrol. |
| Maintenance & Go-Live | `/admin/system/maintenance` | Checklist operasi, cache, maintenance, dan go-live. |
| Channel Notifikasi | `/admin/notifications/channels` | Konfigurasi WA/Telegram/email. |
| Template Pesan | `/admin/notifications/templates` | Template pesan notifikasi. |
| Jadwal Notifikasi | `/admin/notifications/schedules` | Jadwal laporan dan notifikasi. |
| Penerima Notifikasi | `/admin/notifications/recipients` | Daftar penerima. |
| Log Pengiriman | `/admin/notifications/logs` | Status pengiriman notifikasi. |
| Aturan Alert | `/admin/notifications/alerts` | Aturan alert operasional. |

## 3. Mengelola user

### 3.1 Tambah user baru

1. Buka `/admin/users`.
2. Klik `Tambah Pengguna`.
3. Isi:
   - nama,
   - username,
   - email,
   - nomor WhatsApp,
   - avatar bila perlu,
   - status aktif,
   - role.
4. Simpan.
5. Buka detail user untuk memastikan role benar.
6. Buka lokasi kerja user dan assign gudang/cabang yang sesuai.

Cara kerja di belakang layar:

- Sistem menyimpan identitas user di tabel `users`.
- Role memakai package Spatie Permission.
- User aktif saja yang boleh login; user nonaktif akan ditolak middleware `active.user`.
- Lokasi kerja disimpan sebagai assignment agar user gudang/toko hanya melihat data lokasinya.
- Aktivitas penting dicatat ke audit log/activity log.

### 3.2 Edit user

1. Buka `/admin/users/{id}/edit`.
2. Ubah data yang perlu.
3. Hindari mengganti email/username tanpa alasan karena dipakai untuk login.
4. Simpan.

Jika user pindah cabang/gudang:

1. Buka `/admin/users/{id}/locations`.
2. Pilih lokasi kerja baru.
3. Tentukan lokasi default.
4. Nonaktifkan lokasi lama bila user tidak boleh mengakses data lama.

### 3.3 Nonaktifkan user

1. Buka detail user.
2. Klik nonaktifkan jika tersedia.
3. Pastikan user tersebut tidak sedang memegang shift/transaksi aktif.

Cara kerja di belakang layar:

- User nonaktif tidak dihapus permanen.
- Riwayat transaksi tetap memakai user lama sebagai actor.
- Sistem menjaga audit sehingga data lama tidak kehilangan pemilik tindakan.

### 3.4 Reset password user

1. Buka detail user.
2. Klik kirim reset password.
3. User menerima link reset sesuai konfigurasi mail.

Catatan:

- Jangan menaruh password production di repository.
- Untuk training lokal, akun demo menggunakan password `password`.

## 4. Mengelola role dan permission

### 4.1 Membuat role

1. Buka `/admin/roles/create`.
2. Isi nama role dan deskripsi manusiawi.
3. Pilih permission sesuai tugas role.
4. Simpan.

Cara kerja di belakang layar:

- Role menyimpan kumpulan permission.
- Permission mengontrol akses route, policy, dan aksi server-side.
- Menyembunyikan tombol saja tidak cukup; server tetap memeriksa permission.

### 4.2 Mengubah role

1. Buka `/admin/roles/{id}`.
2. Periksa user yang memakai role tersebut.
3. Klik edit atau ubah matriks permission.
4. Simpan.
5. Minta user logout-login jika perubahan belum terasa.

### 4.3 Menghapus role

Tombol delete role hanya boleh untuk Super Admin.

Sebelum hapus:

- Pastikan role bukan role sistem yang masih dibutuhkan.
- Pastikan tidak ada user aktif memakai role tersebut.
- Pastikan tidak menghapus role yang menjadi basis operasional seperti `super_admin`, `owner_approver`, `kepala_gudang`, `kasir`, atau `langganan_owner`.

Cara kerja di belakang layar:

- Penghapusan role memengaruhi akses user.
- Jika role masih dipakai, sebaiknya sistem menolak atau Super Admin harus memindahkan user lebih dulu.
- Semua perubahan role harus diaudit.

## 5. Mengelola gudang dan cabang

### 5.1 Master gudang

1. Buka `/admin/warehouses`.
2. Tambah gudang.
3. Isi kode, nama, alamat, kota, telepon, kapasitas, area layanan, status aktif.
4. Simpan.

Cara kerja di belakang layar:

- Gudang akan disinkronkan menjadi `work_location`.
- Lokasi kerja ini dipakai untuk scope user, stok, transfer, receipt, dan laporan.
- Kode gudang sebaiknya tidak berubah jika sudah ada transaksi.

### 5.2 Master cabang/toko

1. Buka `/admin/branches`.
2. Tambah cabang.
3. Pilih gudang pemasok default.
4. Isi kode, nama toko, alamat, telepon, target penjualan, konfigurasi harga dan closing.
5. Simpan.

Cara kerja di belakang layar:

- Cabang juga disinkronkan menjadi `work_location`.
- Stok cabang terpisah dari stok gudang.
- Transfer gudang ke cabang akan memindahkan saldo melalui mutasi stok.
- Tab operasional cabang menampilkan user, stok, shift, performa, dan histori.

## 6. Mengelola master produk, supplier, dan pelanggan

### 6.1 Produk

Menu terkait:

- Kategori Produk: `/admin/product-categories`
- Merek Produk: `/admin/product-brands`
- Satuan: `/admin/units`
- Produk: `/admin/products`
- Barcode/QR: `/admin/products/barcodes`
- Import Produk: `/admin/products/import`

Urutan setup disarankan:

1. Buat satuan dasar.
2. Buat kategori.
3. Buat brand.
4. Buat produk dengan SKU, base unit, minimum stock, safety stock, HPP awal, dan minimum price.
5. Tambahkan satuan konversi jika produk dijual dalam pack/dus.
6. Tambahkan barcode.

Cara kerja di belakang layar:

- Semua transaksi stok memakai unit dasar.
- Unit input disimpan sebagai snapshot pada dokumen.
- Barcode membantu POS, picking, packing, dan label produk.

### 6.2 Supplier

1. Buka `/admin/suppliers`.
2. Tambah supplier.
3. Isi kontak, WhatsApp, email, alamat, kota, termin pembayaran, dan status aktif.
4. Simpan.

Supplier dipakai oleh PO, penerimaan barang, histori harga supplier, dan performa supplier.

### 6.3 Pelanggan dan B2B

1. Buka `/admin/customers`.
2. Tambah pelanggan.
3. Pilih tipe pelanggan, misalnya B2B.
4. Isi profil usaha, owner, PIC, kontak, alamat, kategori harga, minimum order, termin, limit kredit.
5. Buka `Akses B2B` untuk menghubungkan user pelanggan.
6. Buka `Pengaturan pelanggan` untuk status verifikasi, dokumen, harga khusus, limit kredit, dan termin.

Cara kerja di belakang layar:

- Customer B2B menjadi tenant bisnis kecil untuk portal langganan.
- User B2B hanya boleh melihat customer yang terhubung dengannya.
- Limit kredit memengaruhi checkout dan approval order.

## 7. Konfigurasi sistem

### 7.1 Pengaturan umum

Periksa:

- nama perusahaan,
- alamat,
- telepon,
- timezone `Asia/Jakarta`,
- locale `id`,
- currency `IDR`,
- upload limit,
- default margin,
- overpricing tolerance,
- template invoice/receipt.

### 7.2 Nomor dokumen

Nomor dokumen dipakai untuk PO, receipt, transfer, POS, invoice, payment, retur, opname, dan dokumen lain.

Aturan aman:

- Jangan reset sequence tanpa alasan.
- Jangan memakai nomor manual yang sudah pernah dipakai.
- Setelah dokumen void, nomor tidak boleh dipakai ulang.

## 8. Health check, backup, log, queue, dan go-live

### 8.1 Health check

1. Buka `/admin/system/health`.
2. Pastikan database OK.
3. Pastikan storage link OK.
4. Pastikan queue connection sesuai environment.
5. Pastikan scheduler heartbeat sehat.
6. Pastikan versi PHP/Laravel sesuai standar.

### 8.2 Backup

1. Buka `/admin/system/backups`.
2. Jalankan backup manual sebelum perubahan besar.
3. Unduh backup jika perlu.
4. Simpan di lokasi aman.

### 8.3 Log dan queue

1. Buka `/admin/system/logs`.
2. Periksa error aplikasi.
3. Periksa failed jobs.
4. Retry job hanya jika penyebab error sudah jelas.

### 8.4 Import data awal

1. Buka `/admin/system/imports`.
2. Unduh template sesuai tipe data.
3. Isi data di template.
4. Upload untuk preview.
5. Periksa error baris.
6. Commit hanya jika preview valid.

Jangan import langsung ke database tanpa validasi aplikasi.

## 9. Notifikasi

### 9.1 Channel

Channel dapat berupa WA API, Telegram, email, atau channel lain sesuai konfigurasi.

Langkah:

1. Buka `/admin/notifications/channels`.
2. Tambahkan channel.
3. Isi credential dengan hati-hati.
4. Test channel.

### 9.2 Template

1. Buka `/admin/notifications/templates`.
2. Buat template pesan.
3. Gunakan variabel yang tersedia.
4. Preview sebelum aktif.

### 9.3 Jadwal dan penerima

1. Buka `/admin/notifications/recipients`.
2. Tambahkan penerima.
3. Buka `/admin/notifications/schedules`.
4. Buat jadwal laporan/notifikasi.
5. Jalankan test bila perlu.

Cara kerja di belakang layar:

- Pengiriman berjalan melalui queue bila tersedia.
- Log pengiriman tersimpan di `/admin/notifications/logs`.
- Secret harus disimpan aman dan tidak boleh dicetak penuh di log.

## 10. Hal yang tidak boleh dilakukan Super Admin

- Jangan memakai akun `super_admin` untuk transaksi harian POS/gudang.
- Jangan hapus role/user transaksi tanpa memahami dampak audit.
- Jangan edit database langsung untuk memperbaiki stok/uang.
- Jangan menjalankan `migrate:fresh` di database yang berisi data nyata.
- Jangan menjalankan seeder demo di production.
- Jangan membagikan password akun Super Admin.
- Jangan menonaktifkan queue/scheduler jika notifikasi, export, atau laporan harian bergantung padanya.

## 11. Checklist setup awal

- [ ] `.env` production sudah benar.
- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] Database production terhubung.
- [ ] Storage link aktif.
- [ ] Queue worker berjalan.
- [ ] Scheduler berjalan.
- [ ] Backup berhasil dan restore drill diuji.
- [ ] Super Admin production dibuat.
- [ ] Role dan permission dicek.
- [ ] Gudang dan cabang dibuat.
- [ ] User ditugaskan ke lokasi kerja.
- [ ] Produk, supplier, pelanggan siap.
- [ ] Nomor dokumen dicek.
- [ ] Health check hijau.
