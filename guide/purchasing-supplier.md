# Guide Book Purchasing & Supplier

Panduan ini terutama untuk role `purchasing`. Istilah Supplier pada menu berarti pengelolaan master dan performa supplier oleh tim internal; aplikasi saat ini belum menyediakan portal login khusus supplier eksternal.

## 1. Tujuan role Purchasing

Purchasing memastikan barang dibeli dari supplier yang benar, dalam jumlah dan harga yang disetujui, kemudian diterima dan dinilai secara transparan.

Tanggung jawab utama:

- menjaga data supplier akurat;
- meninjau kebutuhan pembelian;
- membuat Purchase Order (PO);
- memastikan harga, qty, tanggal, dan syarat pembayaran benar;
- memantau approval dan pengiriman PO;
- berkoordinasi dengan gudang saat Goods Receipt;
- menelusuri perubahan HPP;
- mengevaluasi performa supplier;
- menyelesaikan selisih atau retur melalui dokumen resmi.

## 2. Menu utama

| Menu | URL | Fungsi |
|---|---|---|
| Produk | `/admin/products` | Melihat SKU, satuan, minimum stok, dan data pembelian. |
| Supplier | `/admin/suppliers` | Master supplier, kontak, termin, import, dan export. |
| Saldo Stok | `/warehouse/stocks` | Melihat kebutuhan stok dan kondisi kritis. |
| Permintaan Pembelian | `/purchasing/requests` | Membuat/meninjau kebutuhan pembelian. |
| Purchase Order | `/purchasing/purchase-orders` | Membuat, mengirim, memantau, dan export PO. |
| Penerimaan Barang | `/warehouse/goods-receipts` | Memantau receipt dan hasil QC dari gudang. |
| Histori HPP | `/pricing/hpp-history` | Menelusuri perubahan HPP produk. |
| Laporan Supplier | `/reports/suppliers` | Performa pengiriman, kualitas, dan pembelian. |

Menu dan tombol yang terlihat tetap mengikuti permission akun.

## 3. Alur kerja purchasing dari awal sampai selesai

Urutan normal:

1. Periksa kebutuhan stok.
2. Validasi master produk dan supplier.
3. Buat atau review Permintaan Pembelian.
4. Tentukan supplier dan harga.
5. Buat PO.
6. Periksa item, pajak, diskon, ongkir, dan termin.
7. Submit/ajukan approval bila diperlukan.
8. Setelah approved, kirim PO ke supplier.
9. Pantau tanggal kedatangan.
10. Gudang membuat Goods Receipt dan QC.
11. Cocokkan qty ordered, received, rejected, dan damaged.
12. Periksa perubahan HPP dan performa supplier.
13. Tutup tindak lanjut untuk kekurangan atau retur.

Jangan melompati PO dengan meminta gudang menambah stok langsung, kecuali alur stok awal resmi saat implementasi.

## 4. Mengelola master supplier

### 4.1 Menambah supplier

1. Buka `/admin/suppliers`.
2. Klik **Tambah Supplier**.
3. Isi kode supplier yang unik.
4. Isi nama legal/usaha.
5. Isi PIC, nomor telepon/WhatsApp, email, dan alamat.
6. Isi termin pembayaran dalam hari.
7. Isi data pajak atau rekening bila field tersedia dan memang dibutuhkan.
8. Atur status aktif.
9. Simpan.
10. Buka detail supplier dan periksa kembali hasilnya.

Kriteria data yang baik:

- kode konsisten dan tidak memakai nama sementara;
- kontak dapat dihubungi;
- email tidak salah ketik;
- termin sesuai perjanjian;
- supplier duplikat tidak dibuat untuk cabang berbeda tanpa alasan bisnis.

### 4.2 Mengubah supplier

1. Cari supplier berdasarkan kode/nama.
2. Buka detail.
3. Klik edit jika mempunyai izin.
4. Ubah hanya data yang sudah dikonfirmasi.
5. Isi catatan/alasan bila tersedia.
6. Simpan dan periksa audit perubahan.

Jangan mengganti kode supplier yang sudah dipakai dokumen tanpa koordinasi karena kode menjadi referensi operasional.

### 4.3 Menonaktifkan supplier

1. Pastikan tidak ada PO aktif yang belum selesai.
2. Pastikan kewajiban pembayaran/retur sudah ditangani.
3. Ubah status menjadi tidak aktif.
4. Jangan menghapus histori transaksi supplier.

Supplier tidak aktif tidak dipakai untuk PO baru, tetapi histori lama tetap tersedia.

### 4.4 Import supplier

1. Buka halaman import supplier.
2. Unduh template resmi.
3. Isi kolom tanpa mengubah nama header.
4. Simpan dalam format yang didukung halaman.
5. Upload dan jalankan preview.
6. Periksa baris error satu per satu.
7. Koreksi file sumber.
8. Preview ulang sampai valid.
9. Commit sesuai tombol dan permission yang tersedia.
10. Cocokkan jumlah supplier hasil import.

## 5. Meninjau kebutuhan pembelian

### 5.1 Dari stok kritis

1. Buka `/warehouse/stocks`.
2. Filter lokasi gudang yang relevan.
3. Pilih status kritis/kosong bila tersedia.
4. Bandingkan On Hand, Available, minimum stock, dan safety stock.
5. Periksa pesanan yang sudah reserved.
6. Periksa PO yang masih outstanding agar tidak membeli dua kali.
7. Catat produk dan qty rekomendasi.

### 5.2 Dari Permintaan Pembelian

1. Buka `/purchasing/requests`.
2. Filter request baru/pending.
3. Buka detail request.
4. Periksa pemohon, lokasi, tanggal kebutuhan, produk, qty, dan alasan.
5. Periksa stok lokasi lain bila transfer internal lebih tepat.
6. Koreksi atau kembalikan request bila data belum lengkap.
7. Kelompokkan request yang dapat dibeli dari supplier yang sama.

## 6. Membuat Permintaan Pembelian

1. Buka `/purchasing/requests`.
2. Klik tambah request.
3. Pilih lokasi tujuan.
4. Isi tanggal kebutuhan.
5. Tambahkan produk.
6. Isi qty dalam satuan yang benar.
7. Isi alasan kebutuhan.
8. Simpan draft.
9. Periksa ulang item.
10. Submit sesuai alur yang tersedia.

Periksa sebelum submit:

- SKU dan nama produk benar;
- satuan tidak tertukar antara PCS, BOX, atau unit lain;
- qty realistis terhadap penjualan dan kapasitas;
- tidak ada request duplikat;
- tanggal kebutuhan memberi waktu supplier untuk mengirim.

## 7. Membuat Purchase Order

### 7.1 Persiapan

1. Pilih supplier aktif.
2. Pastikan produk dapat dipasok oleh supplier tersebut.
3. Konfirmasi harga terbaru.
4. Konfirmasi minimum order dan lead time.
5. Tentukan gudang tujuan.

### 7.2 Input PO

1. Buka `/purchasing/purchase-orders`.
2. Klik **Buat Purchase Order**.
3. Pilih supplier.
4. Pilih gudang tujuan.
5. Isi tanggal order dan estimasi kedatangan.
6. Tambahkan item produk.
7. Isi qty dan satuan.
8. Isi harga unit sebelum pajak sesuai ketentuan form.
9. Isi diskon, pajak, ongkir, atau biaya lain bila tersedia.
10. Isi termin pembayaran dan catatan pengiriman.
11. Simpan draft.

### 7.3 Pemeriksaan draft PO

Periksa minimal:

1. Supplier dan alamatnya.
2. Gudang penerima.
3. SKU, nama, satuan, dan conversion factor.
4. Qty setiap item.
5. Harga unit dan subtotal.
6. Diskon dan pajak.
7. Ongkir/biaya tambahan.
8. Grand total.
9. Tanggal kirim.
10. Termin pembayaran.
11. Referensi request pembelian bila ada.

### 7.4 Submit dan approval

1. Klik submit/ajukan approval.
2. Isi alasan jika diminta.
3. Pantau status PO.
4. Jika rejected, baca catatan approver.
5. Koreksi melalui aksi yang disediakan.
6. Submit ulang setelah valid.

Purchasing tidak boleh menganggap PO draft sebagai pesanan resmi.

### 7.5 Mengirim PO ke supplier

1. Pastikan status sudah approved.
2. Buka detail PO.
3. Unduh/cetak dokumen PO.
4. Kirim melalui channel resmi perusahaan.
5. Catat konfirmasi supplier.
6. Pastikan supplier menyetujui item, qty, harga, dan tanggal.
7. Perbarui status melalui aksi aplikasi bila tersedia.

## 8. Memantau PO outstanding

Lakukan setiap hari:

1. Filter PO yang belum selesai.
2. Urutkan berdasarkan estimasi kedatangan terdekat.
3. Periksa qty ordered dan received.
4. Hubungi supplier untuk PO terlambat.
5. Catat perubahan tanggal atau kendala pada catatan resmi.
6. Informasikan gudang tentang jadwal kedatangan.
7. Jangan membuat PO pengganti sebelum memastikan PO lama dibatalkan atau dikoreksi.

Status umum PO:

| Status | Arti |
|---|---|
| Draft | Belum diajukan dan belum boleh dikirim ke supplier. |
| Submitted/Pending Approval | Menunggu pemeriksaan. |
| Approved | Disetujui dan dapat dikirim. |
| Sent | Sudah dikirim kepada supplier. |
| Partially Received | Sebagian item sudah diterima. |
| Completed | Seluruh kewajiban penerimaan selesai. |
| Cancelled | Dibatalkan melalui alur resmi. |

## 9. Koordinasi Goods Receipt dan QC

Goods Receipt dibuat/diposting oleh user gudang yang berwenang. Purchasing bertugas menyiapkan referensi dan menindaklanjuti selisih.

### 9.1 Sebelum barang datang

1. Pastikan PO approved/sent.
2. Kirim jadwal ke gudang.
3. Pastikan gudang mengetahui supplier dan nomor PO.
4. Siapkan informasi batch/expired bila produk memerlukannya.

### 9.2 Saat barang diterima

Gudang memeriksa:

- qty fisik;
- kondisi accepted/rejected/damaged;
- batch dan tanggal kedaluwarsa;
- surat jalan;
- item tidak sesuai PO;
- biaya aktual yang diperbolehkan.

Purchasing tidak boleh meminta gudang mengubah hasil QC agar invoice supplier terlihat cocok.

### 9.3 Setelah receipt diposting

1. Buka receipt terkait.
2. Bandingkan ordered, previously received, received saat ini, dan outstanding.
3. Periksa item rejected/damaged.
4. Periksa status PO menjadi partial atau completed secara benar.
5. Catat klaim ke supplier bila ada selisih.
6. Periksa perubahan HPP.

## 10. Memahami HPP

HPP produk dapat berubah ketika penerimaan diposting. Secara umum sistem mempertimbangkan:

- nilai stok lama;
- qty stok lama;
- biaya barang masuk;
- ongkir/biaya tambahan yang dialokasikan;
- qty accepted yang menambah stok.

Langkah pemeriksaan:

1. Buka `/pricing/hpp-history`.
2. Filter produk dan tanggal receipt.
3. Periksa HPP sebelum dan sesudah.
4. Periksa qty sebelum, qty masuk, dan qty setelah.
5. Periksa incoming cost serta landed cost.
6. Buka receipt sumber.
7. Eskalasikan jika lonjakan tidak sesuai dokumen supplier.

Jangan mengubah harga jual langsung hanya karena HPP berubah. Harga jual mengikuti modul pricing dan approval.

## 11. Evaluasi performa supplier

1. Buka `/reports/suppliers`.
2. Pilih periode.
3. Filter supplier bila diperlukan.
4. Periksa ketepatan waktu pengiriman.
5. Periksa accepted, rejected, dan damaged.
6. Periksa frekuensi partial delivery.
7. Periksa tren harga dan HPP.
8. Bandingkan supplier untuk produk sejenis.
9. Catat tindakan perbaikan.

Indikator yang perlu diwaspadai:

- sering terlambat;
- qty fisik sering kurang;
- barang rusak/ditolak meningkat;
- harga berubah tanpa pemberitahuan;
- invoice tidak sesuai PO/receipt;
- respons klaim lambat.

## 12. Selisih, retur, dan koreksi

Jika barang kurang:

1. Pastikan receipt hanya mencatat qty aktual.
2. Biarkan PO partial bila sisa akan dikirim.
3. Catat komitmen pengiriman susulan.
4. Batalkan sisa hanya melalui aksi resmi bila tidak jadi dikirim.

Jika barang salah/rusak:

1. Pastikan QC mencatat rejected/damaged.
2. Dokumentasikan bukti.
3. Hubungi supplier.
4. Gunakan dokumen retur/klaim yang tersedia.
5. Jangan menghapus receipt posted.

Jika harga PO salah setelah final:

1. Jangan edit database.
2. Hentikan proses berikutnya bila aman.
3. Laporkan kepada approver.
4. Gunakan cancel/reversal/koreksi sesuai status dokumen.
5. Pastikan audit dan dokumen pengganti saling merujuk.

## 13. Hal yang tidak boleh dilakukan

- Mengirim PO yang masih draft.
- Membuat supplier duplikat agar terlihat sebagai supplier baru.
- Mengubah hasil QC tanpa bukti fisik.
- Mencatat qty diterima sebelum barang benar-benar datang.
- Menghapus PO/receipt final.
- Menggunakan float/perhitungan manual sebagai sumber final HPP.
- Membuat PO baru untuk menutupi kesalahan PO lama tanpa koreksi resmi.
- Membagikan data HPP sensitif kepada pihak yang tidak berwenang.
- Menggunakan akun user lain untuk approval.

## 14. Checklist harian Purchasing

1. Periksa stok kritis dan request baru.
2. Periksa PO menunggu approval.
3. Periksa PO approved yang belum dikirim.
4. Periksa jadwal kedatangan hari ini/besok.
5. Koordinasikan kedatangan dengan gudang.
6. Periksa receipt yang baru diposting.
7. Tindak lanjuti shortage/rejected/damaged.
8. Periksa PO overdue.
9. Catat komunikasi penting dengan supplier.

## 15. Checklist mingguan Purchasing

1. Review laporan performa supplier.
2. Review perubahan harga dan HPP terbesar.
3. Review PO partial yang terlalu lama.
4. Review supplier tidak aktif atau kontak kedaluwarsa.
5. Review kebutuhan pembelian berdasarkan tren stok.
6. Rekonsiliasi PO, receipt, dan tagihan bersama tim terkait.
7. Dokumentasikan isu supplier dan rencana tindak lanjut.
