# Dokumentasi Lengkap Workflow, Fitur, dan Fungsi Aplikasi GudangToko

Dokumen ini adalah panduan referensi tunggal yang mendokumentasikan **seluruh arsitektur, peran pengguna, alur kerja bisnis (*end-to-end workflows*), katalog modul dan fitur, serta siklus hidup data (*state machines*)** pada aplikasi **GudangToko**.

---

## DAFTAR ISI

1. [Identitas & Paradigma Arsitektur](#1-identitas--paradigma-arsitektur)
2. [Daftar Peran (Roles) & Matriks Hak Akses](#2-daftar-peran-roles--matriks-hak-akses)
3. [Alur Kerja Bisnis Utama (End-to-End Workflows)](#3-alur-kerja-bisnis-utama-end-to-end-workflows)
   - [Workflow 1: Pengadaan Barang & Penerimaan Supplier (Inbound)](#workflow-1-pengadaan-barang--penerimaan-supplier-inbound)
   - [Workflow 2: Distribusi Stok Antar Cabang & Pindah Rak Internal](#workflow-2-distribusi-stok-antar-cabang--pindah-rak-internal)
   - [Workflow 3: Penjualan Kasir POS & Siklus Shift Kasir (Retail)](#workflow-3-penjualan-kasir-pos--siklus-shift-kasir-retail)
   - [Workflow 4: Penjualan B2B & Portal Pelanggan Langganan](#workflow-4-penjualan-b2b--portal-pelanggan-langganan)
   - [Workflow 5: Pengelolaan Piutang, Invoice & Penagihan (Receivables)](#workflow-5-pengelolaan-piutang-invoice--penagihan-receivables)
   - [Workflow 6: Pengelolaan Retur Barang & Quality Control](#workflow-6-pengelolaan-retur-barang--quality-control)
   - [Workflow 7: Audit Fisik Persediaan & Koreksi Stok (Stock Opname)](#workflow-7-audit-fisik-persediaan--koreksi-stok-stock-opname)
   - [Workflow 8: Penetapan Harga, Margin Simulator & Ring Harga 1-3](#workflow-8-penetapan-harga-margin-simulator--ring-harga-1-3)
   - [Workflow 9: Manajemen Kehadiran & Shift Kerja Karyawan](#workflow-9-manajemen-kehadiran--shift-kerja-karyawan)
   - [Workflow 10: Skema Target & Bonus Karyawan Multi-Role](#workflow-10-skema-target--bonus-karyawan-multi-role)
   - [Workflow 11: Pengawasan Sistem, Audit Keamanan & Notifikasi](#workflow-11-pengawasan-sistem-audit-keamanan--notifikasi)
4. [Katalog Lengkap Modul, Fitur & Fungsinya](#4-katalog-lengkap-modul-fitur--fungsinya)
   - [Modul Dashboard & Owner](#modul-dashboard--owner)
   - [Modul Target & Bonus](#modul-target--bonus)
   - [Modul Administrasi & Pengguna](#modul-administrasi--pengguna)
   - [Modul Administrasi Sistem & Keamanan](#modul-administrasi-sistem--keamanan)
   - [Modul Master Produk](#modul-master-produk)
   - [Modul Relasi Bisnis (Supplier & Pelanggan)](#modul-relasi-bisnis-supplier--pelanggan)
   - [Modul Sales](#modul-sales)
   - [Modul Gudang & Stok](#modul-gudang--stok)
   - [Modul Pembelian (Purchasing)](#modul-pembelian-purchasing)
   - [Modul Harga & Margin (Pricing)](#modul-harga--margin-pricing)
   - [Modul Retur (Returns)](#modul-retur-returns)
   - [Modul Toko Internal (Retail & POS)](#modul-toko-internal-retail--pos)
   - [Modul Kehadiran (HR & Attendance)](#modul-kehadiran-hr--attendance)
   - [Modul Kontrol, Approval & Audit](#modul-kontrol-approval--audit)
   - [Modul Invoice & Pembayaran (Piutang)](#modul-invoice--pembayaran-piutang)
   - [Modul Keuangan & Pajak Tahunan](#modul-keuangan--pajak-tahunan)
   - [Modul Laporan & Ekspor](#modul-laporan--ekspor)
   - [Modul Portal Langganan B2B](#modul-portal-langganan-b2b)
5. [State Machines & Siklus Hidup Transaksi](#5-state-machines--siklus-hidup-transaksi)
6. [Prinsip Integritas Data & Guardrails Bisnis](#6-prinsip-integritas-data--guardrails-bisnis)

---

## 1. IDENTITAS & PARADIGMA ARSITEKTUR

- **Nama Aplikasi**: GudangToko (`gudangtoko/application`)
- **Tipe Arsitektur**: *Modular Monolith* berbasis **Laravel 12**
- **Teknologi Utama**: PHP 8.3+, MySQL / SQLite in-memory, Blade, Bootstrap 5, Metronic 8 Theme, Vite, Tailwind CSS v4, jQuery, DataTables, Select2, SweetAlert2.

### Prinsip Utama Sistem:
1. **Warehouse-First & Isolasi Saldo Kerja (`WorkLocation`)**:
   - Setiap saldo persediaan fisik diikat ke `work_location_id`.
   - Gudang (`Warehouse`) dan Toko Cabang (`Branch`) memiliki saldo terpisah dan tidak saling campur aduk tanpa mutasi resmi.
2. **Fleksibilitas Operasional (Mendukung Operasional Murni Toko / Single-Store)**:
   - Sistem mendukung operasional mandiri toko tanpa gudang pusat wajib. Lokasi rak/bin (`WarehouseLocation`), penerimaan barang dari supplier, dan opname stok dapat langsung terikat pada `Branch`.
3. **Penilaian Persediaan FIFO Berlapis (*Auto FIFO Cost Layering*)**:
   - Saldo barang masuk membentuk layer batch harga modal (`cost_price` / HPP).
   - Pengeluaran stok (penjualan POS, transfer keluar, pemakaian) otomatis memotong saldo layer tertua (*FIFO*).
4. **Kekekalan Dokumen Final (*Immutability & No Hard Delete*)**:
   - Transaksi yang telah selesai (`completed`, `posted`, `closed`, `settled`) tidak boleh diedit atau dihapus secara sembarangan.
   - Koreksi dilakukan melalui dokumen pembalik resmi (*void*, *reversal receipt*, *credit note*, atau *retur*).
5. **Presisi Finansial & Kuantitas**:
   - Nominal uang, harga jual, dan HPP menggunakan `decimal(18,2)`.
   - Kuantitas stok dan rasio konversi satuan menggunakan `decimal(18,4)`.
   - Perhitungan matematis diproses via helper `App\Support\Decimal` untuk mencegah galat floating-point.
6. **Snapshot Transaksi Historis**:
   - Setiap transaksi final menyimpan *snapshot* nama produk, SKU, satuan, HPP, dan harga jual pada saat transaksi terjadi. Perubahan data master tidak akan merusak histori keuangan masa lalu.

---

## 2. DAFTAR PERAN (ROLES) & MATRIKS HAK AKSES

| Role | Kode | Fokus Utama & Wewenang |
|---|---|---|
| **Super Admin** | `super_admin` | Akses teknis penuh ke seluruh modul, manajemen pengguna, role, permission Spatie, konfigurasi nomor dokumen, audit log, backup, dan maintenance sistem. |
| **Owner / Approver** | `owner_approver` | Eksekutif & Pemilik Usaha. Memantau dashboard keuangan, omzet, margin kotor sensitif, laporan harian, serta memberikan persetujuan (*approval*) untuk PO bernilai tinggi, void kasir, diskon di bawah minimum, dan penghapusan piutang. |
| **Kepala Gudang** | `kepala_gudang` | Penanggung jawab operasional gudang, tata letak rak/bin, penerimaan barang supplier, transfer stok antar cabang, stok opname, dan approval insentif/bonus staf gudang. |
| **Staff Gudang / Picker** | `staff_gudang`, `picker_packer` | Pelaksana lapangan gudang: *receiving* barang fisik, penataan rak (*put-away*), *picking & packing* pesanan B2B dan transfer cabang, serta penghitungan fisik opname. |
| **Purchasing** | `purchasing` | Pengadaan barang: mengelola daftar supplier, memproses pengajuan belanja (*purchase request*), menerbitkan PO (*purchase order*), dan memantau performa supplier. |
| **Kepala Toko** | `kepala_toko` | Penanggung jawab operasional toko internal/cabang: approval pembukaan/penutupan shift kasir, pembelian darurat (*emergency purchase*), verifikasi transfer masuk toko, dan approval target bonus kasir. |
| **Kasir** | `kasir` | Pelaksana penjualan retail: membuka shift, memindai barcode, melayani pembayaran POS (tunai, transfer, QRIS, tempo pelanggan langganan), menahan transaksi (*hold*), dan menutup shift harian. |
| **Sales** | `sales` | Pengelola penjualan B2B: mendaftarkan pelanggan, membuat pesanan B2B (*sales order*), memantau ketersediaan stok, dan mengejar target penjualan. |
| **Langganan B2B** | `langganan_owner`, `langganan_pic` | Pelanggan grosir/B2B yang bertransaksi via portal: melihat katalog harga khusus, memesan barang mandiri, memantau riwayat transaksi, mengunduh invoice, dan mengajukan komplain. |
| **Admin Master Data** | `admin_user`, `admin_config` | Mengelola data master produk, kategori, merek, satuan UOM, supplier, pelanggan, dan integrasi WhatsApp. |

---

## 3. ALUR KERJA BISNIS UTAMA (END-TO-END WORKFLOWS)

```
       +-------------------------------------------------------------------+
       |               PENGADAAN BARANG (PURCHASING & INBOUND)            |
       |  Purchase Request -> Purchase Order -> Goods Receipt (3-Way Match)|
       +---------------------------------+---------------------------------+
                                         |
                       [Barang Masuk ke Saldo Gudang/Toko]
                       [FIFO Cost Layering / StockBatch]
                                         |
         +-------------------------------+-------------------------------+
         |                                                               |
         v                                                               v
+-----------------------------------+           +-----------------------------------+
|      DISTRIBUSI & TRANSFER        |           |    PENJUALAN B2B & LANGGANAN      |
|  - Transfer Antar Cabang (2-Step) |           |  - Order Mandiri Portal / Sales   |
|  - Pindah Rak Internal (Instant)  |           |  - Reservasi Stok Gudang (24 Jam) |
|  - Permintaan Restok Cabang       |           |  - Terbit Invoice & Approval Kredit|
+----------------+------------------+           |  - Picking, Packing & Surat Jalan |
                 |                              +-----------------+-----------------+
                 v                                                |
+-----------------------------------+                             v
|      PENJUALAN KASIR POS TOKO     |           +-----------------------------------+
|  - Buka Shift (Modal + Absensi)   |           |    PIUTANG, INVOICE & PENAGIHAN   |
|  - Scan Barcode & Price Resolver  |           |  - Buku Pembantu Piutang          |
|  - Validasi Kredit / Min. Order   |           |  - Notifikasi Reminder WhatsApp   |
|  - Selesai (FIFO Mutasi Kasir)    |           |  - Pembayaran Piutang & Re-limit  |
|  - Tutup Shift & Rekonsiliasi Kas |           +-----------------------------------+
+-----------------------------------+
```

---

### Workflow 1: Pengadaan Barang & Penerimaan Supplier (Inbound)

Tujuan: Memastikan barang dibeli sesuai kebutuhan, harga ternegosiasi dengan tepat, dan kuantitas fisik yang tiba sesuai pesanan (*3-Way Matching*).

```
[Staf Toko/Gudang]      [Purchasing]          [Supplier]             [Staf Gudang]
       |                      |                    |                       |
       |-- 1. Restock Req. -->|                    |                       |
       |                      |-- 2. Buat PO ----->|                       |
       |                      |   (Draft -> Appr)  |                       |
       |                      |                    |-- 3. Kirim Barang --->|
       |                      |                    |                       |-- 4. Goods Receipt
       |                      |                    |                       |   (QC: Baik/Rusak)
       |                      |                    |                       |-- 5. Posting Mutasi
       |                      |<-- 6. Terbit Tagihan Hutang (AP) ----------|   (Auto FIFO Layer)
```

1. **Pengajuan Belanja (*Purchase Request*)**:
   - Staf toko atau gudang mengajukan kebutuhan barang ketika stok mendekati batas minimum (*reorder point*).
2. **Penerbitan Pesanan Pembelian (*Purchase Order / PO*)**:
   - Divisi Purchasing membuat draft PO ke Supplier terpilih, menentukan satuan beli, harga per unit, pajak, dan tanggal estimasi kedatangan.
   - Jika nilai PO di atas ambang wewenang, PO masuk ke kotak approval Owner / Kepala Bagian.
   - Status: `draft` &rarr; `submitted` &rarr; `approved` &rarr; `sent`.
3. **Penerimaan Barang Fisik (*Goods Receipt / GR*)**:
   - Saat kiriman tiba di gudang atau toko, staf penerima membuka form Goods Receipt berbasis referensi PO.
   - Dilakukan verifikasi fisik: kuantitas diterima (*accepted*), kuantitas rusak (*damaged*), dan kuantitas ditolak (*rejected*).
   - Penentuan penempatan rak/bin simpan (*put-away*).
4. **Posting Mutasi & Pembentukan Layer HPP**:
   - Saat status GR di-*posting*:
     - Saldo fisik bertambah pada tabel `stocks` di rak bersangkutan.
     - Terbentuk layer baru pada tabel `stock_batches` mencatat tanggal masuk, kuantitas awal, sisa kuantitas, dan HPP aktual.
     - Tercatat jejak audit mutasi persediaan pada `stock_mutations` bertipe `goods_receipt`.
     - Kuantitas PO ter-update secara otomatis (`partially_received` atau `fully_received`).

---

### Workflow 2: Distribusi Stok Antar Cabang & Pindah Rak Internal

Tujuan: Memindahkan stok secara aman, cepat, dan akuntabel tanpa hambatan birokrasi berlebih.

#### A. Transfer Antar Cabang (*Fast 2-Step Logistics*)
1. **Tahap 1: Kirim Sekarang (Dalam Perjalanan)**:
   - Staf gudang/toko asal membuat transfer, memilih cabang tujuan, dan menambahkan produk yang ingin dikirim.
   - Tombol **"Kirim Sekarang (Dalam Perjalanan)"** langsung memotong stok dari lokasi asal secara FIFO, membentuk mutasi `transfer_out`, dan status dokumen menjadi `shipped` (*In-Transit*).
   - Akun user otomatis tercatat sebagai **Penanggung Jawab Pengirim** (`shipper_by`).
   - Tersedia opsi *Simpan Draft* jika barang fisik masih dikemas di area staging.
2. **Tahap 2: Penerimaan di Cabang Tujuan**:
   - Pada halaman Transfer Antar Cabang, staf di lokasi tujuan membuka **Tab "Penerimaan Masuk"**.
   - Menekan tombol **"Terima Barang"** &rarr; Form penerimaan menyediakan tombol cepat **"1-Klik Terima Semua"**.
   - Jika terdapat barang hilang atau rusak di perjalanan, staf menginput rincian selisih (*discrepancy*).
   - Saat disimpan, stok bertambah di cabang tujuan, status menjadi `received` / `completed`, dan akun user tercatat sebagai **Penanggung Jawab Penerima** (`receiver_by`).

#### B. Pindah Rak Internal (*Instant Atomic Move*)
- Khusus perpindahan barang antar rak/bin dalam satu lokasi (misal dari Rak Gudang Belakang ke Rak Display Depan).
- Bersifat **seketika (*instant*)**: tidak memiliki status *in-transit* dan tidak memerlukan persetujuan.
- Mendukung pemindahan banyak item sekaligus dalam satu formulir.
- Begitu form disimpan, sistem secara atomik memindahkan saldo dari bin asal ke bin tujuan dalam transaksi database yang terlindungi.

#### C. Permintaan Restok Cabang (*Restock Request*)
- Toko internal meminta pasokan barang ke gudang pusat.
- Permintaan dapat dikonversi menjadi dokumen Transfer Antar Cabang secara langsung dengan 1 klik.

---

### Workflow 3: Penjualan Kasir POS & Siklus Shift Kasir (Retail)

Tujuan: Melayani pelanggan toko secara instan, mencatat pendapatan secara akurat, dan mengamankan kas fisik toko.

```
[Buka Shift Kasir] ──> [Scan Produk & Barcode] ──> [Price Resolver & Margin]
  (Modal Awal +          (Shortcut F2, F4, F6)        (Deteksi Ring / Override)
   Absensi Masuk)                                              │
                                                               v
[Tutup Shift Harian] <── [Cetak Struk Thermal] <── [Validasi Pembayaran (F8)]
  (Rekonsiliasi Kas,       (Potong FIFO Stok,         (Tunai/QRIS/Bank/Tempo:
   Alasan Selisih)          Snapshot HPP)              Cek Limit & Min. Order)
```

1. **Pembukaan Shift Kasir (*Opening Shift*)**:
   - Prasyarat: Kasir wajib memiliki riwayat absensi masuk kerja pada hari tersebut.
   - Kasir menginput modal awal kas di laci kasir (`opening_cash`). Status shift: `OPEN`.
2. **Transaksi Penjualan di Meja Kasir**:
   - **Pencarian Produk**: Menggunakan barcode scanner atau tombol shortcut `F2`.
   - **Pemilihan Satuan (Multi-UOM)**: Otomatis menghitung rasio konversi unit (misal: Pcs, Dus, Pak).
   - **Price Resolver Dinamis**: Menentukan harga jual otomatis berdasarkan profil pelanggan (Umum, Retail, Grosir, Reseller) dan tingkatan Ring Harga (Ring 1, 2, 3).
   - **Tahan Transaksi (*Hold Cart - F6*)**: Kasir dapat menunda antrean pelanggan saat ini untuk melayani pelanggan berikutnya, dan melanjutkan keranjang hold sewaktu-waktu.
   - **Validasi Pembayaran (*F8*)**:
     - *Pelanggan Umum*: Hanya diizinkan membayar Tunai, Transfer Bank, atau QRIS. Opsi Tempo/Piutang otomatis disembunyikan.
     - *Pelanggan Langganan*: Dapat memilih metode **Tempo/Piutang**.
       - Sistem memvalidasi apakah nilai transaksi memenuhi **Minimum Order**.
       - Sistem memeriksa sisa **Limit Kredit** (`credit_limit - receivable_balance`).
       - Jatuh tempo otomatis mengikuti kesepakatan termin pelanggan (misal: 14 hari, 30 hari), atau dapat disesuaikan (*custom due date*).
3. **Penyelesaian Transaksi & Mutasi Persediaan**:
   - Sistem secara atomik memotong stok etalase toko menggunakan alur FIFO (*Stock Layer*).
   - Mencatat *snapshot* HPP aktual dan harga jual pada detail penjualan.
   - Mencetak struk belanja kasir (*Thermal Print 58mm / 80mm*).
4. **Penutupan Shift Kasir (*Closing Shift*)**:
   - Kasir menghitung fisik uang kertas dan koin di laci kasir (*Cash Denomination Count*).
   - Sistem membandingkan saldo kas fisik aktual dengan ekspektasi sistem:
     $$\text{Selisih} = \text{Kas Fisik} - (\text{Kas Awal} + \text{Penjualan Tunai} - \text{Pengeluaran Kas} - \text{Refund})$$
   - Jika ada selisih (kurang/lebih), kasir wajib mengetik alasan selisih.
   - Shift masuk ke status `closing_submitted` untuk diverifikasi dan disetujui Supervisor / Kepala Toko menjadi `CLOSED`.
5. **Pembelian Darurat Toko (*Emergency Purchase*)**:
   - Jika stok toko habis mendadak dan ada permintaan konsumen, toko dapat membeli barang secara cepat dari toko terdekat.
   - Stok masuk ke *pool darurat* dan dapat langsung dijual via POS atau dikonversi menjadi stok reguler toko.

---

### Workflow 4: Penjualan B2B & Portal Pelanggan Langganan

Tujuan: Memfasilitasi pesanan volume besar untuk pelanggan grosir/B2B secara mandiri dan transparan.

1. **Akses Portal Pelanggan**:
   - Pelanggan login ke Portal B2B khusus (`/portal` atau menu Portal Langganan).
   - Pelanggan melihat katalog produk dengan harga khusus grosir mereka, sisa limit kredit, dan tagihan aktif.
2. **Pemesanan Mandiri (*Self-Service Order*)**:
   - Pelanggan memilih barang ke keranjang atau menggunakan fitur **Reorder Cepat** berbasis pesanan sebelumnya.
   - Pelanggan melakukan checkout dengan memilih alamat kirim dan metode pembayaran (Tempo Kredit atau Transfer Dimuka).
   - Status pesanan: `pending_confirmation`.
3. **Validasi Gudang & Reservasi Stok (*Stock Reservation*)**:
   - Admin B2B / Staf Gudang memeriksa pesanan.
   - Saat pesanan diverifikasi, sistem mengunci stok di gudang (*reserved stock*) selama 24 jam agar tidak terjual ke kasir POS atau pesanan lain.
   - Kuantitas fisik tetap di gudang, tetapi `available_quantity` berkurang.
4. **Penerbitan Invoice & Pembayaran**:
   - Jika pelanggan menggunakan kredit tempo dan limit kredit mencukupi: pesanan langsung disetujui.
   - Jika prabayar (prepaid): invoice terbit dan pesanan menunggu konfirmasi pembayaran.
5. **Fulfillment Gudang (Picking, Packing & Shipment)**:
   - Terbit dokumen Surat Jalan Pengiriman (`Shipment`).
   - Staf gudang melakukan *picking* barang dari rak bin, *packing*, dan mencetak surat jalan / label resi kurir.
   - Saat pengiriman diberangkatkan (*dispatched*), sistem melepaskan status reservasi dan mencatat mutasi pengeluaran stok fisik (`issue`).
6. **Penerimaan Barang oleh Pelanggan**:
   - Pelanggan menerima barang fisik dan menekan konfirmasi penerimaan di portal.
   - Jika ada barang rusak atau kurang, pelanggan dapat mengajukan dokumen komplain/retur langsung dari portal.

---

### Workflow 5: Pengelolaan Piutang, Invoice & Penagihan (Receivables)

Tujuan: Mengontrol perputaran modal usaha, menjaga arus kas, dan mencegah piutang macet (*bad debt*).

1. **Penerbitan Faktur (*Invoice Generation*)**:
   - Invoice terbit otomatis dari transaksi penjualan POS bertempo atau pesanan B2B yang telah dikirim.
   - Invoice memuat nomor faktur resmi, daftar item, nilai pajak, syarat pembayaran (*payment terms*), dan tanggal jatuh tempo.
2. **Pemantauan Umur Piutang (*Aging Analysis*)**:
   - Dashboard Piutang mengelompokkan saldo piutang berdasarkan usia jatuh tempo:
     - Belum Jatuh Tempo (*Current*)
     - Terlambat 1 – 30 hari
     - Terlambat 31 – 60 hari
     - Terlambat 61 – 90 hari
     - Macet (> 90 hari)
3. **Pengingat Tagihan Otomatis (*Automated Reminders*)**:
   - Sistem mengirim pesan reminder tagihan otomatis via WhatsApp Gateway dan Email:
     - H-3 sebelum jatuh tempo (pengingat ramah).
     - Hari H jatuh tempo.
     - H+7 dan H+14 lewat jatuh tempo (peringatan penangguhan kredit).
4. **Pencatatan Pembayaran Piutang**:
   - Admin membuka form Pembayaran Piutang (`/receivables/payments/create`).
   - Memilih pelanggan: sistem memuat seluruh daftar faktur yang belum lunas.
   - Satu bukti transfer pembayaran dapat dialokasikan ke beberapa faktur sekaligus (*multi-invoice settlement*).
   - Setelah disimpan, saldo piutang pelanggan berkurang dan **plafon kredit pelanggan otomatis pulih kembali**.

---

### Workflow 6: Pengelolaan Retur Barang & Quality Control

Tujuan: Menangani pengembalian barang dari pelanggan atau ke supplier secara tertib, terinspeksi mutunya, dan benar secara pembukuan finansial.

1. **Pengajuan Retur (*Return Request*)**:
   - Berasal dari Penjualan POS Toko, Pesanan B2B, atau Retur Pembelian ke Supplier.
   - Wajib mencantumkan nomor referensi transaksi asal dan alasan retur.
2. **Pemeriksaan Kualitas (*Quality Control Inspection*)**:
   - Staf gudang memeriksa kondisi fisik barang retur dan membaginya ke dalam 3 kategori:
     - **Good Condition (Bagus)**: Barang masih utuh dan layak jual kembali &rarr; Masuk ke saldo stok jual (`return_in`).
     - **Damaged (Rusak)**: Barang cacat/pecah &rarr; Masuk ke penampungan barang rusak (`damaged`).
     - **Rejected (Ditolak)**: Klaim retur tidak sah atau bukan barang milik toko &rarr; Dikembalikan ke pelanggan.
3. **Resolusi Finansial Retur**:
   - Tergantung jenis transaksi asal, resolusi dapat berupa:
     - **Pengembalian Dana Kas (*Cash Refund*)**: Mengurangi kas shift aktif kasir.
     - **Nota Kredit (*Credit Note*)**: Mengurangi saldo piutang aktif pelanggan pada invoice bersangkutan.
     - **Penggantian Barang (*Replacement*)**: Menerbitkan pengiriman barang baru.
4. **Batas Persetujuan Kerugian**:
   - Jika estimasi nilai kerugian barang retur melampaui Rp 1.000.000, dokumen retur wajib disetujui oleh Owner / Kepala Bagian sebelum diselesaikan (*settled*).

---

### Workflow 7: Audit Fisik Persediaan & Koreksi Stok (Stock Opname)

Tujuan: Menjaga kesesuaian antara catatan saldo di komputer (*system stock*) dengan jumlah fisik barang di rak (*physical stock*).

1. **Jadwal & Pembekuan Lokasi (*Stock Freeze*)**:
   - Kepala Gudang membuat jadwal opname (bisa *Cycle Count* per rak atau *Full Count* per lokasi).
   - Rak atau zona yang sedang diopname dibekukan dari mutasi penjualan dan transfer agar angka tidak bergerak selama proses hitung.
2. **Penghitungan Fisik (*Physical Counting*)**:
   - Staf gudang mencatat jumlah fisik barang di lapangan (bisa berupa *blind count* tanpa melihat angka sistem).
3. **Rekonsiliasi & Analisis Varian (*Variance Reconciliation*)**:
   - Sistem menghitung selisih:
     $$\text{Selisih Qty} = \text{Fisik} - \text{Sistem}$$
   - Sistem menampilkan nilai selisih nominal berdasarkan HPP barang.
   - Staf wajib mengisi alasan untuk setiap barang yang berselisih (misal: barang pecah, salah hitung sebelumnya, selisih barcode).
4. **Approval & Posting Penyesuaian**:
   - Dokumen opname diajukan ke Kepala Gudang / Owner.
   - Setelah disetujui, sistem melakukan *posting* mutasi penyesuaian (*stock adjustment*), memperbarui saldo pada `stocks`, dan menyinkronkan layer FIFO.

---

### Workflow 8: Penetapan Harga, Margin Simulator & Ring Harga 1-3

Tujuan: Mengamankan batas margin keuntungan usaha, mencegah kasir salah input harga rugi, dan menerapkan segmentasi harga grosir.

```
Input: Produk, Qty, Pelanggan, Cabang
                  │
                  ▼
        [1. Hitung HPP Dasar]
                  │
                  ▼
   [2. Cek Aturan Khusus Pelanggan] (Customer Special Price)
                  │
                  ▼
    [3. Cek Kategori Harga & Ring]  (Ring 1: Retail, Ring 2: Reseller, Ring 3: Grosir)
                  │
                  ▼
       [4. Cek Margin Minimum]      (Margin Guardrail)
                  │
         +--------+--------+
         |                 |
(Harga Aman)       (Di Bawah Margin / Overpricing)
         |                 |
         v                 v
[Langsung Terpasang] [Wajib Approval Pimpinan]
```

1. **Tingkatan Ring Harga (Tiered Pricing)**:
   - **Ring 1 (Retail / Umum)**: Harga jual eceran standar untuk konsumen reguler.
   - **Ring 2 (Semi-Grosir / Reseller)**: Harga diskon khusus dengan kuantitas belanja tertentu atau pelanggan terdaftar mitra.
   - **Ring 3 (Grosir Besar / Proyek)**: Harga terendah dengan kuantitas partai besar untuk pelanggan B2B.
   - *(Catatan: Ring 4 telah dihapus dari sistem untuk simplifikasi).*
2. **Mesin Penentu Harga (*PriceResolverService*)**:
   - Menggunakan 5 urutan prioritas:
     1. Harga Khusus Pelanggan (*Customer Special Price*).
     2. Aturan Harga Produk per Kategori Harga Pelanggan (*Price Category*).
     3. Aturan Harga Khusus Cabang Toko.
     4. Aturan Harga Global Saluran Penjualan (*Channel*).
     5. *Fallback*: HPP Produk + Margin Minimum yang diwajibkan.
3. **Pencegahan Anomali & Wajib Approval**:
   - Sistem otomatis mengunci transaksi dan meminta approval jika:
     - Harga jual diskon berada di bawah harga minimum modal (`below_minimum`).
     - Harga jual melampaui toleransi wajar (`overpricing`).
     - Persentase diskon melebihi pagu diskon maksimum aturan.

---

### Workflow 9: Manajemen Kehadiran & Shift Kerja Karyawan

Tujuan: Menegakkan kedisiplinan kerja, mencatat kehadiran secara transparan, dan menghubungkan kehadiran dengan prasyarat operasional POS serta evaluasi bonus.

1. **Konfigurasi Shift & Jadwal Kerja**:
   - Admin mengatur master jam kerja shift (Pagi, Siang, Malam, Regular) dan menugaskan jadwal mingguan karyawan.
2. **Pencatatan Kehadiran (Check-In & Check-Out)**:
   - Karyawan melakukan absensi mandiri via gawai atau komputer toko:
     - Verifikasi geolokasi GPS (radius lokasi kerja).
     - Swafoto kamera wajah (*photo selfie*).
   - Sistem mencatat waktu tepat dan mendeteksi keterlambatan (*late arrival*) atau pulang lebih awal.
3. **Pengajuan Izin, Cuti & Sakit**:
   - Karyawan dapat mengajukan izin dengan mengunggah bukti surat dokter/keterangan.
   - Atasan langsung melakukan approval / penolakan izin.
4. **Koreksi Absensi (*Attendance Correction*)**:
   - Jika karyawan lupa absen karena kendala teknis atau dinas luar, karyawan mengajukan form koreksi absensi yang wajib disetujui atasan.
5. **Integrasi Operasional**:
   - Absensi masuk berstatus valid adalah **prasyarat mutlak** bagi kasir untuk membuka shift POS.
   - Rekap absensi bersih (tanpa status pending) adalah syarat verifikasi sebelum bonus bulanan staf dihitung.

---

### Workflow 10: Skema Target & Bonus Karyawan Multi-Role

Tujuan: Memberikan penghargaan berbasis kinerja yang objektif, transparan, dan terotomatisasi bagi seluruh lini operasional.

1. **Cakupan 5 Peran Karyawan**:
   - `staff_gudang`, `picker_packer`, `staf_toko`, `kasir`, dan `sales`.
2. **Integritas Program Target**:
   - Total bobot metrik KPI wajib tepat **100%**.
   - Program wajib diaktifkan sebelum bulan target berjalan (beku / *immutable*).
3. **Pembagian Bobot Metrik**:
   - **Gudang & Picker**: Pekerjaan gudang tim (40%), akurasi transfer tim (20%), absensi individu (20%), checklist individu (20%).
   - **Toko & Kasir**: Penjualan POS bersih tim (50%), akurasi selisih kas shift tim (10%), absensi individu (20%), checklist individu (20%).
   - **Sales**: Penjualan B2B bersih individu (80%), absensi individu (10%), checklist individu (10%).
4. **Kaidah Perhitungan & Cap Skor**:
   - Skor dihitung adil: $\text{Skor} = \min(100\%, \frac{\text{Aktual}}{\text{Target}} \times 100\%)$.
   - Bonus tidak dapat melebihi batas maksimum program.
5. **Approval & Pembayaran Bonus**:
   - Dihitung setelah bulan periode selesai dan seluruh absensi telah diverifikasi.
   - Disetujui oleh Kepala Bagian terkait (`kepala_gudang` atau `kepala_toko`).
   - Pembayaran dicatat dengan nomor bukti transaksi transfer dan tidak dapat diubah historinya.

---

### Workflow 11: Pengawasan Sistem, Audit Keamanan & Notifikasi

Tujuan: Memastikan operasional aman dari kebocoran data, kecurangan (*fraud*), dan downtime teknis.

1. **Pencegahan Self-Approval (*Segregation of Duties*)**:
   - Pengguna dilarang menyetujui transaksi yang dibuat oleh dirinya sendiri (misal: PO, void kasir, izin cuti, diskon di bawah minimum).
2. **Ledger Audit Log Lengkap**:
   - Setiap aksi `create`, `update`, `delete`, `status_change` tercatat otomatis pada tabel audit log lengkap dengan nilai sebelum (*old values*), nilai sesudah (*new values*), User ID, IP Address, dan User Agent.
3. **Dashboard Deteksi Anomali**:
   - Algoritma mendeteksi anomali secara berkala: selisih kas berulang pada kasir tertentu, pembatalan struk mencurigakan, diskon tidak lazim, atau mutasi stok tanpa dokumen referensi.
4. **Sistem Notifikasi Multichannel**:
   - **WhatsApp Gateway**: Pengingat tagihan ke nomor WhatsApp pelanggan, alert selisih kas besar ke Owner, notifikasi PO siap dikirim ke Supplier.
   - **Email & In-App Notification**: Notifikasi permintaan approval dan ringkasan laporan harian.

---

## 4. KATALOG LENGKAP MODUL, FITUR & FUNGSINYA

Berikut adalah daftar komprehensif seluruh modul dan fitur yang terdapat pada antarmuka aplikasi GudangToko:

### Modul Dashboard & Owner

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Utama** | `dashboard` | `dashboard.view` | Ringkasan operasional harian: grafik penjualan mingguan, status shift kasir, ringkasan stok menipis, dan pengumuman sistem. |
| **Dashboard Owner** | `owner.dashboard` | `reports.view` | Dashboard eksekutif pemilik usaha: memantau omzet harian/bulanan, laba kotor (*gross margin*), total piutang tertunggak, arus kas masuk/keluar, dan tren performa toko. |

### Modul Target & Bonus

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Target & Bonus Staf** | `staff-bonuses.index` | `staff_bonuses.view_own` / `team` / `all` | Mengelola program KPI target bulanan, evaluasi pencapaian metrik tim & individu untuk 5 role karyawan, pengajuan approval bonus, serta pencatatan bukti transfer pembayaran bonus. |

### Modul Administrasi & Pengguna

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Manajemen Pengguna** | `admin.users.index` | `admin.users.view` | Mengelola akun karyawan/pengguna: tambah user, ubah password, assign role, penugasan lokasi kerja (*WorkLocation assignment*), dan status aktif/non-aktif. |
| **Manajemen Role** | `admin.roles.index` | `admin.roles.view` | Mengelola grup peran pengguna (Super Admin, Kasir, Kepala Toko, dll.) dan memetakan hak akses permission ke dalam peran. |
| **Manajemen Permission** | `admin.permissions.index` | `admin.permissions.view` | Mengelola daftar hak akses granular Spatie Permission untuk mengunci tombol, menu, dan endpoint API. |

### Modul Administrasi Sistem & Keamanan

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Gudang (Warehouse)** | `admin.warehouses.index` | `admin.warehouses.view` | Master data gudang fisik: alamat, penanggung jawab, kapasitas, dan status aktif. |
| **Cabang / Toko (Branch)** | `admin.branches.index` | `admin.branches.view` | Master data toko internal: nama toko, alamat, relasi ke gudang penyedia utama (`primary_warehouse_id`), dan kontak. |
| **Pengaturan Umum** | `admin.settings.general.edit` | `admin.settings.view` | Konfigurasi identitas perusahaan, logo usaha, mata uang, zona waktu, dan kebijakan operasional default. |
| **Nomor Dokumen** | `admin.settings.document-numbers.index` | `admin.settings.view` | Mengatur format penomoran otomatis dokumen (PO, Invoice, POS, Transfer, GR) menggunakan prefix, tahun, bulan, dan nomor urut. |
| **Akses Koreksi Stok** | `admin.settings.stock-adjustment-access.index` | `stock_direct_adjustments.manage_access` | Mengatur perizinan khusus bagi akun yang diizinkan melakukan penyesuaian stok langsung tanpa opname formal. |
| **Koneksi WhatsApp** | `admin.whatsapp.index` | `whatsapp_connection.view` | Konfigurasi koneksi WhatsApp Gateway (status koneksi QR Code, nomor pengirim, log pengiriman pesan). |
| **Kesehatan Sistem** | `admin.system.health` | `system.health.view` | Memeriksa performa server, penggunaan RAM, status database, kapasitas disk storage, dan antrean queue worker. |
| **Backup Sistem** | `admin.system.backups.index` | `system.operations.view` | Membuat cadangan database dan berkas media secara terjadwal atau manual, serta memulihkan data (*restore*). |
| **Log & Queue** | `admin.system.logs.index` | `system.operations.view` | Memantau catatan error `laravel.log`, memonitor job antrean latar belakang (*queued jobs*), dan membersihkan cache sistem. |
| **Maintenance & Go-Live** | `admin.system.maintenance.index` | `system.operations.view` | Mengaktifkan mode perbaikan (*maintenance mode*), mempersiapkan migrasi data awal sebelum aplikasi go-live. |

### Modul Master Produk

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Kategori Produk** | `admin.product-categories.index` | `products.view` | Mengelola hierarki kategori produk (induk dan sub-kategori). |
| **Merek Produk** | `admin.product-brands.index` | `products.view` | Mengelola daftar merek / brand produk. |
| **Satuan & UOM** | `admin.units.index` | `products.view` | Mengelola satuan dasar dan satuan turunan (Pcs, Dus, Pak, Kg) beserta faktor konversi pengalinya. |
| **Daftar Produk** | `admin.products.index` | `products.view` | Master katalog barang: SKU, barcode, nama barang, satuan dasar, harga pokok standar, foto barang, dan batas minimum stok (*reorder point*). |
| **Cetak Barcode / QR** | `admin.products.barcodes.index` | `products.print_barcode` | Menghasilkan dan mencetak stiker label barcode produk (format Code128 / QR Code) untuk ditempel pada rak atau kemasan barang. |
| **Import Produk** | `admin.products.import.index` | `products.import` | Mengunggah data master produk dalam jumlah besar menggunakan berkas template Excel / CSV. |

### Modul Relasi Bisnis (Supplier & Pelanggan)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Daftar Supplier** | `admin.suppliers.index` | `suppliers.view` | Mengelola data vendor/pemasok: nama PT/toko, kontak sales, termin pembayaran standar, nomor rekening, dan alamat. |
| **Pelanggan & B2B** | `admin.customers.index` | `customers.view` | Mengelola mitra pelanggan dan langganan B2B: profil usaha, plafon limit kredit, termin jatuh tempo piutang, batas minimum order, serta akun login portal. |

### Modul Sales

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Sales** | `sales.dashboard` | `sales.dashboard.view` | Memantau pencapaian omzet penjualan pribadi, komisi berjalan, dan target bulanan. |
| **Customer Saya** | `sales.customers.index` | `sales.customers.view_own` | Melihat daftar pelanggan yang ditugaskan (*assigned*) ke sales bersangkutan beserta histori ordernya. |
| **Buat Order B2B** | `sales.orders.create` | `sales.orders.create` | Membantu pelanggan membuatkan pesanan penjualan B2B langsung dari lapangan. |
| **Order Saya** | `sales.orders.index` | `sales.orders.view_own` | Memantau status pemrosesan pesanan pelanggan: menunggu persetujuan, terbit tagihan, atau sedang dikirim. |
| **Cek Stok Sales** | `sales.stocks.index` | `sales.stock.view` | Melihat ketersediaan stok riil di gudang secara cepat tanpa membuka menu gudang. |
| **Target & Bonus Sales** | `sales.targets.index` | `sales.targets.view_own` | Melihat target kuantitas/omzet sales dan histori penerimaan insentif. |
| **Performance Sales** | `sales.performance` | `sales.performance.view` | Analisis peringkat pencapaian seluruh staf sales (leaderboard & conversion rate). |
| **Assignment Customer** | `sales.assignments.index` | `sales.customers.assign` | Menugaskan akun pelanggan tertentu kepada staf sales tertentu untuk pembinaan relasi. |

### Modul Gudang & Stok

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Gudang** | `warehouse.dashboard` | `stock.view` | Ringkasan stok masuk hari ini, daftar picking pending, pesanan B2B yang siap kirim, dan barang yang menipis. |
| **Zona, Rak, dan Bin** | `warehouse.locations.index` | `stock.view` | Tata letak fisik penyimpanan gudang atau toko: mengelola nama zona, nomor rak, dan bin penyimpanan barang. |
| **Saldo Stok** | `warehouse.stocks.index` | `stock.view` | Menampilkan kuantitas stok riil (`on_hand`, `reserved`, `damaged`, `available`) per lokasi rak, histori Kartu Stok (*Stock Card*), rincian Batch FIFO, dan HPP rata-rata. |
| **Import Stok Awal** | `warehouse.opening-stocks.import.index` | `opening_stocks.import` | Fitur pengisian saldo persediaan awal saat pertama kali sistem dipasang. |
| **Penerimaan Barang (GR)**| `warehouse.goods-receipts.index` | `goods_receipts.view` | Mencatat kedatangan barang dari PO supplier, verifikasi fisik, penentuan rak, dan posting layer HPP FIFO. |
| **Order B2B Gudang** | `warehouse.b2b-orders.index` | `b2b_orders.view` | Memproses verifikasi stok dan reservasi barang untuk pesanan B2B yang masuk dari portal atau sales. |
| **Pengiriman B2B (Shipments)**| `shipments.index` | `shipments.view` | Mengelola dokumen surat jalan pengiriman, instruksi pengambilan barang (*picking list*), packing, dan input resi ekspedisi. |
| **Transfer Antar Cabang** | `warehouse.stock-transfers.index`| `stock_transfers.view` | Alur pengiriman logistik antar cabang dalam 3 tab: Tab Kirim Transfer (Keluar), Tab Penerimaan Masuk (1-klik terima), dan Tab Permintaan Restok. Dilengkapi aksi langsung "Kirim Sekarang" tanpa birokrasi approval. |
| **Pindah Rak Internal** | `warehouse.location-transfers.index`| `stock.view` | Formulir cepat perpindahan barang antar rak/bin dalam satu toko atau gudang yang sama. Bersifat seketika (*instant move*) dan mendukung multi-produk. |
| **Stok Opname** | `warehouse.stock-opnames.index` | `stock_adjustments.view` | Mengelola jadwal opname fisik, pencatatan hasil hitung, rekonsiliasi varian selisih, dan posting penyesuaian stok. |
| **Barang Rusak & Loss** | `warehouse.losses.index` | `losses.view` | Mencatat dan mengisolasi barang yang rusak, bocor, atau kadaluarsa agar tidak bisa dijual. |

### Modul Pembelian (Purchasing)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Pengajuan Belanja** | `purchasing.requests.index` | `purchase_orders.view` | Mengumpulkan permohonan pengadaan barang dari cabang atau gudang sebelum dibuatkan PO resmi. |
| **Pesanan Supplier (PO)** | `purchasing.purchase-orders.index`| `purchase_orders.view` | Menerbitkan dokumen PO ke supplier, menentukan rincian item, harga beli, PPN, dan memantau status pemenuhan barang. |
| **Performa Supplier** | `reports.suppliers.index` | `reports.view` | Mengevaluasi pemasok berdasarkan ketepatan waktu pengiriman (*on-time delivery*) dan kualitas barang. |
| **Laporan Loss Pembelian**| `reports.losses.index` | `losses.view` | Memantau selisih barang yang rusak atau hilang saat diterima dari pemasok. |

### Modul Harga & Margin (Pricing)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Aturan Harga (Margin Rules)**| `pricing.rules.index` | `prices.view` | Mengatur parameter margin keuntungan kotor per kategori produk, saluran penjualan (Retail vs B2B), atau cabang. |
| **Harga Produk** | `pricing.product-prices.index` | `prices.view` | Daftar matriks harga jual produk per tingkatan **Ring 1 (Retail)**, **Ring 2 (Semi-Grosir)**, dan **Ring 3 (Grosir)**. Tabel diurutkan memprioritaskan produk yang belum lengkap ring harganya. |
| **Histori & Simulasi Harga**| `pricing.history.index` | `prices.view` | Melihat riwayat perubahan harga jual serta fitur **Simulator Margin 7 Langkah** untuk menguji harga aman sebelum diberlakukan. |

### Modul Retur (Returns)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Daftar Retur** | `returns.index` | `returns.view` | Mengelola seluruh pengembalian barang (Retur Kasir POS, Retur Pesanan B2B, dan Retur ke Supplier), mencatat hasil QC barang fisik, dan memproses solusi finansial (Refund, Nota Kredit, Ganti Barang). |

### Modul Toko Internal (Retail & POS)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Cabang** | `retail.dashboard` | `cash_shifts.view` | Ringkasan transaksi toko aktif: kasir yang sedang bertugas, omzet penjualan hari ini, dan barang paling laris. |
| **Kasir POS** | `retail.pos.index` | `pos.view` | Antarmuka kasir penjualan retail: pemindaian barcode cepat (F2), tahan transaksi (F6), pembayaran (F8), validasi pelanggan umum vs tempo, input custom due date, validasi min order dan limit kredit, serta cetak struk thermal. |
| **Riwayat Transaksi** | `retail.sales.index` | `pos.view` | Menampilkan seluruh riwayat transaksi penjualan kasir, detail item belanja, cetak ulang struk, dan proses void struk. |
| **Shift Kasir** | `retail.shifts.current` | `cash_shifts.view` | Pengelolaan shift kasir harian: modal kas awal, pencatatan pengeluaran operasional kas kecil (*petty cash*), dan penutupan shift rekonsiliasi kas. |
| **Etalase Produk Toko** | `retail.storefront.index` | `retail.catalog.view` | Katalog produk khusus toko internal dan form pengajuan restok etalase ke gudang toko. |
| **Pembelian Darurat** | `retail.emergency.index` | `emergency_purchases.view` | Mengelola pembelian darurat barang saat toko kehabisan stok, mencatat pengeluaran kas, dan mengelola alokasi stok pool darurat. |

### Modul Kehadiran (HR & Attendance)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Absensi & Tim** | `attendance.dashboard` | `attendance.check` / `view` | Papan absensi harian: melakukan check-in / check-out mandiri dengan validasi lokasi GPS & swafoto, serta memantau status kehadiran staf hari ini. |
| **Jadwal Mingguan** | `attendance.schedules.index` | `attendance.view` | Melihat dan mengatur jadwal penugasan shift kerja karyawan selama sepekan. |
| **Pengajuan Izin** | `attendance.requests.index` | `attendance.check` / `approve`| Pengajuan dan persetujuan izin tidak masuk kerja, cuti tahunan, atau surat keterangan sakit. |
| **Koreksi Absensi** | `attendance.corrections.index` | `attendance.update` / `approve`| Mengajukan perbaikan jam absensi jika terjadi kendala teknis atau lupa check-out, disertai alasan dan persetujuan atasan. |
| **Pengaturan Karyawan** | `attendance.employees.index` | `attendance.update` | Data kepegawaian karyawan: NIK, tanggal bergabung, kuota cuti tahunan, dan penugasan jadwal kerja default. |
| **Pengaturan Shift Kerja**| `attendance.work-shifts.index` | `attendance.update` | Mengatur master jam kerja (jam masuk, batas toleransi terlambat, jam pulang, dan istirahat). |

### Modul Kontrol, Approval & Audit

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Kotak Masuk Approval** | `approvals.index` | `approvals.view` | Pusat persetujuan terpadu bagi Pimpinan / Owner untuk memproses dokumen yang membutuhkan otorisasi (PO bernilai besar, diskon khusus, void kasir, izin kerja). |
| **Audit Log** | `audit-logs.index` | `audit.view` | Rekaman jejak audit sistem: mencatat siapa melakukan perubahan apa, pada jam berapa, dari IP mana, beserta nilai sebelum dan sesudah perubahan. |
| **Dashboard Anomali** | `audit.anomalies.index` | `audit.view` | Mendeteksi potensi penyimpangan: selisih kas berulang, transaksi di luar jam operasional, diskon kasir mencurigakan, atau lonjakan retur. |
| **Log Login & Keamanan** | `audit.security.index` | `audit.view` | Memantau upaya login gagal, device fingerprinting, akun terkunci, dan aktivitas keamanan sistem. |
| **Konfigurasi Notifikasi** | `admin.notifications.*` | `notifications.view` | Mengatur saluran notifikasi (WhatsApp, Email), template pesan, jadwal otomatis pengingat tagihan, dan daftar nomor penerima alert. |

### Modul Invoice & Pembayaran (Piutang)

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Piutang** | `receivables.dashboard` | `receivables.view` | Ringkasan total piutang usaha, grafik aging analysis piutang, dan rasio perputaran piutang (*Days Sales Outstanding*). |
| **Daftar Piutang Pelanggan**| `receivables.index` | `receivables.view` | Buku pembantu piutang per pelanggan, melihat saldo outstanding, histori pembayaran, dan mutasi debit/kredit piutang. |
| **Daftar Invoice** | `invoices.index` | `invoices.view` | Mengelola dokumen faktur tagihan penjualan B2B dan POS tempo, mencetak PDF invoice, dan mengirim invoice via WhatsApp. |
| **Pembayaran Piutang** | `receivables.payments.create`| `receivables.pay` | Formulir pencatatan pembayaran piutang: mendukung alokasi satu bukti transfer bank untuk melunasi banyak invoice sekaligus secara proporsional. |
| **Reminder Tagihan** | `receivables.reminders` | `receivables.view` | Memantau antrean dan mengirimkan pengingat tagihan jatuh tempo ke pelanggan via WhatsApp secara massal atau manual. |
| **Limit Kredit Pelanggan** | `receivables.credit-limits` | `receivables.manage_limits` | Mengelola dan mengevaluasi kelayakan plafon kredit pelanggan serta persetujuan kenaikan limit. |
| **Piutang Toko Retail** | `retail.receivables.index` | `receivables.view` | Khusus memantau piutang bon belanja pelanggan langganan lokal di tingkat toko internal. |

### Modul Keuangan & Pajak Tahunan

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Keuangan & Pajak** | `tax.index` | `finance_annual.view` | Rekapitulasi transaksi kena pajak (PPN Masukan dari PO dan PPN Keluaran dari Invoice), serta kalkulasi estimasi pajak tahunan badan usaha. |

### Modul Laporan & Ekspor

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Laporan Harian Owner** | `reports.daily.index` | `reports.view` | Laporan ringkasan harian komprehensif: penjualan, margin kotor, arus kas kasir, penerimaan barang, dan piutang baru dalam 1 halaman ringkas. |
| **Laporan Gudang** | `reports.warehouse.index` | `stock.view` | Laporan mutasi barang, perputaran stok (*inventory turnover*), barang lambat laku (*slow moving / dead stock*), dan nilai aset persediaan. |
| **Laporan Toko Internal** | `reports.retail.index` | `cash_shifts.view` | Laporan performa penjualan per kasir, jam sibuk transaksi (*peak hours*), dan histori selisih kas shift. |
| **Laporan B2B** | `reports.b2b.index` | `b2b_orders.view` | Laporan penjualan wholesale B2B, tingkat pemenuhan pesanan (*order fulfillment rate*), dan pelanggan paling aktif. |
| **Laporan Harga & Margin** | `reports.pricing.index` | `prices.view` | Analisis margin kotor per produk dan evaluasi efektivitas diskon promosi. |
| **Laporan Supplier** | `reports.suppliers.index` | `suppliers.view` | Rekapitulasi pembelian per supplier dan skor evaluasi ketepatan pengiriman barang. |
| **Laporan Kehadiran** | `reports.attendance.index` | `attendance.view` | Rekapitulasi presensi bulanan: total kehadiran, jumlah keterlambatan, cuti, sakit, dan lembur per karyawan. |
| **Laporan Piutang** | `reports.receivables.index` | `receivables.view` | Laporan penagihan piutang dan analisa umur piutang (*aging report*). |
| **Laporan Audit & Alert** | `reports.audit-notifications.index`| `audit.view` | Rekapitulasi log insiden keamanan dan notifikasi sistem yang terkirim. |
| **Pusat Ekspor Dokumen** | `reports.exports.index` | `reports.export` | Mengunduh seluruh laporan data ke dalam format Excel (.xlsx), CSV, atau PDF untuk kebutuhan audit eksternal dan akuntansi. |

### Modul Portal Langganan B2B

| Menu / Halaman | Rute Web | Hak Akses (Permission) | Fungsi & Kegunaan |
|---|---|---|---|
| **Dashboard Langganan** | `langganan.dashboard` | `dashboard.view` (B2B) | Papan informasi pelanggan B2B: status pesanan aktif, sisa limit kredit, dan informasi promo terbaru. |
| **Katalog Produk Portal** | `langganan.katalog.index` | `b2b_orders.view` | Menjelajahi katalog produk dengan harga grosir khusus yang berlaku untuk pelanggan tersebut. |
| **Keranjang Belanja** | `langganan.keranjang.index`| `b2b_orders.create` | Menyusun daftar produk pesanan, memilih varian satuan (Dus/Pak), dan melihat subtotal belanja. |
| **Checkout Mandiri** | `langganan.checkout.show` | `b2b_orders.create` | Memilih alamat pengiriman, menentukan metode pembayaran (Tempo Kredit atau Transfer Dimuka), dan mengirim pesanan. |
| **Fitur Reorder Cepat** | `langganan.reorder.index` | `b2b_orders.create` | Mengulang pesanan rutin berkala hanya dengan 1 kali klik tanpa memilih barang satu per satu dari nol. |
| **Riwayat Pesanan B2B** | `langganan.orders.index` | `b2b_orders.view` | Memantau status pengerjaan pesanan: diverifikasi gudang, dipacking, dikirim kurir, atau telah selesai. |
| **Invoice Mandiri** | `invoices.index` (B2B) | `receivables.view` | Mengunduh faktur tagihan resmi (PDF) secara mandiri untuk arsip pembukuan pelanggan. |
| **Pengajuan Komplain** | `langganan.complaints.index`| `complaints.create` | Mengajukan klaim komplain barang rusak atau selisih kuantitas dengan mengunggah foto bukti fisik. |
| **Profil Usaha** | `langganan.profil.edit` | `customers.view_own` | Memperbarui kontak PIC usaha, nomor telepon WhatsApp, dan alamat penagihan/pengiriman. |

---

## 5. STATE MACHINES & SIKLUS HIDUP TRANSAKSI

Seluruh status dokumen dikelola menggunakan PHP *Backed Enum* dengan aturan transisi ketat yang divalidasi oleh *Service Layer*.

### 1. Purchase Order (PO)
```text
draft ──> submitted ──> approved ──> sent ──> partially_received ──> fully_received ──> completed
  │           │            │
  └───> cancelled <────────┘ (bila belum diterima)
```

### 2. Goods Receipt (Penerimaan Barang Supplier)
```text
draft ──> inspecting ──> submitted ──> approved ──> posted (Menambah Stok & Layer FIFO)
  │                                                    │
  └───> cancelled (sebelum posting)                    └───> reversed (via Dokumen Pembalik)
```

### 3. Transfer Stok Antar Cabang (Alur Cepat)
```text
draft ──> shipped (Kirim Langsung / In-Transit) ──> received / completed (Terima di Tujuan)
  │
  └───> cancelled
```

### 4. Penjualan Kasir POS (POS Sale)
```text
draft ──> priced ──> payment_pending ──> completed (Stok Terpotong Atomik)
  │                                          │
  └───> cancelled (batal keranjang)          ├───> voided (Dibatalkan via Void Kasir)
                                             └───> returned (Diretur via PosReturn)
```

### 5. Shift Kasir (Cash Shift)
```text
opened (Input Modal Awal) ──> closing_submitted (Hitung Fisik) ──> verified ──> closed
                                       │
                                       └───> reopened (Re-hitung bila ada selisih janggal)
```

### 6. Pesanan B2B (B2B Order)
```text
draft ──> submitted ──> warehouse_validation ──> reserved ──> confirmed ──> invoiced ──> shipped ──> delivered ──> completed
  │            │               │                    │
  └───> cancelled <────────────┴────────────────────┘ (Reservasi Dilepas Tepat Sekali)
```

### 7. Surat Jalan Pengiriman (Shipment)
```text
draft ──> picking ──> packed ──> dispatched (In-Transit) ──> delivered ──> completed
```

### 8. Retur Barang (Return)
```text
draft ──> submitted ──> inspected (QC Baik/Rusak) ──> approved ──> resolution_pending ──> completed
```

### 9. Stok Opname
```text
draft ──> scheduled ──> frozen ──> counting ──> reconciled ──> approval_pending ──> approved ──> posted (Stok Tersinkron)
```

---

## 6. PRINSIP INTEGRITAS DATA & GUARDRAILS BISNIS

1. **Larangan Stok Negatif**:
   - Saldo persediaan (`quantity_on_hand`, `available_quantity`) **tidak pernah boleh bernilai negatif**. Setiap mutasi keluar memvalidasi ketersediaan stok fisik sebelum eksekusi.
2. **Pusat Mutasi Tunggal**:
   - Seluruh perubahan saldo wajib melewati `InventoryService` dan menghasilkan rekaman pada tabel `stock_mutations` (bersifat *append-only* tanpa edit/hapus).
3. **Pemisahan Wewenang (*Segregation of Duties*)**:
   - User dilarang menyetujui transaksi yang dibuatnya sendiri (*no self-approval*).
4. **Perlindungan Batas Kredit (*Credit Limit Guard*)**:
   - Penjualan tempo di POS atau checkout B2B wajib ditolak otomatis jika nilai belanja melebihi sisa plafon kredit aktif pelanggan atau terdapat tagihan tertunggak yang melampaui batas hari toleransi.
5. **Kerahasiaan Nilai Margin**:
   - Data HPP modal dan persentase keuntungan kotor adalah informasi rahasia yang hanya dapat diakses oleh role yang memiliki permission `margins.view_sensitive` (Owner, Kepala Gudang, Purchasing). Kasir dan pelanggan tidak dapat melihat nilai HPP barang.

