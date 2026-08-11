# Panduan Umum Pengguna GudangToko

Panduan ini berlaku untuk seluruh pengguna internal dan pelanggan Langganan/B2B. Gunakan panduan ini sebelum membaca panduan role agar memahami cara login, navigasi, status dokumen, keamanan, dan cara menangani masalah.

## 1. Tujuan panduan

Setelah membaca panduan umum, pengguna diharapkan dapat:

- masuk melalui portal yang benar;
- memahami menu yang muncul berdasarkan role dan lokasi kerja;
- mengisi form, mencari data, memakai filter, dan membaca status;
- menjalankan transaksi secara berurutan;
- membedakan draft, posted, approved, void, reverse, dan cancelled;
- menjaga keamanan akun serta jejak audit;
- mengumpulkan informasi yang dibutuhkan ketika meminta bantuan.

## 2. Memilih portal login

| Jenis pengguna | Alamat login | Contoh role |
|---|---|---|
| Pengguna internal | `/login` | Super Admin, Owner, Gudang, Purchasing, Kepala Toko, Kasir |
| Pelanggan langganan | `/langganan/login` | Langganan Owner, Langganan Staff |

### 2.1 Login pengguna internal

1. Buka `/login`.
2. Isi kolom **Login** menggunakan email atau username.
3. Isi password.
4. Klik **Masuk**.
5. Pastikan nama, role, dan lokasi kerja pada sidebar sudah benar.
6. Bila dashboard tidak sesuai tugas, jangan melanjutkan transaksi; hubungi administrator.

### 2.2 Login pelanggan Langganan/B2B

1. Buka `/langganan/login`.
2. Masukkan email/username akun perusahaan pelanggan.
3. Masukkan password.
4. Klik **Masuk**.
5. Pastikan nama usaha pada dashboard benar.
6. Jangan memakai akun perusahaan lain walaupun mempunyai link halaman.

### 2.3 Jika lupa password

1. Klik **Lupa Password** pada halaman login.
2. Masukkan email yang terdaftar.
3. Buka email reset password.
4. Gunakan link sebelum kedaluwarsa.
5. Buat password baru yang tidak dipakai pada layanan lain.

Jika email tidak diterima:

1. Periksa folder spam.
2. Pastikan alamat email benar.
3. Tunggu beberapa menit dan jangan menekan kirim berulang kali.
4. Hubungi administrator untuk memeriksa konfigurasi email atau status akun.

## 3. Memahami role, permission, dan lokasi kerja

Sistem membatasi akses dengan tiga lapisan:

| Lapisan | Fungsi |
|---|---|
| Role | Kelompok tanggung jawab pengguna, misalnya Kasir atau Kepala Gudang. |
| Permission | Izin aksi tertentu, misalnya melihat, membuat, menyetujui, atau export. |
| Lokasi kerja | Membatasi data ke gudang/cabang yang ditugaskan kepada pengguna. |

Aturan penting:

- Menu yang tidak relevan otomatis disembunyikan.
- Mengetik URL langsung tidak melewati pemeriksaan permission.
- Dua user dengan role sama dapat melihat data berbeda karena lokasi kerjanya berbeda.
- User dengan beberapa role memperoleh gabungan akses yang sah.
- Panduan yang terlihat mengikuti role, tetapi panduan tidak menambah permission.

Jika menu yang diperlukan tidak muncul:

1. Catat nama menu dan pekerjaan yang sedang dilakukan.
2. Periksa role pada sidebar.
3. Periksa lokasi kerja yang tampil.
4. Logout lalu login kembali setelah administrator mengubah role.
5. Jangan meminjam akun rekan kerja.

## 4. Navigasi dasar aplikasi

### 4.1 Sidebar

1. Klik kelompok menu untuk membuka submenu.
2. Pilih halaman yang dibutuhkan.
3. Menu aktif ditandai dengan warna aktif.
4. Pada layar kecil, gunakan tombol menu di header.
5. Gunakan **Dokumentasi Panduan** untuk kembali ke panduan role.

### 4.2 Header dan profil

Gunakan area profil untuk:

- memeriksa identitas akun;
- membuka profil;
- mengganti password;
- logout setelah pekerjaan selesai.

### 4.3 Tombol Panduan pada halaman

Beberapa halaman mempunyai tombol tanda tanya. Buka tombol tersebut sebelum transaksi untuk membaca:

- fungsi halaman;
- urutan proses;
- arti kolom;
- dampak transaksi;
- peringatan;
- contoh penggunaan.

## 5. Pola umum penggunaan halaman

### 5.1 Halaman daftar

1. Gunakan filter tanggal, status, lokasi, atau kata kunci.
2. Klik **Filter/Terapkan**.
3. Periksa jumlah hasil dan halaman pagination.
4. Buka detail sebelum menjalankan aksi sensitif.
5. Export hanya setelah filter benar.

### 5.2 Form tambah atau edit

1. Isi field bertanda wajib.
2. Pilih referensi dari dropdown; jangan menebak ID.
3. Periksa satuan, jumlah, nominal, tanggal, dan lokasi.
4. Isi alasan/catatan secara spesifik.
5. Klik simpan satu kali dan tunggu respons.
6. Jika validasi gagal, perbaiki field yang ditandai tanpa menghapus data lain.

### 5.3 Halaman detail

Sebelum menekan aksi, periksa:

- nomor dokumen;
- lokasi dan pihak terkait;
- status saat ini;
- item, qty, satuan, harga, dan total;
- pembuat dan waktu transaksi;
- catatan, bukti, serta histori status;
- tombol yang tersedia untuk role Anda.

## 6. Memahami status dan alur dokumen

Status yang umum digunakan:

| Status | Arti dan tindakan |
|---|---|
| Draft | Belum final; masih dapat diperbaiki sesuai permission. |
| Submitted | Sudah diajukan; menunggu pemeriksaan atau approval. |
| Pending Approval | Menunggu keputusan user berwenang. |
| Approved | Disetujui dan siap melanjutkan proses berikutnya. |
| Rejected | Ditolak; baca alasan lalu koreksi melalui alur yang disediakan. |
| Posted/Completed | Sudah berdampak pada stok/uang dan menjadi catatan final. |
| Cancelled | Dibatalkan sebelum final sesuai aturan. |
| Void | Transaksi final dinetralkan melalui jejak koreksi. |
| Reversed | Efek transaksi dibalik dengan catatan baru; histori lama tetap ada. |

Prinsip urutan kerja:

1. Buat atau pilih dokumen sumber.
2. Isi data dan simpan sebagai draft bila tersedia.
3. Periksa seluruh item dan bukti.
4. Submit untuk pemeriksaan.
5. User berbeda melakukan approval bila diwajibkan.
6. Posting/complete hanya setelah kondisi fisik sesuai.
7. Periksa hasil pada saldo, laporan, atau ledger.

## 7. Prinsip stok, uang, dan audit

### 7.1 Stok

- `On Hand` adalah stok fisik tercatat.
- `Reserved` adalah stok yang sudah dialokasikan.
- `Damaged` adalah stok rusak/terblokir.
- `Available` adalah stok yang boleh dipakai transaksi baru.

Jangan menjanjikan barang hanya berdasarkan On Hand. Gunakan Available.

### 7.2 HPP dan harga

- HPP adalah biaya modal, bukan harga jual.
- Harga jual dapat berbeda berdasarkan channel, pelanggan, atau aturan harga.
- Transaksi menyimpan snapshot HPP/harga agar laporan historis tidak berubah.
- Perubahan HPP sensitif hanya terlihat oleh role yang mempunyai izin.

### 7.3 Pembayaran dan piutang

- Pastikan nomor invoice dan pelanggan benar.
- Jangan mencatat pembayaran dua kali.
- Pembayaran final tidak dihapus; koreksi memakai reversal.
- Periksa saldo sebelum dan sesudah alokasi.

### 7.4 Audit

Sistem mencatat actor, waktu, dokumen, nilai sebelum/sesudah, dan alasan untuk aksi penting. Karena itu:

- setiap user harus memakai akun sendiri;
- alasan harus menjelaskan kejadian nyata;
- transaksi final tidak boleh dimanipulasi langsung di database;
- bukti harus relevan dan dapat dibaca.

## 8. Approval yang aman

Untuk pemohon:

1. Pastikan data dokumen sudah lengkap.
2. Isi alasan bisnis, bukan hanya “mohon approve”.
3. Lampirkan bukti bila diperlukan.
4. Submit satu kali.
5. Pantau status dan baca catatan approver.

Untuk approver:

1. Buka dokumen sumber.
2. Bandingkan nilai sebelum dan sesudah.
3. Periksa dampak stok, uang, harga, atau piutang.
4. Pastikan pemohon dan lokasi benar.
5. Approve atau reject dengan alasan jelas.
6. Periksa efek proses setelah keputusan.

## 9. Keamanan akun

- Jangan membagikan password atau kode reset.
- Logout dari perangkat bersama.
- Jangan menyimpan password pada catatan yang terlihat orang lain.
- Pastikan alamat aplikasi benar sebelum login.
- Laporkan login asing atau perubahan data yang tidak dikenal.
- Jangan mengunggah bukti yang mengandung password, token, atau data rahasia yang tidak diperlukan.

## 10. Troubleshooting langkah demi langkah

### 10.1 Halaman tidak dapat dibuka

1. Catat URL dan waktu kejadian.
2. Periksa koneksi jaringan.
3. Refresh satu kali.
4. Pastikan akun belum logout.
5. Periksa apakah menu memang tersedia untuk role.
6. Kirim screenshot dan pesan error kepada administrator.

### 10.2 Tombol aksi tidak muncul

1. Periksa status dokumen; aksi hanya muncul pada status tertentu.
2. Periksa role dan permission.
3. Periksa lokasi kerja.
4. Pastikan Anda bukan pembuat yang dilarang menyetujui sendiri.
5. Hubungi atasan/administrator bila akses memang dibutuhkan.

### 10.3 Validasi form gagal

1. Baca pesan di bawah field.
2. Periksa format tanggal, email, angka, dan file.
3. Pastikan qty dan nominal tidak negatif.
4. Pastikan referensi masih aktif.
5. Simpan ulang setelah seluruh error diperbaiki.

### 10.4 Data tidak terlihat setelah simpan

1. Pastikan muncul notifikasi berhasil.
2. Hapus filter yang terlalu sempit.
3. Periksa lokasi kerja dan rentang tanggal.
4. Cari berdasarkan nomor dokumen.
5. Periksa status atau audit log melalui user berwenang.

### 10.5 Stok atau total berbeda

1. Jangan membuat transaksi penyeimbang tanpa investigasi.
2. Buka kartu stok/mutasi dokumen.
3. Periksa reserved dan damaged.
4. Periksa satuan dan conversion factor.
5. Cocokkan dokumen sumber dan waktu posting.
6. Eskalasikan ke Kepala Gudang/Owner bila perlu opname atau reversal.

## 11. Informasi minimum saat meminta bantuan

Sertakan:

1. Nama user dan role.
2. Lokasi kerja.
3. Nama menu dan URL.
4. Nomor dokumen/SKU/invoice terkait.
5. Langkah sebelum error.
6. Pesan error lengkap.
7. Waktu kejadian.
8. Screenshot tanpa data rahasia.
9. Hasil yang diharapkan.

## 12. Checklist sebelum mengakhiri pekerjaan

1. Pastikan transaksi penting sudah berstatus benar.
2. Pastikan tidak ada draft yang seharusnya disubmit.
3. Periksa notifikasi gagal.
4. Kasir memastikan shift sudah ditutup/disubmit sesuai jadwal.
5. Gudang memastikan penerimaan/pengiriman fisik sesuai dokumen.
6. Purchasing memeriksa PO dan receipt outstanding.
7. Approver memeriksa antrean prioritas.
8. Logout dari perangkat bersama.
