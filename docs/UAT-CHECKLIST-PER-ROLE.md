# Checklist User Acceptance Test (UAT) per Role

Dokumen ini digunakan untuk menguji pekerjaan pengguna pada setiap role aplikasi GudangToko. Checklist disusun berdasarkan `config/rbac.php`, route aplikasi, guide per role, dan data `DemoFullApplicationSeeder`.

> **Peringatan:** jalankan UAT hanya pada environment `local`, `testing`, atau database khusus UAT. Jangan menjalankan `migrate:fresh`, seeder demo, transaksi simulasi, atau pengujian destructive pada production.

## 1. Identitas pelaksanaan UAT

| Informasi | Isian |
|---|---|
| Nama aplikasi/build | |
| Environment | |
| URL aplikasi | |
| Tanggal pengujian | |
| Nama tester | |
| Browser/perangkat | |
| Database/seeder | |
| Nomor tiket/build | |

Status hasil yang digunakan:

- `[x]` = lulus.
- `[ ]` = belum diuji.
- `GAGAL` = hasil aktual tidak sama dengan hasil yang diharapkan.
- `N/A` = tidak relevan atau data prasyarat tidak tersedia; alasan wajib dicatat.

Untuk setiap checklist yang gagal, simpan URL, waktu kejadian, input, hasil aktual, screenshot, dan nomor dokumen yang terbentuk.

## 2. Persiapan environment dan data

### 2.1 Persiapan aplikasi

- [ ] `APP_ENV` dipastikan `local` atau `testing`.
- [ ] Database UAT sudah di-backup atau dapat dibuat ulang.
- [ ] Migration sudah berjalan tanpa error.
- [ ] `DemoFullApplicationSeeder` sudah dijalankan jika memakai data demo.
- [ ] Asset frontend sudah dibangun dan halaman tidak mengalami error JavaScript.
- [ ] Queue dan scheduler dijalankan jika menguji notifikasi, export, atau laporan terjadwal.
- [ ] Mail/WA/Telegram memakai sandbox, fake, atau channel testing.
- [ ] Tester mempunyai akses ke log aplikasi tanpa membocorkan credential.

Perintah data demo lokal:

```bash
php artisan migrate
php artisan db:seed --class=DemoFullApplicationSeeder
```

Semua akun demo berikut memakai password `password`.

### 2.2 Akun demo per role

| Role | Login/email | Lokasi/lingkup | Tersedia dari seeder demo |
|---|---|---|---|
| `super_admin` | `super_admin@gudangtoko.test` | Global | Ya |
| `owner_viewer` | `owner_viewer@gudangtoko.test` | Global | Ya |
| `owner_approver` | `owner_approver@gudangtoko.test` | Global | Ya |
| `admin_user` | `admin_user@gudangtoko.test` | Global | Ya |
| `admin_config` | `admin_config@gudangtoko.test` | Global | Ya |
| `kepala_gudang` | `kepala_gudang@gudangtoko.test` | Gudang Demo Utama | Ya |
| `staff_gudang` | `staff_gudang@gudangtoko.test` | Gudang Demo Utama | Ya |
| `picker_packer` | `picker_packer@gudangtoko.test` | Gudang Demo Utama | Ya |
| `purchasing` | `purchasing@gudangtoko.test` | Gudang Demo Utama | Ya |
| `kepala_toko` | `kepala_toko@gudangtoko.test` | Toko Demo Pusat | Ya |
| `kasir` | `kasir@gudangtoko.test` | Toko Demo Pusat | Ya |
| `supervisor_shift` | `supervisor_shift@gudangtoko.test` | Toko Demo Pusat | Ya |
| `langganan_owner` | `langganan_owner@gudangtoko.test` | Customer B2B demo | Ya |
| `langganan_staff` | `langganan_staff@gudangtoko.test` | Customer B2B demo | Ya |
| `sales` | Buat akun UAT tersendiri | Customer dan target milik sales | Belum tersedia |

Untuk menguji isolasi data, siapkan minimal dua gudang, dua toko, dua customer B2B, dan dua user sales dengan assignment berbeda.

### 2.3 Data transaksi minimum

- [ ] Produk aktif memiliki SKU, satuan dasar, barcode, HPP, harga POS, harga B2B, minimum stock, dan safety stock.
- [ ] Tersedia produk dengan stok cukup, stok kosong, stok kritis, stok reserved, dan stok damaged.
- [ ] Tersedia supplier aktif dan nonaktif.
- [ ] Tersedia customer tunai dan customer B2B dengan alamat, termin, limit kredit, serta invoice overdue.
- [ ] Tersedia purchase request dan PO pada status draft, submitted, approved, sent, partial, dan completed.
- [ ] Tersedia transfer pada status draft, pending approval, approved, packed, shipped, partial received, dan completed.
- [ ] Tersedia order B2B, invoice, payment, shipment, retur, opname, loss, dan approval dalam beberapa status.
- [ ] Tersedia shift terbuka, closing submitted, approved, dan rejected.
- [ ] Tersedia employee, jadwal, attendance, pengajuan izin, dan koreksi attendance.
- [ ] Saldo stok serta saldo piutang awal dicatat agar dapat dibandingkan sebelum dan sesudah pengujian.

## 3. Checklist umum untuk setiap akun

Jalankan bagian ini untuk seluruh role.

### 3.1 Login, profil, dan sesi

- [ ] Login dengan email yang benar berhasil dan diarahkan ke dashboard yang sesuai role.
- [ ] Login dengan username yang benar berhasil.
- [ ] Password salah ditolak tanpa membocorkan apakah akun terdaftar.
- [ ] Field wajib login divalidasi dan pesan ditampilkan dalam Bahasa Indonesia.
- [ ] User nonaktif tidak dapat menggunakan aplikasi.
- [ ] Nama user, role, dan lokasi kerja yang tampil sesuai akun.
- [ ] Refresh halaman tidak menghilangkan sesi.
- [ ] Tombol logout mengakhiri sesi dan halaman internal tidak dapat dibuka kembali tanpa login.

### 3.2 Navigasi dan tampilan

- [ ] Menu yang sesuai permission tampil dan dapat dibuka.
- [ ] Menu/aksi tanpa permission tidak tampil.
- [ ] Judul, breadcrumb, tombol kembali, filter, pagination, dan empty state tampil benar.
- [ ] Tabel dapat dicari, difilter, diurutkan, dan tidak menampilkan error.
- [ ] Form tetap terbaca pada desktop dan perangkat mobile.
- [ ] Format rupiah, qty, tanggal, waktu, status, dan nomor dokumen konsisten.
- [ ] Tombol aksi tidak dapat dikirim dua kali ketika request sedang diproses.
- [ ] Tidak ada error browser console yang menghambat pekerjaan utama.

### 3.3 Validasi, keamanan, dan audit

- [ ] Submit kosong/invalid ditolak dan input yang sudah benar tidak hilang.
- [ ] Akses URL tanpa permission menghasilkan `403` atau respons penolakan yang aman.
- [ ] Mengganti ID pada URL tidak membuka data lokasi/customer/user lain.
- [ ] Request perubahan status tanpa CSRF atau sesi yang valid ditolak.
- [ ] Nominal/qty negatif, nol yang tidak sah, terlalu besar, dan format tidak valid ditolak.
- [ ] Upload menolak tipe atau ukuran file yang tidak diizinkan.
- [ ] Data final tidak menyediakan edit/delete langsung; koreksi memakai void, reverse, retur, atau adjustment.
- [ ] Aksi penting mencatat actor, waktu, referensi, status sebelum/sesudah, serta alasan pada audit/history.
- [ ] Pesan sukses hanya tampil setelah transaksi benar-benar tersimpan.

### 3.4 Pembatasan lingkup data

- [ ] User gudang hanya melihat lokasi kerja yang ditugaskan.
- [ ] User toko hanya melihat cabang yang ditugaskan.
- [ ] User B2B hanya melihat customer, order, invoice, shipment, dan pembayaran miliknya.
- [ ] User sales hanya melihat customer, order, target, bonus, dan data assignment miliknya.
- [ ] Owner dan Super Admin dapat memakai filter global sesuai permission.
- [ ] Export mengikuti filter dan lingkup data yang sama dengan tampilan layar.

## 4. UAT role `super_admin`

Hasil utama: seluruh fungsi dapat dikelola, tetapi histori transaksi final tetap tidak boleh dimanipulasi langsung.

### 4.1 User, role, dan permission

- [ ] Buka `/admin/users`, cari, filter, lihat detail, dan export user.
- [ ] Buat user baru dengan email, username, role, dan status yang valid.
- [ ] Duplikasi email atau username ditolak.
- [ ] Edit user lalu pastikan perubahan tersimpan.
- [ ] Assign beberapa lokasi kerja dan tetapkan satu lokasi default.
- [ ] Reset password user menghasilkan proses/notifikasi yang benar.
- [ ] Nonaktifkan user lalu pastikan user tersebut tidak dapat login.
- [ ] Buka `/admin/roles`, buat role UAT, duplikasi role, dan ubah permission.
- [ ] Perubahan permission efektif setelah sesi diperbarui.
- [ ] Daftar `/admin/permissions` dapat dibuka dan dikelompokkan dengan benar.

### 4.2 Master organisasi dan produk

- [ ] Buat, edit, lihat, dan nonaktifkan warehouse.
- [ ] Buat, edit, lihat, dan nonaktifkan branch beserta gudang pemasok default.
- [ ] Buat/edit/nonaktifkan unit, kategori, brand, dan produk.
- [ ] Tambahkan gambar utama, satuan konversi, serta barcode produk.
- [ ] Cetak/export barcode dan pastikan produk yang dipilih benar.
- [ ] Preview import produk dengan data valid dan invalid.
- [ ] Commit import hanya memasukkan baris valid sesuai hasil preview.
- [ ] Export produk menghasilkan file yang dapat dibuka dan sesuai filter.

### 4.3 Supplier, customer, harga, dan konfigurasi

- [ ] Buat/edit/nonaktifkan supplier dan uji import/export.
- [ ] Buat/edit/nonaktifkan customer dan download formulir registrasi.
- [ ] Hubungkan akses user B2B ke customer yang benar.
- [ ] Ubah verifikasi, harga khusus, termin, serta limit kredit customer.
- [ ] Kelola product price, price rule, special price, revisi, end date, dan simulator harga.
- [ ] Approve/reject perubahan harga sensitif.
- [ ] Ubah pengaturan umum dan pastikan nilai dimuat kembali.
- [ ] Ubah format nomor dokumen tanpa menghasilkan nomor duplikat.

### 4.4 Notifikasi, audit, sales, pajak, dan sistem

- [ ] Kelola channel, template, recipient, schedule, dan alert rule notifikasi.
- [ ] Preview template/alert tidak mengirim pesan nyata.
- [ ] Test channel, run schedule, retry log, dan revoke secure report token bekerja sesuai status.
- [ ] Buka audit log, keamanan, anomali, export audit, dan resolve anomali.
- [ ] Kelola assignment customer sales serta target/bonus sales.
- [ ] Kelola profil, rule, klasifikasi, dokumen, sinkronisasi, rekonsiliasi, dan periode pajak.
- [ ] Export periode pajak mengikuti periode yang dipilih.
- [ ] Health check menampilkan status database, storage, queue, scheduler, PHP, dan Laravel.
- [ ] Semua alur operasional pada bagian role lain dapat dilakukan sesuai permission penuh.
- [ ] Transaksi final tetap hanya dapat dikoreksi melalui workflow resmi, bukan dihapus.

## 5. UAT role `admin_user`

- [ ] Dashboard dapat dibuka.
- [ ] Daftar, detail, filter, dan export user dapat dibuka.
- [ ] User baru dapat dibuat dengan role yang benar.
- [ ] Data user dapat diedit dan divalidasi.
- [ ] Lokasi kerja dapat di-assign dan lokasi default hanya satu.
- [ ] Reset password dapat dijalankan.
- [ ] User dapat dinonaktifkan dan histori actor tetap tersimpan.
- [ ] URL master produk, supplier, customer, warehouse, branch, setting, transaksi, laporan, dan audit ditolak.
- [ ] URL pengelolaan role/permission ditolak karena role ini hanya mengelola user dan lokasi.
- [ ] User lokasi A tidak salah terhubung ke lokasi B setelah perubahan assignment.

## 6. UAT role `admin_config`

### 6.1 Fungsi yang diizinkan

- [ ] Dashboard dapat dibuka.
- [ ] CRUD dan nonaktifkan warehouse serta branch berhasil.
- [ ] CRUD, import, export, gambar, barcode, unit, kategori, brand, dan produk berhasil.
- [ ] CRUD, import, dan export supplier berhasil.
- [ ] CRUD, import, export, akses B2B, pengaturan, verifikasi, dan limit customer berhasil.
- [ ] Product price, price rule, special price, histori, revisi, serta simulator dapat dikelola.
- [ ] Pengaturan umum dan nomor dokumen dapat dikelola.
- [ ] Seluruh konfigurasi dan operasi notifikasi dapat dijalankan memakai channel testing.
- [ ] Assignment customer sales, target sales, dan performance sales dapat dikelola/dilihat.
- [ ] Audit log dapat dilihat/export, tetapi anomali tidak dapat di-resolve tanpa permission.
- [ ] HPP/margin sensitif hanya tampil pada halaman yang memang diizinkan.
- [ ] Health check dapat dibuka.

### 6.2 Batasan

- [ ] Pengelolaan user, role, dan permission ditolak.
- [ ] Approval owner, PO, transfer, opname, retur, shift, pembayaran, dan pajak ditolak.
- [ ] Pembuatan transaksi POS, receipt, shipment, serta mutasi stok ditolak.
- [ ] Data transaksi hanya berubah sebagai akibat konfigurasi yang sah, bukan lewat edit histori.

## 7. UAT role `owner_viewer`

- [ ] `/owner/dashboard` menampilkan KPI sesuai periode dan lokasi yang dipilih.
- [ ] Dashboard menampilkan penjualan, margin/HPP, stok, pembelian, retur/loss, dan piutang secara konsisten.
- [ ] Daftar dan detail approval dapat dilihat.
- [ ] Laporan harian, gudang, retail, B2B, supplier, pricing, piutang, loss, attendance, audit/notifikasi dapat dibuka.
- [ ] Detail stok, kartu stok, mutasi, PO, receipt, transfer, opname, retur, shift, invoice, payment, dan shipment dapat dilihat.
- [ ] HPP dan margin sensitif tampil untuk owner.
- [ ] Audit log, security log, serta anomaly alert dapat dilihat.
- [ ] Export laporan dan audit mengikuti filter serta dapat dibuka.
- [ ] Tombol approve/reject tidak tampil.
- [ ] POST approve/reject melalui URL langsung ditolak.
- [ ] CRUD master, setting, user, notifikasi, transaksi, dan koreksi ditolak.
- [ ] Aktivitas read-only tidak mengubah status, saldo stok, kas, atau piutang.

## 8. UAT role `owner_approver`

Jalankan seluruh checklist `owner_viewer`, kemudian uji:

- [ ] Approve dan reject approval umum dengan alasan wajib.
- [ ] Approve/reject PO dan pastikan status/history berubah satu kali.
- [ ] Approve transfer lalu pastikan reservation stok sumber sesuai qty.
- [ ] Approve/reject/complete opname dan pastikan adjustment hanya terbentuk setelah approval yang sah.
- [ ] Approve retur/loss dan pastikan settlement atau mutasi mengikuti keputusan.
- [ ] Approve/reject perubahan harga sensitif.
- [ ] Verifikasi/reject pembayaran dan pastikan alokasi piutang hanya terjadi setelah verified.
- [ ] Approve koreksi/credit note piutang.
- [ ] Approve/reject permintaan serta koreksi attendance.
- [ ] Resolve anomaly tanpa mengubah transaksi asal.
- [ ] Kelola dan transisikan periode pajak, termasuk approve, lapor, bayar, lock, dan reopen sesuai state.
- [ ] Test/run/retry notifikasi berhasil, tetapi konfigurasi channel/template tetap read-only.
- [ ] Klik approve dua kali tidak menggandakan mutasi, reservation, jurnal, atau alokasi.
- [ ] Approval oleh pembuat transaksi sendiri ditolak bila aturan segregation of duties berlaku.
- [ ] CRUD user, role, master, setting, dan transaksi operasional ditolak jika tidak memiliki permission khusus.

## 9. UAT role `kepala_gudang`

### 9.1 Stok dan lokasi gudang

- [ ] Dashboard gudang hanya memuat lokasi yang diizinkan.
- [ ] Saldo on hand, reserved, damaged, available, dan nilai stok dapat dilihat.
- [ ] Kartu stok, batch, detail mutasi, filter, dan running balance konsisten.
- [ ] Buat/edit/nonaktifkan zone, rack, dan bin.
- [ ] Pindahkan stok antar lokasi internal dan pastikan mutasi keluar/masuk seimbang.
- [ ] Produk beserta unit/kategori/brand/barcode dapat dibuat, diedit, di-import, diexport, dan dinonaktifkan.
- [ ] Supplier dapat dilihat, dibuat, diedit, di-import, serta diexport; penghapusan histori tetap ditolak.
- [ ] Customer beserta akses B2B, pengaturan, verifikasi, harga khusus, termin, dan limit dapat dikelola.
- [ ] Harga dapat dilihat/diperbarui dan margin sensitif tampil, tetapi approval harga mengikuti permission approver.

### 9.2 PO, receipt, transfer, dan B2B

- [ ] Purchase request dapat dibuat, dilihat, serta di-approve/reject.
- [ ] PO dapat dilihat dan di-approve/reject, tetapi pembuatan PO baru ditolak.
- [ ] Buat/edit/post Goods Receipt dari PO.
- [ ] Receipt partial memperbarui qty diterima, outstanding, stok, status PO, dan HPP.
- [ ] Posting receipt kedua kali tidak menggandakan stok.
- [ ] Buat/submit/approve/pack/ship/receive/complete/cancel transfer sesuai state.
- [ ] Short pick dan discrepancy dapat diselesaikan dengan alasan serta bukti.
- [ ] Review/approve/reject/reserve/pack/ship order B2B.
- [ ] Release/expire reservation mengembalikan available stock tepat satu kali.
- [ ] Buat/post shipment dan upload proof of delivery.
- [ ] Terbitkan invoice serta verifikasi pembayaran sesuai order/customer.

### 9.3 Opname, retur, loss, piutang, dan attendance

- [ ] Buat, start, count/import count, submit, review variance, approve/reject, dan complete opname.
- [ ] Approve opname menghasilkan adjustment sesuai variance dan mutasi dapat ditelusuri.
- [ ] Buat, inspect, approve, dan settle retur.
- [ ] Catat dan approve loss; stok damaged/on hand berubah sesuai keputusan.
- [ ] Lihat piutang, input pembayaran, reminder, adjustment, approval, dan credit limit.
- [ ] Kelola employee, work shift, schedule, attendance, request, correction, dan approval.
- [ ] Laporan operasional dapat dilihat, tetapi export ditolak jika route mensyaratkan `reports.export`.
- [ ] Akses lokasi di luar assignment ditolak.
- [ ] Pengelolaan user/role/setting sistem ditolak.

## 10. UAT role `staff_gudang`

- [ ] Dashboard, produk, supplier, customer, saldo, kartu stok, batch, dan mutasi dapat dilihat.
- [ ] Transaksi stok/lokasi yang diizinkan dapat dibuat tanpa dapat approve sendiri.
- [ ] Buat/edit/post Goods Receipt dan pastikan stok/HPP berubah hanya saat posting.
- [ ] Buat/submit/pack/ship/cancel transfer sesuai state yang diizinkan.
- [ ] Approval transfer ditolak.
- [ ] Buat/start/count/import/submit opname, tetapi approve/reject/complete ditolak.
- [ ] Buat retur dan lakukan inspection, tetapi approve/settlement ditolak.
- [ ] Buat loss, tetapi approve loss ditolak.
- [ ] Lihat order B2B tanpa dapat reserve/reject/approve order.
- [ ] Buat/update/post shipment serta upload proof sesuai permission.
- [ ] Lihat invoice/piutang, input pembayaran, dan catat reminder.
- [ ] Buat pembayaran, tetapi verifikasi pembayaran ditolak.
- [ ] Lihat komplain tanpa mengubah settlement.
- [ ] Check-in/out dan pengajuan attendance pribadi berhasil.
- [ ] Edit produk/supplier/customer, melihat margin sensitif, dan membuka admin ditolak.
- [ ] Seluruh data dibatasi pada Gudang Demo Utama.

## 11. UAT role `picker_packer`

- [ ] Dashboard gudang, produk, stok, kartu stok, batch, dan order B2B dapat dilihat.
- [ ] Daftar transfer dapat dibuka.
- [ ] Form packing dapat dibuka untuk transfer yang valid.
- [ ] Scan/pilih item dan input picked qty menghasilkan packed qty yang benar.
- [ ] Over-pick dan produk yang tidak ada pada dokumen ditolak.
- [ ] Short pick memerlukan qty/alasan yang benar.
- [ ] Opname/koreksi stok dapat dibuat dan dihitung sesuai `stock_adjustments.create`.
- [ ] Shipment dapat dilihat dan proof/status yang diizinkan dapat diperbarui.
- [ ] Check-in/out dan pengajuan attendance pribadi berhasil.
- [ ] Membuat transfer baru ditolak.
- [ ] Approve/cancel/ship/receive/complete transfer ditolak jika tidak tercakup aksi packing.
- [ ] Reserve/reject/approve order B2B ditolak.
- [ ] Approve opname, edit master, melihat HPP/margin, POS, piutang, dan admin ditolak.
- [ ] Stok tidak berkurang hanya karena membuka atau menyimpan packing; perubahan mengikuti state transfer.

## 12. UAT role `purchasing`

- [ ] Dashboard gudang dan saldo stok dapat dilihat untuk menilai kebutuhan pembelian.
- [ ] Produk dapat dilihat dan diexport, tetapi tidak dapat dibuat/diedit.
- [ ] Supplier dapat dibuat, dilihat, diedit, dinonaktifkan, di-import, dan diexport.
- [ ] Price dan HPP history dapat dilihat tanpa mengubah harga.
- [ ] Buat/review/convert purchase request sesuai permission pembuatan PO.
- [ ] Approve/reject purchase request ditolak.
- [ ] Buat/edit/submit/send/cancel PO sesuai state.
- [ ] Print dan export PO menghasilkan dokumen dengan supplier, item, qty, harga, pajak, biaya, dan total yang benar.
- [ ] Approve PO ditolak.
- [ ] Goods Receipt dan hasil QC dapat dilihat, tetapi create/edit/post ditolak.
- [ ] Status PO menjadi partial/completed setelah receipt diposting oleh gudang.
- [ ] Laporan supplier dan laporan terkait dapat dilihat.
- [ ] Transaksi stok, transfer, opname, POS, pembayaran, serta menu admin ditolak.
- [ ] PO approved/final tidak dapat diedit bebas atau dikirim dua kali.

## 13. UAT role `kepala_toko`

### 13.1 Operasional toko

- [ ] Dashboard hanya menampilkan Toko Demo Pusat.
- [ ] Produk, barcode, customer, harga, dan stok toko dapat dilihat.
- [ ] Customer dapat dibuat/diedit untuk kebutuhan toko.
- [ ] Restock request/transfer dapat dibuat dan dipantau.
- [ ] Transfer shipped dapat diterima secara full atau partial.
- [ ] Over-receive ditolak dan discrepancy mencatat qty kurang/rusak.
- [ ] Opname/koreksi stok toko dapat dibuat dan di-approve sesuai permission.
- [ ] Retur dapat dibuat dan di-approve; inspection/settlement tanpa permission ditolak.

### 13.2 POS, shift, piutang, dan attendance

- [ ] POS dan detail/struk penjualan dapat dilihat.
- [ ] Void penjualan dengan alasan menghasilkan reversal dan audit yang benar.
- [ ] Pembuatan penjualan POS baru ditolak bila akun hanya memiliki role `kepala_toko` tanpa `pos.create`.
- [ ] Buka shift, catat expense, submit closing, lihat report, serta export shift dapat dijalankan sesuai permission.
- [ ] Approve/reject closing mengunci status dan mencatat reviewer.
- [ ] Selisih expected cash dan actual cash dihitung benar.
- [ ] Piutang, pembayaran, serta reminder toko dapat dikelola.
- [ ] Employee, schedule, shift kerja, attendance, request, correction, approval, dan export dapat dikelola.
- [ ] Laporan retail, attendance, produktivitas, dan stok dapat dilihat.
- [ ] Data cabang lain dan menu admin/config/gudang pusat ditolak.

## 14. UAT role `kasir`

- [ ] Check-in dan pengajuan izin pribadi berhasil.
- [ ] Buka shift dengan opening cash valid berhasil.
- [ ] User tidak dapat membuka shift kedua yang melanggar aturan shift aktif.
- [ ] POS menolak checkout tanpa shift aktif.
- [ ] Cari/scan produk, ubah qty, pilih customer, dan hitung quote berhasil.
- [ ] Harga, diskon, pajak, total, pembayaran, kembalian, HPP snapshot, serta nomor struk benar.
- [ ] Pembayaran tunai dan non-tunai memperbarui ringkasan shift dengan benar.
- [ ] Stok tidak cukup, harga minimum, qty invalid, dan pembayaran kurang ditolak.
- [ ] Hold, lihat hold, resume, dan cancel hold tidak mengubah stok sebelum checkout.
- [ ] Checkout sukses mengurangi stok tepat satu kali dan membuat mutasi/referensi penjualan.
- [ ] Detail penjualan dan print struk dapat dibuka.
- [ ] Void dan retur penjualan ditolak untuk kasir tanpa permission terkait.
- [ ] Lihat piutang dan input pembayaran yang diizinkan berhasil.
- [ ] Catat expense shift dengan nominal serta bukti valid.
- [ ] Closing menghitung cash sales, non-cash, refund, expense, receivable, expected cash, actual cash, dan difference dengan benar.
- [ ] Setelah closing submitted, transaksi/expense shift tidak dapat diubah secara tidak sah.
- [ ] Approve/reject closing ditolak.
- [ ] Check-out berhasil dan waktu attendance tercatat.
- [ ] Akses cabang lain, stok gudang, master, laporan sensitif, serta admin ditolak.

## 15. UAT role `supervisor_shift`

- [ ] Dashboard retail hanya menampilkan cabang yang ditugaskan.
- [ ] POS, produk, detail sale, dan struk dapat dilihat.
- [ ] Penjualan baru ditolak karena role ini tidak memiliki `pos.create`.
- [ ] Void/retur melalui permission `pos.void` meminta alasan dan menghasilkan reversal yang benar.
- [ ] Shift dapat dilihat, dibuka, diberi expense, dan disubmit closing.
- [ ] Approval/reject closing ditolak karena role ini tidak memiliki `cash_shifts.approve`.
- [ ] Transfer masuk dapat dilihat dan diterima full/partial sesuai fisik.
- [ ] Piutang, pembayaran, dan reminder dapat dikelola sesuai permission.
- [ ] Employee, work shift, schedule, serta koreksi attendance dapat dikelola.
- [ ] Approval attendance ditolak.
- [ ] Laporan retail/attendance dapat dilihat tanpa export jika tidak memiliki `reports.export`.
- [ ] Stok gudang pusat, master data, pricing sensitif, serta admin ditolak.

## 16. UAT role `langganan_owner`

Gunakan `/langganan/login` dan selalu uji dengan minimal dua customer B2B.

- [ ] Login portal berhasil dan diarahkan ke dashboard B2B.
- [ ] Dashboard hanya menampilkan ringkasan customer sendiri.
- [ ] Profil usaha dan alamat customer sendiri dapat dilihat.
- [ ] Customer lain tidak dapat dibuka dengan manipulasi ID/URL.
- [ ] Katalog hanya menampilkan produk B2B aktif, harga yang berlaku, minimum order, dan available stock yang diizinkan.
- [ ] Tambah item, update qty, hapus item, dan keranjang bekerja tanpa langsung mereservasi stok.
- [ ] Checkout memvalidasi alamat, stok, minimum order, harga, limit kredit, overdue, jadwal, dan persetujuan syarat.
- [ ] Order yang terbentuk memiliki customer, item, harga, total, alamat, dan status yang benar.
- [ ] Daftar/detail order hanya menampilkan order customer sendiri.
- [ ] Cancel order hanya tersedia pada state yang diizinkan dan melepas reservation tepat satu kali.
- [ ] Reorder menghitung ulang harga, status produk, dan stok saat ini.
- [ ] Shipment sendiri dapat dilacak dan dikonfirmasi diterima.
- [ ] Invoice sendiri dapat dilihat dan PDF dapat dibuka.
- [ ] Pembayaran dengan referensi/bukti valid dapat dibuat dan berstatus menunggu verifikasi bila diwajibkan.
- [ ] Komplain order/shipment/item dapat dibuat dan hanya komplain sendiri yang terlihat.
- [ ] HPP, margin, detail stok internal, customer lain, perubahan harga, approval, dan menu internal ditolak.
- [ ] Perubahan profil ditandai N/A bila UI/route hanya menyediakan tampilan profil read-only pada build yang diuji.

## 17. UAT role `langganan_staff`

Permission aktual role ini sama dengan `langganan_owner`; jalankan seluruh checklist pada bagian `langganan_owner`, kemudian pastikan:

- [ ] Akun terhubung ke customer B2B yang benar.
- [ ] Data customer lain tetap terisolasi meskipun user mengetahui ID dokumen.
- [ ] Hak akses yang tampil tidak melebihi permission aktual role.
- [ ] Jika bisnis mengharapkan perbedaan hak owner dan staff, perbedaan yang belum diterapkan dicatat sebagai gap requirement/RBAC, bukan dianggap lulus.

## 18. UAT role `sales`

Prasyarat: buat dua akun sales, dua customer assignment berbeda, order milik masing-masing sales, serta target/bonus per periode.

- [ ] Login diarahkan ke dashboard sales.
- [ ] Dashboard hanya menghitung customer dan order yang ditugaskan kepada sales tersebut.
- [ ] Daftar/detail customer hanya menampilkan customer sendiri.
- [ ] Manipulasi ID customer milik sales lain ditolak.
- [ ] Buat order untuk customer assignment sendiri berhasil.
- [ ] Pembuatan order untuk customer yang tidak di-assign ditolak.
- [ ] Daftar/detail order hanya menampilkan order sendiri.
- [ ] Stok yang tampil hanya available stock untuk membantu penjualan dan tidak menyediakan aksi mutasi.
- [ ] Target serta bonus periode sendiri dapat dilihat dan nilainya sesuai konfigurasi.
- [ ] Performance seluruh sales ditolak.
- [ ] Kelola target/bonus dan assignment customer ditolak.
- [ ] Katalog internal sensitif, HPP/margin, POS, gudang, piutang global, master, audit, serta admin ditolak.
- [ ] Order yang dibuat sales masuk ke workflow B2B internal dengan actor dan sales assignment yang benar.

## 19. Skenario lintas role end-to-end

### 19.1 E2E-01 — Pembelian sampai stok tersedia

- [ ] `purchasing` membuat dan submit PO.
- [ ] `kepala_gudang` atau `owner_approver` meng-approve PO.
- [ ] `purchasing` mengirim/print PO.
- [ ] `staff_gudang` membuat dan posting receipt partial.
- [ ] PO menjadi partial, stok accepted bertambah, dan rejected/damaged tercatat.
- [ ] `staff_gudang` posting receipt sisa dan PO menjadi completed.
- [ ] HPP moving average, histori HPP, supplier score, kartu stok, dan saldo akhir cocok.

### 19.2 E2E-02 — Restock toko dan transfer

- [ ] `kepala_toko` membuat restock request.
- [ ] `kepala_gudang` mereview, approve, dan convert menjadi transfer.
- [ ] `picker_packer` melakukan picking/packing.
- [ ] `staff_gudang` mengirim transfer.
- [ ] `supervisor_shift` atau `kepala_toko` menerima transfer dengan satu item kurang/rusak.
- [ ] Stok sumber, in-transit, stok tujuan, reservation, dan discrepancy cocok.
- [ ] `kepala_gudang` menyelesaikan discrepancy melalui workflow resmi.

### 19.3 E2E-03 — Operasional kasir satu shift

- [ ] `kasir` check-in dan membuka shift.
- [ ] `kasir` membuat penjualan tunai, non-tunai, dan kredit.
- [ ] `kasir` membuat hold lalu resume sampai checkout.
- [ ] `supervisor_shift` atau `kepala_toko` memproses void/retur yang sah.
- [ ] `kasir` mencatat expense dan submit closing.
- [ ] `kepala_toko` mereview lalu approve/reject closing.
- [ ] Penjualan, stok, refund, piutang, expected cash, actual cash, difference, dan attendance cocok.

### 19.4 E2E-04 — Order B2B sampai pelunasan

- [ ] `langganan_staff`, `langganan_owner`, atau `sales` membuat order.
- [ ] Sistem memvalidasi harga, stok, minimum order, limit kredit, dan overdue.
- [ ] `kepala_gudang` mereview serta reserve order.
- [ ] `picker_packer`/gudang pack dan shipment diposting.
- [ ] Invoice diterbitkan dan outstanding piutang terbentuk.
- [ ] Customer mengonfirmasi penerimaan atau membuat komplain.
- [ ] Customer mengunggah pembayaran dan user berwenang memverifikasi.
- [ ] Alokasi pembayaran, outstanding invoice, receivable ledger, reservation, shipment, dan status order cocok.

### 19.5 E2E-05 — Opname dan koreksi stok

- [ ] `staff_gudang`/`picker_packer` membuat, start, count, dan submit opname.
- [ ] Variance positif dan negatif ditampilkan benar.
- [ ] `kepala_gudang` atau `owner_approver` meng-approve/reject.
- [ ] Approval menghasilkan adjustment satu kali; reject tidak mengubah stok.
- [ ] Saldo, kartu stok, mutasi, audit, dan anomaly alert dapat direkonsiliasi.

### 19.6 E2E-06 — Retur, loss, dan piutang

- [ ] User operasional membuat retur dengan sumber dan item yang valid.
- [ ] Gudang melakukan inspection kondisi barang.
- [ ] Approver menyetujui dan gudang melakukan settlement.
- [ ] Barang baik kembali ke stok; barang rusak masuk damaged/loss sesuai keputusan.
- [ ] Refund/credit note mengurangi piutang tanpa menghapus transaksi asal.
- [ ] Reminder, pembayaran partial, aging, outstanding, dan ledger dapat direkonsiliasi.

### 19.7 E2E-07 — Laporan, audit, dan notifikasi

- [ ] Transaksi lintas modul muncul pada laporan harian yang benar.
- [ ] Owner dapat memfilter dan export laporan.
- [ ] Daily report dijalankan melalui schedule/queue testing.
- [ ] Pesan menggunakan template dan recipient yang benar tanpa membocorkan secret.
- [ ] Notification log mencatat queued/sent/failed dan retry tidak menggandakan pesan tanpa alasan.
- [ ] Secure report token valid dapat dipakai, token expired/revoked ditolak.
- [ ] Audit log dan anomaly alert mencatat aksi sensitif yang dilakukan selama UAT.

## 20. Pemeriksaan integritas setelah UAT

- [ ] Tidak ada stok on hand, reserved, damaged, atau available yang tidak logis/negatif.
- [ ] Total mutasi dan running balance cocok dengan saldo stok per produk/lokasi.
- [ ] Reservation aktif hanya terkait dokumen aktif dan tidak dilepas/dipotong dua kali.
- [ ] PO ordered/received/outstanding cocok dengan seluruh receipt.
- [ ] Transfer source/shipped/received/discrepancy cocok per item.
- [ ] Total sale, payment, refund, expense, receivable, dan shift closing cocok.
- [ ] Invoice, payment allocation, credit note, dan outstanding piutang cocok.
- [ ] Nomor dokumen unik dan nomor dokumen void tidak digunakan ulang.
- [ ] Semua perubahan state memiliki status history dan actor.
- [ ] Tidak ada transaksi final yang hilang karena delete/edit langsung.
- [ ] Export tidak memuat data di luar permission, lokasi, customer, atau assignment user.
- [ ] Log tidak memuat password, token utuh, credential channel, atau data sensitif yang tidak perlu.

## 21. Catatan defect

Salin tabel berikut untuk setiap temuan.

| Field | Isian |
|---|---|
| ID defect | |
| Role/user | |
| Modul/URL | |
| Waktu kejadian | |
| Prasyarat/data | |
| Langkah reproduksi | |
| Hasil yang diharapkan | |
| Hasil aktual | |
| Severity | Critical / High / Medium / Low |
| Screenshot/log/nomor dokumen | |
| Status | Open / Retest / Closed |
| Tester/retester | |

## 22. Rekap dan persetujuan

| Role | Total diuji | Lulus | Gagal | N/A | Tester | Status akhir |
|---|---:|---:|---:|---:|---|---|
| `super_admin` | | | | | | |
| `admin_user` | | | | | | |
| `admin_config` | | | | | | |
| `owner_viewer` | | | | | | |
| `owner_approver` | | | | | | |
| `kepala_gudang` | | | | | | |
| `staff_gudang` | | | | | | |
| `picker_packer` | | | | | | |
| `purchasing` | | | | | | |
| `kepala_toko` | | | | | | |
| `kasir` | | | | | | |
| `supervisor_shift` | | | | | | |
| `langganan_owner` | | | | | | |
| `langganan_staff` | | | | | | |
| `sales` | | | | | | |

Kriteria selesai:

- [ ] Seluruh skenario Critical dan High sudah lulus.
- [ ] Tidak ada kebocoran permission, lokasi, customer, atau assignment.
- [ ] Seluruh selisih stok, kas, dan piutang sudah direkonsiliasi.
- [ ] Defect Medium/Low yang ditunda memiliki tiket, owner, dan target perbaikan.
- [ ] Owner bisnis menyetujui hasil UAT.
- [ ] Tim teknis menyetujui kesiapan build.

| Persetujuan | Nama | Tanggal | Tanda tangan/catatan |
|---|---|---|---|
| Tester utama | | | |
| Owner bisnis | | | |
| Tim teknis | | | |

## 23. Referensi

- `config/rbac.php`
- `routes/web.php`
- `docs/TESTING.md`
- `docs/DEMO-SEED.md`
- `docs/DOMAIN.md`
- `docs/STATE-MACHINES.md`
- `guide/super-admin.md`
- `guide/owner.md`
- `guide/gudang.md`
- `guide/purchasing-supplier.md`
- `guide/toko-internal.md`
- `guide/langganan-b2b.md`
