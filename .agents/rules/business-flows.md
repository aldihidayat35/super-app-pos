---
trigger: model_decision
---

# Business Flow & Domain Guardrails — GudangToko

Dokumen ini adalah aturan domain dan guardrail alur bisnis (*business flow*) wajib untuk seluruh pengembangan, perbaikan bug, dan pembuatan fitur di aplikasi **GudangToko**.

---

## 1. Arsitektur Warehouse-First & Isolasi Saldo

1. **Gudang sebagai Sumber Kebenaran Persediaan**:
   - Saldo stok fisik, HPP (*cost price*), harga dasar, dan distribusi utama berpusat pada gudang (`Warehouse`).
   - Cabang toko internal (`Branch`) dan akun pelanggan langganan (`Customer`) mengambil ketersediaan stok dari gudang dengan alur dan aturan yang terisolasi.
2. **Isolasi Saldo Berbasis Lokasi Kerja**:
   - Setiap transaksi dan saldo stok terikat pada `work_location_id`.
   - Cabang/toko memiliki saldo stok terpisah dan tidak berbagi baris saldo stok secara langsung dengan gudang.
   - Pengecekan otorisasi wajib selalu memverifikasi lokasi kerja pengguna via `$request->user()->permittedWorkLocationIds()` atau `canAccessWorkLocation()`.
3. **Hirarki Fleksibel Lokasi Penyimpanan (Rak/Bin)**:
   - Rak atau lokasi penyimpanan (`WarehouseLocation`) bisa berelasi langsung ke `Warehouse` (via `warehouse_id`) ATAU langsung ke `Branch` (via `branch_id`).
   - DILARANG mengasumsikan lokasi rak hanya terikat pada gudang. Saat memfilter rak berdasarkan lokasi kerja (`WorkLocation`), WAJIB menggunakan *query scope* `WarehouseLocation::forWorkLocation($id)` atau memanggil fungsi pendeteksi milik model `$bin->workLocationId()`.

---

## 2. Integritas Saldo Persediaan & Mutasi (*Inventory Invariants*)

1. **Formula Saldo Stok**:
   ```text
   available = on_hand - reserved - damaged
   ```
2. **Larangan Stok Negatif**:
   - ❌ Saldo stok (`quantity_on_hand`, `quantity_reserved`, `quantity_damaged`, `available_quantity`) **tidak pernah boleh bernilai negatif**.
   - Setiap mutasi pengurangan stok wajib memvalidasi kecukupan stok sebelum saldo dikurangi.
3. **Pusat Mutasi Persediaan**:
   - Seluruh perubahan stok wajib dieksekusi melalui [`InventoryService`](file:///c:/laragon/www/super-app-pos/app/Services/Inventory/InventoryService.php).
   - ❌ Dilarang memperbarui kolom kuantitas pada tabel `stocks` secara langsung di controller, model, atau view.
4. **Transaksi Atomik & Locking**:
   - Semua mutasi persediaan harus dibungkus dalam `DB::transaction()` dan menggunakan row-locking (`lockForUpdate()`) untuk mencegah *race condition* dan *double issue*.
5. **Ledger Mutasi Append-Only**:
   - Setiap perpindahan persediaan wajib menghasilkan satu atau lebih rekaman di tabel `stock_mutations`.
   - Data mutasi mencatat delta kuantitas, saldo sebelum, saldo sesudah, arah (`direction`), aktor, jenis mutasi, referensi dokumen, dan kunci idempotensi (`idempotency_key`).
   - ❌ Dilarang keras mengedit atau menghapus (*delete/update*) rekaman pada `stock_mutations`.

---

## 3. Presisi Finansial, Kuantitas & Snapshot Transaksi

1. **Presisi Finansial**:
   - Seluruh nilai nominal uang, harga jual, biaya, dan HPP wajib menggunakan format `decimal(18,2)`.
   - ❌ Dilarang menggunakan tipe data `float` untuk nominal uang atau perhitungan HPP.
2. **Presisi Kuantitas**:
   - Kuantitas stok dan faktor konversi satuan wajib menggunakan format `decimal(18,4)`.
   - Gunakan helper `qty()` atau class helper `App\Support\Decimal` untuk operasi matematika presisi tinggi.
3. **Snapshot Transaksi Wajib**:
   - Setiap dokumen transaksi final (POS sale item, order item, invoice item, transfer item, goods receipt item) **wajib menyimpan snapshot**:
     - `product_name_snapshot`
     - `product_sku_snapshot`
     - `unit_name_snapshot`
     - `conversion_factor_snapshot`
     - `hpp_snapshot` / `unit_cost_snapshot`
     - `price_snapshot` / `selected_price`
   - Hal ini memastikan laporan keuangan dan audit historis tidak berubah ketika data master produk atau harga jual diperbarui di kemudian hari.

---

## 4. Immutability Dokumen Final (*No Hard Delete*)

1. ❌ Dokumen yang telah mencapai status final (`COMPLETED`, `POSTED`, `CLOSED`, `ISSUED`, `SETTLED`) **tidak boleh diedit atau dihapus secara bebas (*no hard delete*)**.
2. Metode koreksi transaksi final wajib menggunakan mekanisme audit resmi:
   - Penjualan POS: Menggunakan alur `void` (sebelum shift ditutup) atau dokumen `PosReturn`.
   - Penerimaan Barang (Goods Receipt): Menggunakan dokumen pembalik (*reversal receipt*).
   - Transfer Stok: Menggunakan transfer koreksi atau resolusi *discrepancy*.
   - Tagihan/Invoice: Menggunakan dokumen *Credit Note* yang disetujui.
   - Pembayaran: Menggunakan pencatatan pembalikan pembayaran (*payment reversal*).
   - Stok Fisik: Menggunakan dokumen *Stock Opname* atau *Stock Direct Adjustment*.

---

## 5. Aturan Penentuan Harga & Proteksi Margin (*Pricing Guardrails*)

1. **5 Urutan Prioritas Pemilihan Harga ([`PriceResolverService`](file:///c:/laragon/www/super-app-pos/app/Services/Pricing/PriceResolverService.php))**:
   1. *Customer Special Price* (override harga khusus pelanggan yang disetujui).
   2. Aturan harga produk sesuai *Price Category* pelanggan (`retail`, `grosir`, `reseller`, `project`, `special`).
   3. Aturan harga produk khusus cabang toko bersangkutan.
   4. Aturan harga produk global atau per channel (`retail`, `b2b`).
   5. *Fallback*: HPP produk + batas margin minimum aturan harga aktif.
2. **Deteksi Anomali & Wajib Approval**:
   - Transaksi penjualan/order wajib memerlukan *approval* atau ditolak jika:
     - Harga jual diskon berada di bawah harga minimum (`below_minimum`).
     - Harga jual melampaui batas toleransi wajar (`overpricing`).
     - Persentase diskon melebihi pagu diskon maksimum aturan (`discount_exceeds_cap`).
3. **Kerahasiaan Margin**:
   - Nilai HPP dan persentase margin kotor merupakan data sensitif yang hanya boleh dilihat oleh pengguna dengan hak akses `margins.view_sensitive` (Owner, Kepala Gudang, Purchasing).
   - Staf kasir, picker/packer, staf toko, dan pelanggan B2B tidak boleh melihat data margin internal.

---

## 6. Toko Internal, Kasir POS & Siklus Shift

1. **Prasyarat Kasir**:
   - Kasir wajib memiliki rekaman absensi masuk aktif pada shift kerjanya untuk dapat membuka shift kasir POS.
   - Pembukaan shift tanpa absensi hanya diperbolehkan melalui override supervisor berizin dengan mencatat alasan operasional.
2. **Siklus Kasir ([`CashShift`](file:///c:/laragon/www/super-app-pos/app/Services/Retail/CashShiftService.php))**:
   - Kasir mencatat modal kas awal fisik (`opening_cash`). Status shift menjadi `OPEN`.
   - Seluruh transaksi POS wajib terikat pada shift berstatus `OPEN`.
   - Transaksi tunai menambah ekspektasi kas (`expected_cash_amount`), pengeluaran kas (`shift_expenses`) dan refund tunai mengurangi ekspektasi kas.
3. **Penutupan Shift (*Closing*)**:
   - Kasir menginput rincian lembaran/koin kas fisik (`cash_counts`).
   - Rumus rekonsiliasi kas:
     ```text
     expected_cash = opening_cash + cash_sales - cash_expenses - refunds
     difference = actual_cash - expected_cash
     ```
   - Jika terdapat selisih kas fisik (`difference != 0`), kasir **wajib mengisi alasan selisih**.
   - Shift masuk status `CLOSING_SUBMITTED`, lalu diverifikasi dan disetujui oleh Supervisor/Kepala Toko menjadi `CLOSED`.
4. **Pembelian Darurat Toko (*Emergency Purchase*)**:
   - Saldo barang yang dibeli darurat disimpan pada *pool darurat* toko dan tidak langsung bercampur dengan stok reguler.
   - POS mengonsumsi stok reguler terlebih dahulu sebelum mengambil pool darurat.
   - Konversi saldo darurat bebas menjadi stok reguler toko wajib melalui persetujuan Kepala Toko dengan jejak mutasi.

---

## 7. Portal B2B, Reservasi Stok & Fulfillment

1. **Akses Portal Pelanggan**:
   - Pelanggan hanya dapat bertransaksi jika akun pelanggan (`Customer`) berstatus aktif, terverifikasi, dan tidak diblokir.
2. **Alur Pesanan B2B**:
   - Checkout portal &rarr; `PENDING_CONFIRMATION` &rarr; Verifikasi Gudang &rarr; `RESERVED` &rarr; Terbit Tagihan &rarr; `AWAITING_PAYMENT` / `APPROVED_CREDIT` &rarr; Picking & Packing &rarr; `SHIPPED` &rarr; Konfirmasi Terima &rarr; `RECEIVED`.
3. **Reservasi Stok**:
   - Persetujuan pesanan B2B memindahkan stok gudang dari status *available* ke *reserved* (`reserve`) dengan batas kedaluwarsa (default 24 jam).
   - Pembatalan pesanan otomatis melepaskan reservasi stok (`releaseReservation`).
   - Saat pengiriman (*shipment dispatched*), sistem melepaskan reservasi dan mencatat mutasi pengeluaran stok fisik (`issue`).

---

## 8. Kontrol Kredit, Piutang & Retur

1. **Plafon Kredit Pelanggan**:
   - Sisa limit kredit dihitung dengan rumus:
     ```text
     available_credit = credit_limit - receivable_balance
     ```
   - Penjualan POS kredit atau pesanan B2B kredit **wajib ditolak** jika nilai transaksi melebihi `available_credit` atau pelanggan memiliki faktur tertunggak yang melampaui toleransi hari jatuh tempo (`max_overdue_days`), kecuali ada *approval* khusus pimpinan.
2. **Pencatatan Pembayaran Piutang**:
   - Satu bukti pembayaran dapat dialokasikan ke beberapa dokumen piutang secara proporsional.
   - Pengurangan piutang di luar pembayaran (seperti kompensasi retur atau klaim) wajib menggunakan dokumen *Credit Note* yang disetujui pimpinan.
3. **Pemeriksaan Kualitas Retur (*Return QC*)**:
   - Barang retur fisik wajib di-QC menjadi kuantitas baik (*good* &rarr; mutasi `returnIn`), rusak (*damaged* &rarr; mutasi `returnIn` lalu `damage`), dan ditolak (*rejected*).
   - Jika estimasi nilai kerugian (*loss value*) retur melebihi Rp 1.000.000, dokumen retur wajib disetujui oleh Owner / Kepala Bagian sebelum diselesaikan (*settled*).

---

## 9. Prinsip Pemisahan Tugas (*Segregation of Duties*)

1. **Pencegahan Self-Approval**:
   - Pengguna yang mengajukan tindakan sensitif (pembuatan PO, diskon di bawah minimum, void penjualan, pengeluaran kas besar, penyesuaian stok opname) **dilarang menyetujui dokumennya sendiri**.
2. **Otoritas Berjenjang**:
   - Persetujuan PO bernilai besar, penghapusan piutang (*write-off*), koreksi stok bernilai tinggi, dan perubahan limit kredit strategis berada di bawah wewenang `owner_approver` atau Kepala Bagian terkait.

---

## 10. Sistem Target & Bonus Staf (`/target-bonus`)

1. **Dual Skema**:
   - Modul modern multi-role (`StaffBonusProgram`, `StaffBonusPeriod`, `StaffBonusResult`) berlaku untuk 5 role: `staff_gudang`, `picker_packer`, `staf_toko`, `kasir`, dan `sales`.
   - Skema legacy sales (`SalesBonus`) bersifat historis (*read-only*), tidak boleh dihitung ulang.
2. **Integritas Konfigurasi Program**:
   - Total bobot metrik KPI wajib tepat **100%**.
   - Program hanya dapat diaktifkan **sebelum bulan target dimulai** (`startOfMonth->isFuture()`). Setelah aktif, konfigurasi dibekukan (*snapshot*) dan tidak dapat diedit.
   - Satu akun karyawan aktif hanya boleh terdaftar pada **satu program bonus** dalam bulan/tahun yang sama.
3. **Batasan Metrik & Scope**:
   - Staff Gudang & Picker/Packer: Pekerjaan gudang tim (40%), akurasi transfer tim (20%), absensi individu (20%), checklist individu (20%).
   - Staf Toko & Kasir: Penjualan POS bersih tim (50%), akurasi selisih kas shift tim (10%), absensi individu (20%), checklist individu (20%).
   - Sales: Penjualan B2B bersih individu (80%), absensi individu (10%), checklist individu (10%).
4. **Kaidah Perhitungan & Cap Skor**:
   - `Skor KPI = min(100.00, max(0.00, (Aktual / Target) * 100))`. Pencapaian di atas 100% tidak menambah bonus melebihi batas maksimum program/override akun.
   - `Bonus = max(0.00, (Bonus Maksimum * Nilai Akhir / 100) + Penyesuaian)`.
5. **Pengajuan & Approval Berjenjang**:
   - Pengajuan hanya dapat dilakukan **setelah bulan periode berakhir** dan **seluruh absensi peserta telah berstatus verifikasi final** (tidak ada absensi *pending*).
   - Approval dilakukan oleh kepala divisi penugasan: `kepala_gudang` (gudang & sales global), `kepala_toko` (toko/kasir cabang).
   - Penolakan approval wajib mencantumkan alasan tertulis minimal 5 karakter.
6. **Pembayaran & Larangan Edit Histori**:
   - Pembayaran hanya dapat dicatat jika periode telah berstatus `approved`.
   - Nominal bayar harus sama persis dengan bonus yang disetujui. Wajib mencatat tanggal, metode pembayaran, dan nomor referensi transaksi.
   - Bonus yang sudah dibayar tidak boleh dihapus atau diedit. Koreksi dilakukan melalui `StaffBonusAdjustment` (+/-) yang dialokasikan ke periode aktif berikutnya.

---

## 11. Konteks Skenario Pilot Testing Lapangan (Single Store & In-Store Warehouse)

1. **Lingkup Operasional Aktif**:
   - Skenario implementasi dan pengujian lapangan saat ini terkonsentrasi pada **1 Toko Internal** (`Branch`) dan **Gudang Toko Lokal** (`Warehouse` yang berelasi langsung sebagai `primary_warehouse_id` toko tersebut).
2. **Penetapan Lokasi Kerja Otomatis**:
   - Seluruh pengguna pengujian lapangan (Kasir, Kepala Toko, Staf Toko, Staf Gudang Lokal) wajib ditugaskan ke lokasi kerja toko dan gudang lokal yang sama.
   - Hindari mewajibkan pengguna memilih lokasi secara manual jika akun hanya memiliki satu penugasan lokasi.
3. **Prioritas Alur Operasional Lapangan**:
   - Alur utama yang wajib bebas hambatan (*frictionless*):
     1. Penerimaan Barang Supplier (PO / Goods Receipt langsung ke gudang toko lokal).
     2. Restok Etalase Toko (Permintaan Restok & Terima Transfer lokal dari gudang ke etalase toko).
     3. Penjualan Kasir POS (Hold transaksi, diskon wajar, barcode scan, cetak struk).
     4. Pengelolaan Shift Kasir (Buka shift, setoran modal awal, pengeluaran kas, tutup shift harian).
     5. Pembelian Darurat Toko (*Emergency Purchase* & alokasi *pool* restok lokal).
     6. Opname & Penyesuaian Stok Fisik Toko.
4. **Isolasi Fitur yang Ditunda (*Deferred Scope*)**:
   - Distribusi *multi-warehouse*, transfer antar-cabang regional, dan alur pesanan B2B wholesale skala besar ditandai sebagai fitur sekunder dan tidak boleh memblokir alur kerja harian toko lokal.
5. **Integritas Arsitektur Jangka Panjang**:
   - Tetap pertahankan pemisahan entitas `Warehouse` dan `Branch` di level database dan service layer agar sistem tetap siap berekspansi ke multi-cabang tanpa refactoring besar di masa depan.


