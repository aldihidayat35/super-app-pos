# Guide Book Toko Internal

Panduan ini untuk `kepala_toko`, `kasir`, `supervisor_shift`, dan karyawan toko. Fokus utama toko internal adalah penjualan POS, shift kasir, closing, restock, penerimaan transfer, retur pelanggan, piutang toko, dan kehadiran.

## 1. Tujuan role Toko Internal

| Role | Fokus |
|---|---|
| `kepala_toko` | Mengawasi cabang, stok toko, restock, POS, shift, retur, piutang, dan karyawan. |
| `kasir` | Membuka shift, melakukan transaksi POS, menerima pembayaran, dan submit closing. |
| `supervisor_shift` | Memeriksa closing, approval void/selisih, dan memantau shift. |
| Karyawan toko | Check-in/out, jadwal, izin, dan aktivitas toko sesuai tugas. |

## 2. Menu utama Toko Internal

| Menu | URL | Fungsi |
|---|---|---|
| Dashboard Cabang | `/retail/dashboard` | KPI penjualan, shift, transaksi, dan performa cabang. |
| Kasir POS | `/retail/pos` | Input penjualan toko. |
| Checkout POS | `/retail/pos/checkout` | Penyelesaian pembayaran POS. |
| Transaksi Ditahan | `/retail/pos/holds` | Menahan dan melanjutkan transaksi. |
| Shift Aktif | `/retail/shifts/current` | Melihat shift kasir yang sedang berjalan. |
| Buka Shift | `/retail/shifts/open` | Membuka shift kasir. |
| Tutup Shift | `/retail/shifts/{id}/close` | Submit closing shift. |
| Pengeluaran Shift | `/retail/shifts/{id}/expenses` | Catat pengeluaran kas shift. |
| Riwayat Shift | `/retail/shifts` | Melihat daftar shift. |
| Approval Shift | `/retail/shifts/{id}/approval` | Approve/reject closing shift. |
| Laporan Shift | `/retail/shifts/{id}/report` | Laporan per shift. |
| Detail Penjualan | `/retail/sales/{id}` | Detail transaksi POS. |
| Print Struk | `/retail/sales/{id}/print` | Cetak struk. |
| Void Penjualan | `/retail/sales/{id}/void` | Ajukan/pasang void sesuai izin. |
| Retur Penjualan | `/retail/sales/{id}/return` | Retur transaksi POS. |
| Permintaan Restock | `/retail/restock-requests` | Minta stok dari gudang. |
| Terima Transfer | `/retail/stock-transfers/{id}/receive` | Konfirmasi barang dari gudang. |
| Piutang Toko | `/retail/receivables` | Piutang pelanggan toko. |
| Kehadiran | `/attendance/check` | Check-in/out karyawan. |
| Izin/Sakit/Cuti | `/attendance/requests` | Pengajuan kehadiran. |

## 3. Alur harian kasir

### 3.1 Check-in

1. Login.
2. Buka `/attendance/check`.
3. Klik check-in.
4. Pastikan status kehadiran tercatat.

Cara kerja di belakang layar:

- Attendance dicatat terhadap user/karyawan dan jadwal bila ada.
- Data kehadiran bisa dipakai untuk laporan produktivitas shift.
- Koreksi harus diajukan melalui menu koreksi, bukan edit langsung.

### 3.2 Buka shift

1. Buka `/retail/shifts/open`.
2. Pilih cabang/terminal jika diminta.
3. Isi modal awal kas.
4. Klik buka shift.

Cara kerja di belakang layar:

- POS biasanya memerlukan shift aktif.
- Shift menyimpan opening cash, expected cash, sales, expense, refund, dan variance.
- Sistem mencegah konflik shift aktif sesuai aturan yang diterapkan.

### 3.3 Transaksi POS

1. Buka `/retail/pos`.
2. Cari atau scan produk.
3. Isi qty.
4. Periksa harga, diskon, dan total.
5. Pilih pelanggan bila penjualan kredit/piutang.
6. Klik checkout.
7. Pilih metode pembayaran.
8. Simpan transaksi.
9. Cetak struk jika perlu.

Cara kerja di belakang layar:

- Sistem memvalidasi stok available cabang.
- Harga diambil dari price rule/product price sesuai channel POS.
- Harga minimum dicek agar tidak rugi.
- HPP dan margin disimpan sebagai snapshot pada item POS.
- Saat transaksi completed, stok cabang berkurang melalui InventoryService.
- Pembayaran dicatat dan shift expected cash/non-cash diperbarui.

### 3.4 Menahan transaksi

1. Saat transaksi belum selesai, klik tahan/hold bila tersedia.
2. Beri catatan.
3. Untuk melanjutkan, buka `/retail/pos/holds`.
4. Klik resume.

Cara kerja di belakang layar:

- Hold belum mengurangi stok.
- Hold hanya menyimpan keranjang sementara.
- Jika hold dibatalkan, tidak ada mutasi stok.

### 3.5 Retur penjualan

1. Buka detail penjualan `/retail/sales/{id}`.
2. Klik retur.
3. Pilih item dan qty retur.
4. Isi alasan dan kondisi barang.
5. Submit.

Cara kerja di belakang layar:

- Barang baik dapat kembali ke stok.
- Barang rusak masuk damaged/loss workflow.
- Refund atau credit note tergantung settlement.
- Retur final tidak menghapus transaksi awal.

### 3.6 Void penjualan

1. Buka `/retail/sales/{id}/void`.
2. Isi alasan void.
3. Submit.
4. Jika butuh approval, tunggu supervisor/owner.

Cara kerja di belakang layar:

- Void tidak menghapus transaksi.
- Sistem membuat reversal/penanda void dan audit.
- Stok/pembayaran dikoreksi melalui dokumen yang dapat ditelusuri.

## 4. Closing shift

### 4.1 Catat pengeluaran shift

1. Buka shift aktif.
2. Klik pengeluaran.
3. Isi kategori, nominal, catatan, dan bukti jika ada.
4. Simpan.

### 4.2 Tutup shift

1. Buka `/retail/shifts/current`.
2. Klik tutup shift.
3. Hitung kas fisik.
4. Isi actual cash.
5. Periksa:
   - cash sales,
   - non-cash sales,
   - refund,
   - expenses,
   - receivable,
   - expected cash,
   - selisih.
6. Submit closing.

Cara kerja di belakang layar:

- Setelah closing submitted, shift terkunci sebagian.
- Supervisor memeriksa selisih.
- Jika approved/closed, transaksi shift tidak boleh diedit.
- Selisih besar bisa memicu approval.

### 4.3 Approval closing oleh supervisor/kepala toko

1. Buka `/retail/shifts`.
2. Pilih shift dengan status menunggu verifikasi.
3. Buka approval.
4. Cocokkan laporan dengan kas fisik.
5. Approve atau reject dengan catatan.

## 5. Restock toko

### 5.1 Membuat permintaan restock

1. Buka `/retail/restock-requests`.
2. Klik buat request.
3. Pilih produk.
4. Isi qty, prioritas, dan catatan.
5. Submit.

Cara kerja di belakang layar:

- Request restock belum mengubah stok.
- Gudang akan review, approve/revisi/reject, lalu convert menjadi transfer.
- Stok toko baru bertambah setelah transfer diterima.

### 5.2 Menerima transfer dari gudang

1. Buka transfer yang sudah shipped.
2. Buka `/retail/stock-transfers/{id}/receive`.
3. Periksa barang fisik.
4. Isi qty diterima, kurang, rusak.
5. Upload foto/bukti jika perlu.
6. Submit penerimaan.

Cara kerja di belakang layar:

- Stok cabang bertambah hanya sebesar qty diterima.
- Selisih menjadi discrepancy.
- Penerimaan partial bisa dilakukan jika kiriman datang sebagian.
- Sistem mencegah over-receive.

## 6. Piutang toko

1. Buka `/retail/receivables`.
2. Lihat pelanggan dengan saldo piutang.
3. Catat pembayaran bila pelanggan membayar.
4. Buat reminder/follow-up jika overdue.

Cara kerja di belakang layar:

- Penjualan kredit menghasilkan piutang.
- Pembayaran mengurangi outstanding melalui alokasi.
- Koreksi piutang harus melalui credit note/adjustment dan approval jika sensitif.

## 7. Kepala toko: monitoring cabang

Setiap hari kepala toko perlu:

1. Buka `/retail/dashboard`.
2. Cek omzet, transaksi, shift aktif, retur, void, dan stok.
3. Buka `/warehouse/stocks` dengan scope cabang untuk melihat saldo toko.
4. Buka `/retail/shifts` untuk memastikan shift tidak menggantung.
5. Buka `/retail/restock-requests` untuk status permintaan.
6. Buka `/reports/retail` untuk analisis periodik.
7. Buka `/reports/attendance` dan `/reports/shift-productivity` untuk karyawan.

## 8. Kehadiran karyawan toko

### 8.1 Check-in/out

1. Buka `/attendance/check`.
2. Klik check-in saat mulai kerja.
3. Klik check-out saat selesai.

### 8.2 Pengajuan izin/sakit/cuti

1. Buka `/attendance/requests`.
2. Pilih jenis pengajuan.
3. Isi tanggal, alasan, dan bukti jika ada.
4. Submit.

### 8.3 Koreksi absensi

1. Buka `/attendance/corrections`.
2. Ajukan koreksi jika lupa check-in/out.
3. Tunggu approval.

Cara kerja di belakang layar:

- Approval absensi menjaga data tidak diubah sepihak.
- Laporan produktivitas shift dapat menghubungkan data shift POS dan kehadiran.

## 9. Hal yang tidak boleh dilakukan toko

- Jangan transaksi POS tanpa shift aktif.
- Jangan memberi diskon di bawah minimum tanpa approval.
- Jangan menutup shift sebelum transaksi dan kas fisik dicek.
- Jangan menerima transfer jika barang fisik belum dihitung.
- Jangan mengubah qty penerimaan agar "cocok" padahal fisik kurang/rusak.
- Jangan void transaksi tanpa alasan jelas.
- Jangan memakai akun kasir milik orang lain.

## 10. Checklist harian kasir

- [ ] Check-in.
- [ ] Buka shift dengan modal awal benar.
- [ ] Transaksi POS dicatat semua.
- [ ] Hold lama dibersihkan.
- [ ] Retur/void dicatat dengan alasan.
- [ ] Kas fisik dihitung.
- [ ] Closing shift disubmit.
- [ ] Check-out.

## 11. Checklist harian kepala toko/supervisor

- [ ] Dashboard cabang diperiksa.
- [ ] Shift aktif dan closing dicek.
- [ ] Selisih kas ditindaklanjuti.
- [ ] Stok kritis dibuatkan restock request.
- [ ] Transfer masuk diterima sesuai fisik.
- [ ] Retur dan void direview.
- [ ] Piutang toko dan reminder dicek.
- [ ] Kehadiran karyawan dicek.
