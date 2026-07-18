# Guide Book Langganan/B2B

Panduan ini untuk `langganan_owner` dan `langganan_staff`. Portal Langganan/B2B dipakai pelanggan untuk melihat katalog, membuat order, checkout, memantau pengiriman, melihat invoice, mencatat pembayaran, melakukan reorder, mengubah profil usaha, dan mengajukan komplain.

## 1. Tujuan role Langganan/B2B

| Role | Fokus |
|---|---|
| `langganan_owner` | Mengelola profil usaha, alamat, order, invoice, pembayaran, dan user staf pelanggan. |
| `langganan_staff` | Membuat order, melihat katalog, memantau order, dan mengajukan komplain sesuai delegasi. |

## 2. Login portal

URL login pelanggan:

```text
/langganan/login
```

Akun demo:

| Nama | Email | Username | Password |
|---|---|---|---|
| Langganan / B2B | `langganan-b2b@gudangtoko.test` | `langganan-b2b` | `password` |
| Akun Pelanggan | `pelanggan@gudangtoko.test` | `pelanggan` | `password` |

Jika lupa password, buka:

```text
/langganan/forgot-password
```

Cara kerja di belakang layar:

- Login B2B tetap memakai tabel user yang sama, tetapi harus terhubung ke customer B2B.
- Middleware `b2b.customer` memastikan user hanya masuk ke portal jika punya customer terkait.
- User B2B hanya melihat data customer miliknya sendiri.

## 3. Menu utama B2B

| Menu | URL | Fungsi |
|---|---|---|
| Dashboard Langganan | `/langganan/dashboard` | Ringkasan order, invoice, pengiriman, dan status pelanggan. |
| Katalog | `/langganan/katalog` | Melihat produk yang bisa dipesan. |
| Detail Produk | `/langganan/katalog/{product}` | Detail produk, harga, dan stok tersedia. |
| Keranjang | `/langganan/keranjang` | Mengubah qty dan menghapus item. |
| Checkout | `/langganan/checkout` | Alamat, jadwal kirim, metode, catatan, dan submit order. |
| Reorder Cepat | `/langganan/reorder` | Memesan ulang dari order sebelumnya. |
| Riwayat Order | `/langganan/orders` | Daftar order B2B. |
| Detail Order | `/langganan/orders/{id}` | Status, item, shipment, invoice, dan aksi. |
| Tracking Shipment | `/langganan/shipments/{id}` | Melihat pengiriman dan konfirmasi terima. |
| Komplain | `/langganan/complaints` | Mengajukan dan melihat komplain. |
| Profil Usaha | `/langganan/profil` | Profil customer, alamat, dan data usaha. |
| Invoice | `/invoices` | Melihat invoice dan PDF. |
| Pembayaran | `/payments/create` | Upload/catat pembayaran jika diberi akses. |

## 4. Alur membuat order

### 4.1 Lihat katalog

1. Login ke `/langganan/login`.
2. Buka `/langganan/katalog`.
3. Gunakan filter/kategori jika tersedia.
4. Klik produk untuk melihat detail.
5. Periksa harga, minimum order, dan stok tersedia.

Cara kerja di belakang layar:

- Portal hanya menampilkan produk yang aktif dan boleh dijual ke channel B2B.
- Harga bisa berasal dari price ring, kategori pelanggan, atau harga khusus.
- Stok yang ditampilkan adalah available stock sesuai kebijakan, bukan seluruh stok internal.
- Pelanggan tidak bisa melihat HPP atau margin internal.

### 4.2 Tambahkan ke keranjang

1. Pilih produk.
2. Isi qty.
3. Klik tambah ke keranjang.
4. Buka `/langganan/keranjang`.
5. Ubah qty atau hapus item jika perlu.

Cara kerja di belakang layar:

- Keranjang belum melakukan reservasi stok permanen.
- Sistem tetap memvalidasi minimum qty dan ketersediaan saat checkout.
- Harga dapat dihitung ulang saat checkout agar mengikuti aturan aktif.

### 4.3 Checkout

1. Buka `/langganan/checkout`.
2. Pilih alamat pengiriman.
3. Isi tanggal pengiriman yang diminta.
4. Pilih metode pengiriman.
5. Pilih preferensi pembayaran, misalnya transfer atau kredit bila diizinkan.
6. Periksa subtotal, diskon, ongkir, pajak bila ada, dan total.
7. Centang persetujuan syarat.
8. Submit order.

Cara kerja di belakang layar:

- Sistem memvalidasi:
  - customer aktif,
  - akun user aktif,
  - stok available,
  - harga minimum,
  - minimum order,
  - limit kredit,
  - overdue/piutang bila ada,
  - alamat pengiriman.
- Jika order valid, sistem membuat B2B order.
- Stok dapat di-reserve sesuai state workflow.
- Jika limit kredit melewati batas, order bisa ditolak atau membutuhkan approval.

## 5. Memantau order

1. Buka `/langganan/orders`.
2. Klik order.
3. Periksa status dan timeline.
4. Periksa item, qty approved, qty reserved, qty shipped, dan invoice.

Status umum order B2B:

| Status | Arti |
|---|---|
| Draft/Cart | Masih di keranjang atau belum submit. |
| Submitted | Order sudah dikirim ke internal. |
| Approved | Order disetujui. |
| Reserved | Stok dicadangkan. |
| Packed | Barang disiapkan gudang. |
| Shipped | Barang dikirim. |
| Invoice Ready/Issued | Invoice siap/terbit. |
| Completed | Order selesai. |
| Cancelled/Rejected | Order batal atau ditolak. |

Cara kerja di belakang layar:

- Gudang memproses order di `/warehouse/b2b-orders`.
- Reservation membuat available stock turun agar tidak dijanjikan ke order lain.
- Shipment dibuat saat barang dikirim.
- Invoice diterbitkan dari order sesuai aturan pembayaran.

## 6. Membatalkan order

1. Buka detail order.
2. Jika status masih memungkinkan, klik cancel.
3. Isi alasan.
4. Submit.

Cara kerja di belakang layar:

- Jika stok sudah di-reserve tetapi belum dikirim, reservation dilepas.
- Jika barang sudah dikirim, pembatalan biasa tidak boleh; gunakan retur/komplain.
- Semua pembatalan dicatat ke histori.

## 7. Tracking pengiriman dan bukti terima

1. Buka detail order.
2. Klik shipment atau buka `/langganan/shipments/{id}`.
3. Periksa kurir, resi, jadwal, status, dan item.
4. Saat barang diterima, klik konfirmasi terima jika tersedia.
5. Isi qty diterima atau catatan jika ada masalah.

Cara kerja di belakang layar:

- Shipment dikelola tim gudang/internal.
- Proof of delivery mencatat bukti terima.
- Jika qty kurang/rusak, sistem perlu komplain/retur/discrepancy.

## 8. Invoice dan pembayaran

### 8.1 Melihat invoice

1. Buka `/invoices`.
2. Pilih invoice.
3. Periksa nomor, tanggal, due date, item, total, paid amount, dan outstanding.
4. Unduh PDF jika perlu.

### 8.2 Mencatat pembayaran

1. Buka `/payments/create`.
2. Pilih customer/invoice jika tersedia.
3. Isi metode pembayaran, nominal, tanggal, bank, nomor referensi, nama pembayar.
4. Upload bukti bila tersedia.
5. Submit.

Cara kerja di belakang layar:

- Pembayaran awal bisa berstatus pending verification.
- Tim internal memverifikasi pembayaran.
- Setelah verified, payment dialokasikan ke invoice/piutang.
- Outstanding invoice berkurang setelah alokasi sah.

## 9. Reorder cepat

1. Buka `/langganan/reorder`.
2. Pilih order lama atau produk yang sering dibeli.
3. Ubah qty bila perlu.
4. Submit ke keranjang atau order.

Cara kerja di belakang layar:

- Sistem memakai histori order sebagai template.
- Harga dan stok tetap dihitung ulang.
- Produk nonaktif atau stok tidak cukup dapat ditolak.

## 10. Profil usaha

1. Buka `/langganan/profil`.
2. Periksa nama usaha, owner, PIC, WhatsApp, email, alamat, dan data lain.
3. `langganan_owner` dapat memperbarui data yang diizinkan.
4. Simpan.

Cara kerja di belakang layar:

- Profil usaha terhubung ke master customer internal.
- Beberapa data sensitif seperti limit kredit/verifikasi mungkin hanya bisa diubah internal.
- Perubahan penting masuk audit.

## 11. Komplain

1. Buka `/langganan/complaints`.
2. Pilih order/shipment/item jika ada.
3. Pilih tipe komplain.
4. Isi qty, pesan, dan solusi yang diminta.
5. Upload bukti bila ada.
6. Submit.

Cara kerja di belakang layar:

- Komplain menjadi record yang bisa diproses internal.
- Solusi bisa berupa follow-up, retur, replacement, refund, atau credit note sesuai kebijakan.
- Komplain tidak otomatis mengubah invoice/stok sebelum settlement disetujui.

## 12. Hak dan batasan B2B

B2B boleh:

- melihat katalog yang diizinkan,
- membuat order,
- melihat order sendiri,
- melihat invoice sendiri,
- mencatat pembayaran,
- melihat pengiriman sendiri,
- mengonfirmasi penerimaan,
- membuat komplain,
- mengubah profil usaha sesuai izin.

B2B tidak boleh:

- melihat HPP/margin internal,
- melihat stok semua gudang secara detail,
- melihat order pelanggan lain,
- mengubah harga,
- mengubah status order internal,
- memaksa checkout melebihi limit kredit tanpa approval,
- menghapus invoice/payment.

## 13. Checklist pelanggan B2B

- [ ] Login memakai akun resmi.
- [ ] Profil dan alamat pengiriman benar.
- [ ] Katalog dicek sebelum order.
- [ ] Qty dan harga diperiksa di keranjang.
- [ ] Checkout memakai alamat dan tanggal yang benar.
- [ ] Order dipantau sampai shipped.
- [ ] Barang diterima dihitung fisik.
- [ ] Invoice dan due date dipantau.
- [ ] Pembayaran dicatat dengan bukti.
- [ ] Komplain dibuat segera jika ada masalah.
