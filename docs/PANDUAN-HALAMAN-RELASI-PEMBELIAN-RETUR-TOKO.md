# Panduan Halaman Relasi Bisnis, Pembelian, Retur, dan Toko Internal

Dokumen ini menjelaskan fungsi halaman, istilah, variabel input, alur frontend, dan logic backend untuk modul relasi bisnis, pembelian, retur, dan toko internal. Referensi implementasi utama:

- Relasi bisnis: `app/Http/Controllers/Admin/CustomerController.php`, `app/Http/Controllers/Admin/SupplierController.php`, `resources/views/admin/customers/*`, `resources/views/admin/suppliers/*`.
- Pembelian: `app/Http/Controllers/Purchasing/PurchaseRequestController.php`, `app/Http/Controllers/Purchasing/PurchaseOrderController.php`, `app/Services/Purchasing/PurchaseOrderService.php`, `resources/views/purchasing/*`.
- Retur: `app/Http/Controllers/Returns/ReturnController.php`, `app/Services/Returns/ReturnService.php`, `resources/views/returns/*`.
- Toko internal: `app/Http/Controllers/Retail/*`, `app/Services/Retail/*`, `app/Services/Warehouse/RestockRequestService.php`, `resources/views/retail/*`.

## 1. Relasi Bisnis

Relasi bisnis memuat dua master utama: pelanggan (`customers`) dan supplier (`suppliers`). Pelanggan dipakai oleh B2B, POS kredit, invoice, piutang, shipment, dan pricing khusus. Supplier dipakai oleh purchase order, goods receipt, histori harga beli, evaluasi performa, dan retur supplier.

### 1.1 Istilah

| Istilah | Makna |
|---|---|
| Pelanggan | Entitas pembeli, bisa pelanggan umum, grosir, reseller, proyek, atau B2B. |
| Customer user | Akun user yang terhubung ke pelanggan dan bisa masuk portal langganan/B2B. |
| Price category | Kategori harga default pelanggan: `retail`, `grosir`, `reseller`, `project`, `special`. |
| Credit limit | Batas maksimal piutang pelanggan. Dipakai saat POS kredit atau order/invoice kredit. |
| Available credit | `credit_limit - receivable_balance`. Jika tidak cukup, transaksi kredit ditolak. |
| Verification status | Status verifikasi data pelanggan. Default saat create adalah pending. |
| Account status | Status akun pelanggan untuk transaksi. Nonaktif berarti pelanggan tidak boleh dipakai transaksi baru. |
| Supplier | Pemasok barang ke gudang. Supplier aktif dapat dipilih pada PO. |
| Payment term | Jumlah hari tempo pembayaran. Nilai supplier menjadi default pada PO. |
| Performance score | Skor evaluasi supplier dari performa penerimaan, harga, dan ketepatan. |

### 1.2 Halaman Daftar Pelanggan

Route utama: `GET /admin/customers`.

Fungsi halaman:

- Mencari pelanggan berdasarkan kode, nama usaha, nama pemilik, PIC, WA, atau email.
- Memfilter tipe pelanggan, kategori harga, status, dan pelanggan yang melewati limit.
- Membuka detail pelanggan, edit profil, import, dan export.
- Membatasi data jika user hanya memiliki `customers.view_own`; user tersebut hanya melihat pelanggan yang terhubung di pivot `customer_users`.

Variabel filter FE dan BE:

| Variabel | Lokasi | Tipe | Fungsi | Dampak backend |
|---|---|---|---|---|
| `q` | Query string, input pencarian | string | Mencari pelanggan dari beberapa kolom identitas. | Controller memakai `where` berkelompok pada kode, business name, owner, PIC, WA, email. |
| `type` | Query string, dropdown tipe | enum/string | Memfilter tipe pelanggan. | Query `where('type', $type)`. |
| `price_category` | Query string, dropdown kategori harga | string | Memfilter kategori harga default. | Query `where('price_category', $category)`. |
| `status` | Query string, dropdown status | string | Memfilter pelanggan aktif/nonaktif atau status akun. | Query menyesuaikan `is_active` atau `account_status`. |
| `over_limit` | Query string, checkbox/filter | bool/string | Menampilkan pelanggan yang saldo piutangnya melebihi limit. | Query membandingkan `receivable_balance` terhadap `credit_limit`. |

Bagian FE:

- Toolbar `Import` membuka import master party untuk pelanggan.
- Toolbar `Export` men-download CSV sesuai hak akses.
- Toolbar `Tambah Pelanggan` membuka form create.
- Form filter memakai method GET, sehingga URL dapat dibagikan dengan state filter.
- Tabel menampilkan identitas, kategori harga, status, saldo piutang, limit, dan aksi.
- Empty state muncul jika tidak ada data sesuai filter.

Logic BE:

1. Controller membaca filter dari request.
2. Jika user punya `customers.view`, query menampilkan semua pelanggan sesuai filter.
3. Jika user tidak punya `customers.view` tetapi punya `customers.view_own`, query dibatasi ke pelanggan yang pivot `customer_users.is_active = true` dan `user_id` sama dengan user login.
4. Data dipaginate untuk tabel.
5. Export memakai query filter yang sama agar hasil CSV sesuai tampilan.

### 1.3 Form Pelanggan

Route create/store: `GET /admin/customers/create`, `POST /admin/customers`.
Route edit/update: `GET /admin/customers/{customer}/edit`, `PUT /admin/customers/{customer}`.

Variabel input:

| Variabel | Tipe | Wajib | Fungsi | Catatan risiko |
|---|---:|---|---|---|
| `type` | enum | Ya | Jenis pelanggan. | Mempengaruhi alur B2B dan segmentasi laporan. |
| `code` | string | Ya | Kode unik pelanggan. | Harus stabil karena dipakai referensi bisnis. |
| `business_name` | string | Ya | Nama usaha atau nama pelanggan. | Ditampilkan di order, invoice, shipment, POS kredit. |
| `owner_name` | string | Tidak | Nama pemilik usaha. | Data identitas, bukan login. |
| `pic_name` | string | Tidak | PIC operasional. | Dipakai komunikasi order dan collection. |
| `whatsapp_number` | string | Tidak | Nomor WA. | Dipakai notifikasi atau follow-up manual. |
| `email` | email | Tidak | Email pelanggan. | Dapat dipakai komunikasi dan akun portal. |
| `business_address` | text | Tidak | Alamat utama usaha. | Berbeda dengan alamat kirim detail di `customer_addresses`. |
| `city` | string | Tidak | Kota pelanggan. | Dipakai filter dan segmentasi. |
| `price_category` | string | Ya | Kategori harga default. | Price resolver akan memakai ini jika tidak ada harga khusus. |
| `minimum_order` | decimal | Tidak | Minimal nilai order. | Dapat dipakai validasi order B2B. |
| `payment_term_days` | integer | Tidak | Tempo pembayaran default. | Dipakai invoice/piutang. |
| `credit_limit` | decimal | Tidak | Limit kredit pelanggan. | Juga disalin ke relasi `credit_limits`. |
| `receivable_balance` | decimal | Tidak | Saldo piutang snapshot. | Jangan diedit tanpa proses piutang resmi. |
| `verification_status` | enum | Tidak | Status verifikasi dokumen/profil. | Default create pending. |
| `account_status` | enum | Tidak | Status akun transaksi. | Nonaktif menghentikan transaksi baru. |
| `status_reason` | string | Tidak | Alasan status akun. | Wajib secara operasional saat suspend/nonaktif. |
| `notes` | text | Tidak | Catatan internal. | Tidak untuk bukti transaksi. |
| `is_active` | bool | Ya | Flag master aktif. | Pelanggan nonaktif tidak layak dipilih transaksi. |

Logic BE create/update:

1. FormRequest memvalidasi data.
2. Store membuat record `customers`.
3. Store juga membuat `credit_limits` dengan `credit_limit`, `payment_term_days`, `current_balance = 0`, dan `effective_from = today`.
4. Update memperbarui data pelanggan dan melakukan `updateOrCreate` ke relasi `creditLimit`.
5. Activity log mencatat create/update.
6. Deactivate mengubah `account_status = INACTIVE`, `is_active = false`, dan menyimpan `status_reason`.

Relasi model penting:

- `Customer -> addresses`: daftar alamat kirim.
- `Customer -> users`: akun portal via pivot `customer_users`.
- `Customer -> documents`: dokumen verifikasi.
- `Customer -> priceOverrides`: harga khusus per produk/cabang.
- `Customer -> b2bOrders`, `invoices`, `payments`, `shipments`, `receivables`: histori transaksi dan tagihan.
- `Customer -> creditLimit`: konfigurasi limit kredit aktif.

### 1.4 Detail Pelanggan

Route: `GET /admin/customers/{customer}`.

Bagian FE:

| Bagian | Fungsi |
|---|---|
| Header/ringkasan | Menampilkan nama usaha, status akun, status verifikasi, kategori harga, limit, saldo piutang, dan available credit. |
| Tab Ringkasan | Data profil dan konfigurasi komersial. |
| Tab Pesanan | Order B2B terbaru jika user punya akses order. |
| Tab Tagihan | Invoice dan piutang jika user punya akses invoice/piutang. |
| Tab Pembayaran | Riwayat pembayaran dan aksi verifikasi jika punya permission. |
| Tab Pengiriman | Shipment pelanggan dan shortcut buat pengiriman bila ada order siap kirim. |
| Tab Relasi & Akses | Alamat kirim dan akun portal B2B. |
| Tab Harga & Dokumen | Harga khusus dan dokumen pelanggan. |

Logic BE:

1. Controller eager load relasi dasar: `addresses`, `users`, `documents`, `creditLimit`.
2. Relasi transaksi dimuat kondisional berdasarkan permission user.
3. Metrics dihitung dari order, invoice terbuka, saldo piutang, dan available credit.
4. View menyembunyikan bagian yang tidak boleh diakses, tetapi akses backend tetap dikunci policy/permission.

### 1.5 Halaman Supplier

Route utama: `GET /admin/suppliers`.

Fungsi halaman:

- Mencari supplier berdasarkan kode, nama, atau kontak.
- Memfilter kota dan status aktif.
- Import/export master supplier.
- Melihat performa supplier, jumlah produk, last price, dan termin.

Variabel filter:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `q` | string | Pencarian kode, nama, kontak. |
| `city` | string | Filter kota supplier. |
| `status` | string | `active`, `inactive`, atau semua. |

Variabel form supplier:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `code` | string | Ya | Kode unik supplier. |
| `name` | string | Ya | Nama supplier. |
| `contact_name` | string | Tidak | PIC utama supplier. |
| `phone_number` | string | Tidak | Telepon utama. |
| `whatsapp_number` | string | Tidak | Nomor WA untuk koordinasi. |
| `email` | email | Tidak | Email supplier. |
| `city` | string | Tidak | Kota supplier. |
| `tax_number` | string | Tidak | NPWP supplier. |
| `payment_term_days` | integer | Tidak | Default termin PO. |
| `bank_name` | string | Tidak | Nama bank. |
| `bank_account_name` | string | Tidak | Nama rekening. |
| `bank_account_number` | string | Tidak | Nomor rekening. |
| `address` | text | Tidak | Alamat supplier. |
| `notes` | text | Tidak | Catatan internal. |
| `is_active` | bool | Ya | Supplier aktif atau nonaktif. |

Detail supplier:

- Header menampilkan identitas, status, tanggal daftar, skor performa, total PO, dan total penerimaan.
- Info kontak, alamat, bank, catatan, dan aksi cepat.
- Tab Kontak menampilkan kontak tambahan.
- Tab Produk menampilkan produk yang disupply dan harga terkait.
- Tab PO menampilkan purchase order terbaru.
- Tab Penerimaan menampilkan goods receipt terbaru.
- Tab Audit menampilkan aktivitas perubahan.

Logic BE:

1. Index memakai `withCount('productsSupplied')`.
2. Show eager load kontak, produk supplier, dokumen, PO terbaru, dan goods receipt terbaru.
3. Supplier nonaktif tidak layak dipilih di form PO karena form data PO mengambil supplier aktif.
4. Export CSV mengeluarkan kode, nama, kontak, WA, email, kota, termin, dan status.

## 2. Pembelian

Modul pembelian mencakup permintaan pembelian (`purchase_requests`) dan purchase order (`purchase_orders`). Goods receipt berada di modul warehouse, tetapi status PO akan berubah ketika barang diterima.

### 2.1 Istilah

| Istilah | Makna |
|---|---|
| Purchase Request | Permintaan pembelian dari stok minimum atau input manual sebelum jadi PO. |
| Purchase Order | Dokumen resmi order ke supplier. |
| Warehouse | Gudang tujuan barang datang. Harus masuk scope lokasi user. |
| Expected at atau ETA | Perkiraan tanggal barang datang. |
| Quantity ordered | Qty yang dipesan pada PO. |
| Quantity received | Qty yang sudah diterima via goods receipt. |
| Outstanding quantity | `quantity_ordered - quantity_received`. |
| Header discount | Diskon dokumen PO secara keseluruhan. |
| Freight cost | Ongkos kirim pembelian. |
| Additional cost | Biaya tambahan lain pada PO. |

### 2.2 Permintaan Pembelian

Route utama: `GET /purchasing/requests`, `POST /purchasing/requests`.

Bagian FE:

| Bagian | Fungsi |
|---|---|
| Request Manual | Form membuat purchase request untuk gudang tertentu. |
| Rekomendasi dari Stok Minimum | Tabel produk yang stok tersedia kurang atau sama dengan minimum stock. |
| Daftar Permintaan | Tabel purchase request, filter status, dan aksi approve/reject/convert. |

Variabel form request manual:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `warehouse_id` | integer | Ya | Gudang yang membutuhkan barang. |
| `priority` | enum/string | Ya | Prioritas permintaan. |
| `reason` | text | Ya | Alasan kebutuhan pembelian. |
| `items[0][product_id]` | integer | Ya | Produk yang diminta. |
| `items[0][unit_id]` | integer/null | Tidak | Satuan. Jika kosong backend memakai base unit produk. |
| `items[0][quantity]` | decimal | Ya | Qty permintaan. |
| `items[0][reason]` | string | Tidak | Catatan per item. |

Logic BE:

1. Controller membatasi daftar gudang berdasarkan `permittedWorkLocationIds()`.
2. Rekomendasi stok mengambil `stocks` dengan `available_quantity <= products.minimum_stock`.
3. Store membuat nomor dokumen via `DocumentNumberService` untuk tipe request pembelian.
4. Status awal purchase request adalah `SUBMITTED`.
5. Item disimpan dengan produk, unit, qty, dan alasan.
6. History status disimpan di `document_status_histories`.
7. Approve mengubah status menjadi approved.
8. Reject mengubah status menjadi rejected dengan alasan.
9. Convert membuat PO baru dari item request, supplier dipilih pada form convert, lalu request ditandai converted.

### 2.3 Purchase Order

Route utama:

- `GET /purchasing/purchase-orders`
- `GET /purchasing/purchase-orders/create`
- `POST /purchasing/purchase-orders`
- `GET /purchasing/purchase-orders/{purchaseOrder}`
- `POST /purchasing/purchase-orders/{purchaseOrder}/submit`
- `POST /purchasing/purchase-orders/{purchaseOrder}/approve`
- `POST /purchasing/purchase-orders/{purchaseOrder}/send`
- `POST /purchasing/purchase-orders/{purchaseOrder}/cancel`

Variabel filter index:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `supplier_id` | integer | Filter PO per supplier. |
| `status` | enum/string | Filter status PO. |
| `date_from` | date | Awal rentang tanggal order. |
| `date_to` | date | Akhir rentang tanggal order. |

Variabel form header PO:

| Variabel | Tipe | Wajib | Fungsi | Logic backend |
|---|---:|---|---|---|
| `warehouse_id` | integer | Ya | Gudang tujuan pembelian. | Dipakai untuk nomor dokumen dan scope work location. |
| `supplier_id` | integer | Ya | Supplier PO. | Supplier aktif dipilih dari form data. |
| `order_date` | date | Ya | Tanggal order. | Disimpan sebagai tanggal PO. |
| `expected_at` | date | Tidak | ETA barang datang. | Tampil pada index/detail. |
| `payment_term_days` | integer | Tidak | Tempo pembayaran. | Default dari supplier jika kosong pada create service. |
| `notes` | string | Tidak | Catatan dokumen. | Dicetak dan ditampilkan di detail. |
| `purchase_request_id` | integer | Tidak | Link ke request asal. | Terisi bila PO dibuat dari convert PR. |

Variabel item PO:

| Variabel | Tipe | Wajib | Fungsi | Logic backend |
|---|---:|---|---|---|
| `items[n][product_id]` | integer | Ya | Produk yang dibeli. | Snapshot SKU dan nama produk disimpan. |
| `items[n][unit_id]` | integer | Ya | Satuan pembelian. | Harus base unit atau terdaftar pada `product_units`. |
| `items[n][quantity_ordered]` | decimal | Ya | Qty order. | Dinormalisasi decimal 4. |
| `items[n][unit_price]` | decimal | Ya | Harga per satuan. | Dinormalisasi decimal 2. |
| `items[n][discount_amount]` | decimal | Tidak | Diskon nominal item. | Dikurangi dari gross item. |
| `items[n][tax_amount]` | decimal | Tidak | Pajak nominal item. | Ditambahkan ke subtotal item. |

Variabel total PO:

| Variabel | Formula |
|---|---|
| `line subtotal` | `quantity_ordered * unit_price - discount_amount + tax_amount`. |
| `items_subtotal` | Total semua subtotal item. |
| `grand_total` | `items_subtotal - header_discount + freight_cost + additional_cost`. |

Bagian FE:

- Form header untuk gudang, supplier, tanggal, ETA, termin, dan catatan.
- Tabel item untuk produk, unit, qty, harga, diskon, pajak, subtotal.
- Script frontend menghitung subtotal baris dan grand total secara langsung agar user melihat estimasi sebelum submit.
- Tombol `Simpan Draft` menyimpan PO berstatus draft.
- Detail PO menampilkan header, item, progress penerimaan, timeline, approval, dan aksi dokumen.
- Aksi dokumen muncul sesuai status dan permission: submit, approve, kirim ke supplier, buat goods receipt, cancel, print, export.

Logic BE `PurchaseOrderService`:

1. `create()` membuka transaksi database.
2. Warehouse dan supplier diambil dari database.
3. Nomor PO dibuat via `DocumentNumberService` dengan work location warehouse.
4. PO dibuat dengan status `DRAFT`.
5. `replaceItems()` menghapus item lama pada update lalu membuat ulang item.
6. Backend memvalidasi satuan: satuan harus ada di `product_units` atau sama dengan base unit produk.
7. Snapshot produk dan unit disimpan agar histori PO tidak berubah saat master produk berubah.
8. `recalculate()` menghitung `items_subtotal` dan `grand_total`.
9. Jika `grand_total < 0`, service menolak PO.
10. `submit()` mengubah `DRAFT -> SUBMITTED`.
11. `approve()` mengubah `SUBMITTED -> APPROVED` dan membuat record `approvals`.
12. `markSent()` mengubah `APPROVED -> SENT_TO_SUPPLIER`.
13. `cancel()` hanya boleh jika belum ada item yang diterima.
14. `recordReceiptProgress()` dipanggil dari penerimaan barang untuk update qty diterima dan status menjadi partial/completed.

State PO:

| Dari | Ke | Pemicu |
|---|---|---|
| `draft` | `submitted` | Submit PO. |
| `draft` | `cancelled` | Cancel sebelum submit. |
| `submitted` | `approved` | Approval purchasing/owner. |
| `submitted` | `cancelled` | Cancel sebelum approved. |
| `approved` | `sent_to_supplier` | Kirim PO ke supplier. |
| `approved` | `partially_received` | Goods receipt sebagian. |
| `approved` | `completed` | Goods receipt penuh. |
| `sent_to_supplier` | `partially_received` | Goods receipt sebagian. |
| `sent_to_supplier` | `completed` | Goods receipt penuh. |
| `partially_received` | `completed` | Sisa barang diterima. |

Relasi model:

- `PurchaseOrder -> warehouse`, `supplier`, `creator`, `approver`, `purchaseRequest`.
- `PurchaseOrder -> items`, `goodsReceipts`, `statusHistories`, `approvals`.
- `PurchaseOrderItem -> product`, `unit`, `receiptItems`.

## 3. Retur

Modul retur dipakai untuk retur supplier, cabang, POS, B2B, transfer, atau manual. Workflow generik ini berbeda dari retur POS langsung di toko internal. Retur generik memiliki dokumen, item, QC, approval jika loss besar, settlement, dan mutasi stok.

### 3.1 Istilah

| Istilah | Makna |
|---|---|
| Return document | Header dokumen retur pada tabel `returns`. |
| Source | Sumber barang/masalah: supplier, cabang, POS, B2B, transfer, manual. |
| Destination | Tujuan penyelesaian retur bila diperlukan. |
| Reference | Dokumen asal seperti nomor PO, invoice, transfer, atau sale. |
| Requested resolution | Solusi yang diminta saat pengajuan. |
| QC | Pemeriksaan barang retur: good, damaged, rejected. |
| Loss value | Nilai kerugian dari barang damaged, dihitung dari qty damaged x HPP snapshot. |
| Settlement | Penyelesaian akhir: refund, credit note, penggantian, return to supplier, atau metode lain sesuai enum. |

### 3.2 Daftar Retur

Route: `GET /returns`.

Variabel filter:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `q` | string | Mencari nomor retur, referensi, atau nama sumber. |
| `source_type` | enum/string | Filter sumber retur. |
| `status` | enum/string | Filter status retur. |

Bagian FE:

- Toolbar `Ajukan Retur` membuka form create jika user punya `returns.create`.
- Toolbar `Export` men-download daftar retur.
- Filter mencari dokumen retur.
- Tabel menampilkan nomor, tanggal, sumber, nilai, status, dan aksi detail/QC.

### 3.3 Form Pengajuan Retur

Route: `GET /returns/create`, `POST /returns`.

Variabel header:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `work_location_id` | integer | Ya | Lokasi kerja tempat retur diproses. |
| `source_type` | enum | Ya | Sumber retur: `supplier`, `branch`, `b2b`, `pos`, `transfer`, `manual`. |
| `source_id` | integer | Tidak | ID sumber jika terhubung record internal. |
| `source_name` | string | Tidak | Nama sumber bila tidak ada ID. |
| `destination_type` | string | Tidak | Jenis tujuan penyelesaian. |
| `destination_id` | integer | Tidak | ID tujuan jika ada. |
| `destination_name` | string | Tidak | Nama tujuan. |
| `reference_type` | string | Tidak | Jenis dokumen asal. |
| `reference_id` | integer | Tidak | ID dokumen asal. |
| `reference_no` | string | Tidak | Nomor dokumen asal. |
| `reason` | string | Ya | Alasan retur. |
| `requested_resolution` | enum | Ya | Solusi yang diminta. |
| `return_date` | date | Ya | Tanggal retur. |
| `evidence` | file | Tidak | Bukti foto/PDF. |
| `idempotency_key` | string | Tidak | Mencegah double submit. |
| `notes` | text | Tidak | Catatan header. |
| `action` | enum | Tidak | `draft` atau `submit`. |

Variabel item:

| Variabel | Tipe | Wajib | Fungsi | Logic backend |
|---|---:|---|---|---|
| `items[n][product_id]` | integer | Ya | Produk retur. | Harus produk aktif. |
| `items[n][unit_id]` | integer | Tidak | Satuan retur. | Default base unit produk. |
| `items[n][warehouse_location_id]` | integer | Tidak | Lokasi masuk awal/QC. | Harus bin aktif bila dipilih. |
| `items[n][source_item_type]` | string | Tidak | Jenis item asal. | Dipakai kontrol qty retur terhadap dokumen asal. |
| `items[n][source_item_id]` | integer | Tidak | ID item asal. | Dipakai hitung qty yang sudah pernah diretur. |
| `items[n][source_quantity]` | decimal | Tidak | Qty asal dokumen. | Retur tidak boleh melebihi ini. |
| `items[n][quantity_requested]` | decimal | Ya | Qty yang diajukan retur. | Harus lebih dari 0. |
| `items[n][unit_cost_snapshot]` | decimal | Tidak | HPP snapshot. | Default dari `product.cost_price`. |
| `items[n][condition]` | enum | Ya | Kondisi awal. | `good`, `damaged`, atau opsi enum lain. |
| `items[n][reason]` | string | Tidak | Alasan per item. |
| `items[n][resolution]` | enum | Tidak | Solusi per item. | Default dari header. |
| `items[n][notes]` | string | Tidak | Catatan per item. |

Logic BE create:

1. `StoreReturnRequest::prepareForValidation()` membuang baris item kosong.
2. Jika `idempotency_key` sudah ada, service mengembalikan dokumen existing.
3. Service membuat nomor retur dengan `DocumentNumberService`.
4. Status menjadi `DRAFT` jika action draft, selain itu `SUBMITTED`.
5. Setiap item mengambil snapshot SKU, nama, unit, conversion factor, HPP, dan nilai baris.
6. Jika item terhubung ke dokumen asal, total retur lama + retur baru tidak boleh melebihi `source_quantity`.
7. Total quantity, total value, dan total loss value dihitung ulang.
8. History status dibuat.

### 3.4 QC Retur

Route: `GET /returns/{return}/inspection`, `POST /returns/{return}/inspection`.

Variabel QC:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `items[item_id][warehouse_location_id]` | integer | Tidak | Bin tempat stok masuk. |
| `items[item_id][quantity_good]` | decimal | Ya | Qty diterima layak jual. |
| `items[item_id][quantity_damaged]` | decimal | Ya | Qty diterima rusak. |
| `items[item_id][quantity_rejected]` | decimal | Ya | Qty ditolak/tidak diterima. |
| `items[item_id][condition]` | enum | Ya | Kondisi hasil QC. |
| `items[item_id][resolution]` | enum | Tidak | Solusi item setelah QC. |
| `items[item_id][responsible_party]` | string | Tidak | Pihak yang bertanggung jawab. |
| `items[item_id][notes]` | string | Tidak | Catatan QC. |

Logic BE QC:

1. Hanya retur status `SUBMITTED` yang boleh di-QC.
2. Untuk setiap item, service memvalidasi `quantity_good + quantity_damaged + quantity_rejected = quantity_requested`.
3. Qty good masuk stok dengan mutation `returnIn`.
4. Qty damaged masuk stok dahulu dengan `returnIn`, lalu langsung dipindahkan ke status rusak dengan `damage`.
5. Qty rejected tidak masuk stok.
6. `loss_value = quantity_damaged * unit_cost_snapshot`.
7. Record `return_inspections` dibuat per item.
8. Jika `total_loss_value > 1.000.000`, status menjadi `PENDING_APPROVAL`; jika tidak, status menjadi `INSPECTED`.

### 3.5 Approval dan Settlement Retur

Route approval: `GET /returns/{return}/approval`, `POST /returns/{return}/approve`.
Route settlement: `GET /returns/{return}/settlement`, `POST /returns/{return}/settlement`.

Variabel approval:

| Variabel | Fungsi |
|---|---|
| `notes` | Catatan keputusan approval. |

Variabel settlement:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `resolution` | enum | Ya | Resolusi akhir. |
| `document_no` | string | Tidak | Nomor memo, credit note, refund, atau dokumen supplier. |
| `amount` | decimal | Tidak | Nilai settlement. Default total value retur. |
| `notes` | text | Tidak | Catatan penyelesaian. |

Logic BE:

1. Approval hanya untuk status `PENDING_APPROVAL`.
2. Settlement hanya untuk status `INSPECTED` atau `APPROVED`.
3. Jika resolusi `RETURN_TO_SUPPLIER`, stok yang sudah masuk retur dikeluarkan dengan `returnOut`.
4. Jika barang damaged sebelumnya sudah masuk status rusak, service melakukan `recover` sebelum `returnOut` agar perpindahan stok valid.
5. Record `return_settlements` dibuat.
6. Status menjadi `SETTLED`.

State retur:

| Status | Makna |
|---|---|
| `draft` | Dokumen disimpan, belum diajukan. |
| `submitted` | Menunggu QC. |
| `inspected` | QC selesai dan tidak perlu approval nilai besar. |
| `pending_approval` | QC selesai tetapi loss melewati threshold. |
| `approved` | Retur nilai besar sudah disetujui. |
| `settled` | Retur sudah diselesaikan. |
| `rejected` / `cancelled` | Dokumen tidak diproses lanjut. |

Relasi model:

- `ReturnDocument -> workLocation`, `requester`, `checker`, `approver`.
- `ReturnDocument -> items`, `inspections`, `settlements`, `statusHistories`, `stockMutations`.
- `ReturnItem -> product`, `unit`, `warehouseLocation`, `inspections`.

## 4. Toko Internal

Toko internal mencakup POS, shift kasir, pengeluaran kecil, closing, retur/void POS, restock request cabang, dan penerimaan transfer ke cabang.

### 4.1 Istilah

| Istilah | Makna |
|---|---|
| Branch/Toko | Cabang toko internal tempat kasir menjual barang. |
| Work location | Lokasi kerja yang mengikat akses user, stok, shift, dan transaksi. |
| Cash shift | Sesi kasir dari buka kas sampai closing. |
| Opening cash | Modal awal kas saat shift dibuka. |
| Expected cash | Kas yang seharusnya ada: opening cash + penjualan tunai - expense tunai - refund. |
| Actual cash | Kas fisik saat closing. |
| Difference | `actual_cash - expected_cash`. Jika tidak nol harus ada alasan selisih. |
| POS sale | Transaksi penjualan toko. |
| Hold | Keranjang yang ditahan sementara oleh kasir. |
| Void | Pembatalan sale yang mengembalikan stok dan mengunci transaksi. |
| POS return | Retur dari transaksi POS sebelum shift closing. |
| Restock request | Permintaan cabang agar gudang mengirim stok. |

### 4.2 Buka Shift

Route: `GET /retail/shifts/open`, `POST /retail/shifts/open`.

Variabel form:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `branch_id` | integer | Ya | Cabang/toko shift dibuka. |
| `terminal_code` | string | Tidak | Kode terminal kasir, contoh POS-01. |
| `opening_cash_amount` | decimal | Ya | Modal awal kas fisik. |
| `discrepancy_threshold_amount` | decimal | Tidak | Ambang selisih untuk anomali. Default 50.000. |
| `attendance_override_reason` | text | Tidak | Alasan supervisor membuka shift tanpa absensi aktif. |
| `notes` | text | Tidak | Catatan serah terima awal. |

Logic BE:

1. Service memastikan branch aktif dan user punya akses work location branch.
2. Service mencoba mencari attendance aktif untuk kasir.
3. Jika tidak ada attendance, override hanya boleh bila alasan diisi dan user punya `attendance.approve` atau `cash_shifts.approve`.
4. User tidak boleh punya shift `OPEN` lain pada cabang yang sama.
5. Shift dibuat dengan status `OPEN`.
6. `expected_cash_amount` awal sama dengan `opening_cash_amount`.

### 4.3 POS Kasir

Route utama: `GET /retail/pos`, `POST /retail/pos`, `GET /retail/pos/checkout`.

Bagian FE POS:

| Bagian | Fungsi |
|---|---|
| Scan/Cari Produk | Pencarian barcode, SKU, nama produk, atau kategori favorit. |
| Tabel produk | Menampilkan hasil pencarian dan stok/harga ringkas. |
| Keranjang Cepat | Form checkout satu item cepat. |
| Pelanggan Opsional | Mengaitkan transaksi dengan pelanggan. Wajib jika pembayaran kredit. |
| Pembayaran | Metode dan nominal bayar. |
| Hold Keranjang | Menyimpan snapshot cart untuk dilanjutkan nanti. |
| Checkout Manual | Form input langsung untuk kasus testing/admin atau transaksi manual. |

Variabel checkout:

| Variabel | Tipe | Wajib | Fungsi | Logic backend |
|---|---:|---|---|---|
| `idempotency_key` | uuid/string | Tidak | Mencegah double checkout. | Jika sudah ada, sale existing dikembalikan. |
| `branch_id` | integer | Ya | Cabang transaksi. | User harus punya akses branch. |
| `customer_id` | integer | Tidak | Pelanggan transaksi. | Wajib jika ada payment `credit`. |
| `items[n][product_id]` | integer | Ya | Produk dijual. | Produk harus aktif. |
| `items[n][unit_id]` | integer | Tidak | Satuan jual. | Default base unit produk. |
| `items[n][warehouse_location_id]` | integer | Tidak | Bin stok di toko. | Dipakai saat issue stok. |
| `items[n][quantity]` | decimal | Ya | Qty jual. | Dikonversi ke base quantity oleh pricing. |
| `items[n][selected_price]` | decimal | Tidak | Harga manual. | Dicek PriceResolver terhadap minimum price/approval. |
| `items[n][discount_percent]` | decimal | Tidak | Diskon persen. | Jika berlebih, PriceResolver menolak. |
| `payments[n][method]` | enum | Ya | Metode bayar: cash, bank transfer, QRIS, manual, credit. | Method harus valid. |
| `payments[n][amount]` | decimal | Ya | Nominal bayar. | Total paid harus >= grand total. |
| `payments[n][reference_no]` | string | Tidak | Referensi transfer/QRIS/manual. |
| `payments[n][notes]` | string | Tidak | Catatan pembayaran. |
| `notes` | text | Tidak | Catatan sale. |

Logic BE checkout:

1. Service memastikan kasir punya shift `OPEN` di branch.
2. Service menghitung item dengan `PriceResolverService`.
3. Jika harga/diskon butuh approval, checkout ditolak.
4. Pembayaran divalidasi. Total bayar harus cukup.
5. Jika ada pembayaran kredit, pelanggan wajib ada dan limit kredit dicek melalui `ReceivableService`.
6. Sale dibuat dengan status `COMPLETED`.
7. Item sale menyimpan snapshot SKU, nama, unit, HPP, minimum price, selected price, diskon, margin, dan price snapshot.
8. Inventory mengurangi stok dengan mutation `issue`.
9. Payment rows dibuat di `sale_payments`.
10. Jika ada cash payment, `expected_cash_amount` shift ditambah sebesar pembayaran tunai.
11. Jika ada credit payment, piutang dibuat dari POS sale.

Formula POS:

| Variabel | Formula |
|---|---|
| `line_subtotal` | `quantity * selected_price`. |
| `line_total` | `quantity * discounted_price`. |
| `discount_amount` | `line_subtotal - line_total`. |
| `grand_total_amount` | Total seluruh `line_total`. |
| `change_amount` | `paid_amount - grand_total_amount`. |
| `margin_amount` | Qty dikali margin hasil price resolver. |

### 4.4 Hold, Detail Sale, Void, dan Retur POS

Hold:

- Route `GET /retail/pos/holds`, `POST /retail/pos/holds`.
- `cart_snapshot` menyimpan isi keranjang sementara.
- `estimated_total` menyimpan estimasi nilai.
- Resume/cancel hanya boleh oleh kasir yang sama.

Detail sale:

- Menampilkan status, waktu, kasir, pelanggan, grand total, bayar/kembali, item, HPP/margin untuk user yang punya akses sensitif, pembayaran, dan mutasi stok.
- Aksi `Cetak Struk`, `Void`, dan `Retur` bergantung policy.

Void POS:

| Variabel | Fungsi |
|---|---|
| `reason` | Alasan pembatalan transaksi. |

Logic void:

1. Tidak boleh void jika shift sudah locked/closing.
2. Status sale harus boleh void.
3. Sisa qty yang belum diretur dikembalikan ke stok dengan `returnIn`.
4. Sale menjadi `VOID_APPROVED`.
5. Alasan, pemohon, approver, dan waktu void disimpan.

Retur POS:

| Variabel | Fungsi |
|---|---|
| `resolution` | `refund`, `exchange`, atau `credit`. |
| `refund_method` | Cara pengembalian dana. |
| `reason` | Alasan retur. |
| `items[n][pos_sale_item_id]` | Item sale yang diretur. |
| `items[n][quantity]` | Qty retur. |
| `items[n][condition]` | `good` atau `damaged`. |

Logic retur POS:

1. Tidak boleh retur jika shift sudah locked/closing.
2. Sale harus `COMPLETED` atau `RETURNED`.
3. Qty retur tidak boleh melebihi sisa `base_quantity - returned_quantity`.
4. Refund dihitung proporsional dari `line_total / base_quantity * quantity`.
5. Barang masuk stok dengan `returnIn`.
6. Jika condition `damaged`, stok langsung dipindahkan ke rusak dengan `damage`.
7. `returned_quantity` pada item sale bertambah.
8. Sale menjadi `RETURNED`.

### 4.5 Pengeluaran, Closing, dan Approval Shift

Pengeluaran shift:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `category` | string | Ya | Kategori expense: plastik, transport, parkir, operasional, lainnya. |
| `payment_method` | enum | Ya | Metode expense. Hanya cash yang mengurangi expected cash. |
| `amount` | decimal | Ya | Nominal expense. |
| `spent_at` | datetime | Tidak | Waktu pengeluaran. |
| `proof_path` | string | Tidak | Path bukti. |
| `notes` | text | Tidak | Catatan expense. |

Logic expense:

1. Expense hanya boleh pada shift `OPEN`.
2. User harus punya akses lokasi shift.
3. Expense di atas 1.000.000 butuh user dengan `cash_shifts.approve`.
4. Expense disimpan pada `shift_expenses`.

Closing shift:

| Variabel | Fungsi |
|---|---|
| `actual_cash_amount` | Total kas fisik yang dihitung kasir. |
| `cash_counts[n][denomination]` | Pecahan uang. |
| `cash_counts[n][quantity]` | Jumlah lembar/koin. |
| `discrepancy_reason` | Wajib jika actual cash berbeda dari expected cash. |
| `handover_notes` | Catatan serah terima closing. |

Formula summary shift:

| Variabel | Formula |
|---|---|
| `cash_sales` | Total payment method cash dari sale non-void. |
| `non_cash_sales` | Total bank transfer + QRIS + manual. |
| `receivable_sales` | Total payment method credit. |
| `refunds` | Total refund POS return pada shift. |
| `expenses` | Total expense cash pada shift. |
| `expected_cash` | `opening_cash + cash_sales - expenses - refunds`. |
| `difference` | `actual_cash - expected_cash`. |

Logic closing:

1. Hanya kasir pemilik shift yang boleh submit closing.
2. Shift yang bisa ditutup hanya `OPEN` atau `REJECTED`.
3. Jika `actual_cash_amount` tidak diisi, service menghitung dari pecahan `cash_counts`.
4. Jika ada selisih, alasan selisih wajib.
5. Cash count lama dihapus dan diganti dengan input baru.
6. Shift menjadi `CLOSING_SUBMITTED`.
7. Snapshot summary disimpan ke shift.
8. Approval log dibuat dan anomaly detection dijalankan.

Approval shift:

1. Supervisor membuka halaman approval closing.
2. Summary expected, actual, selisih, tunai, non tunai, dan expense ditampilkan.
3. Approve mengubah status menjadi `CLOSED`, mengisi approver dan closed time.
4. Reject mengubah status menjadi `REJECTED`, sehingga kasir dapat submit closing ulang.

### 4.6 Restock Request Cabang

Route: `GET /retail/restock-requests`, `POST /retail/restock-requests`.

Variabel form:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `branch_id` | integer | Ya | Cabang yang meminta stok. |
| `source_warehouse_id` | integer | Tidak | Gudang sumber. Jika kosong memakai gudang utama cabang. |
| `priority` | enum/string | Tidak | Prioritas request. |
| `needed_at` | date | Tidak | Tanggal kebutuhan. |
| `items[n][product_id]` | integer | Ya | Produk diminta. |
| `items[n][quantity_requested]` | decimal | Ya | Qty permintaan. |
| `items[n][priority]` | string | Tidak | Prioritas item. |
| `items[n][notes]` | string | Tidak | Catatan item. |
| `notes` | text | Tidak | Catatan header. |
| `action` | enum | Tidak | `draft` atau `submit`. |

Logic BE:

1. Service mengambil branch dan gudang sumber.
2. Jika `source_warehouse_id` kosong, memakai `branch.primaryWarehouse`.
3. Jika cabang tidak punya gudang sumber, request ditolak.
4. Request dibuat status `DRAFT`.
5. Item wajib minimal satu dan qty harus lebih dari nol.
6. Jika action submit, status berubah ke `PENDING_APPROVAL`.
7. Approve mengisi `quantity_approved` tiap item dan status `APPROVED`.
8. Reject menyimpan alasan dan status `REJECTED`.
9. Convert membuat stock transfer dari gudang sumber ke cabang.

### 4.7 Penerimaan Transfer di Cabang

Route: `GET /retail/stock-transfers/{stockTransfer}/receive`, `POST /retail/stock-transfers/{stockTransfer}/receive`.

Variabel form:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `idempotency_key` | uuid/string | Mencegah double submit penerimaan. |
| `received_at` | datetime | Waktu terima barang. |
| `proof` | file | Bukti foto/PDF. |
| `notes` | string | Catatan penerimaan. |
| `items[item_id][quantity_received]` | decimal | Qty diterima baik. |
| `items[item_id][quantity_damaged]` | decimal | Qty diterima rusak. |
| `items[item_id][quantity_discrepancy]` | decimal | Selisih kurang/lebih yang perlu diselesaikan. |
| `items[item_id][notes]` | string | Catatan item. |

Bagian FE:

- Header menampilkan nomor transfer.
- Input tanggal terima, bukti, dan catatan.
- Tabel item menampilkan SKU, nama produk, qty dikirim, qty sudah diterima, sisa transit, dan input penerimaan.
- Tombol konfirmasi mengirim POST untuk update penerimaan.

Logic BE ringkas:

1. Transfer harus berada pada status yang siap diterima.
2. Qty diterima tidak boleh melebihi sisa in transit.
3. Qty baik masuk stok cabang.
4. Qty damaged diproses sebagai stok rusak/loss sesuai service transfer.
5. Discrepancy dicatat untuk resolusi jika ada selisih.
6. Status transfer berubah sesuai total diterima dan discrepancy.

## 5. Hak Akses dan Dampak Modul

| Modul | Permission utama | Dampak lintas modul |
|---|---|---|
| Pelanggan | `customers.view`, `customers.view_own`, `customers.create`, `customers.update`, `customers.manage_access`, `customers.manage_settings`, `customers.export` | B2B, POS kredit, piutang, invoice, shipment, pricing. |
| Supplier | `suppliers.view`, `suppliers.create`, `suppliers.update`, `suppliers.import`, `suppliers.export` | PO, goods receipt, HPP, retur supplier, laporan supplier. |
| Pembelian | `purchase_orders.view`, `purchase_orders.create`, `purchase_orders.approve` | Penerimaan barang, stok, HPP, laporan pembelian. |
| Retur | `returns.view`, `returns.create`, `returns.inspect`, `returns.approve`, `returns.settle` | Stok, loss, refund/credit note, laporan retur. |
| POS | `pos.view`, `pos.create`, `pos.void` | Stok toko, kas shift, piutang retail, margin. |
| Shift | `cash_shifts.view`, `cash_shifts.open`, `cash_shifts.close`, `cash_shifts.approve` | Closing kas, laporan retail, anomaly detection. |

## 6. Checklist Pengujian Manual

Relasi bisnis:

1. Buat pelanggan baru, pastikan record `customers` dan `credit_limits` terbentuk.
2. Ubah limit kredit, pastikan detail pelanggan menghitung available credit baru.
3. Login user dengan `customers.view_own`, pastikan hanya pelanggan pivot aktif yang terlihat.
4. Nonaktifkan supplier, pastikan supplier tidak muncul di pilihan PO baru.
5. Export pelanggan/supplier dengan filter, pastikan isi file mengikuti filter.

Pembelian:

1. Buat purchase request manual dengan satu item.
2. Approve request lalu convert ke PO dengan supplier aktif.
3. Buat PO manual, cek subtotal frontend dan grand total backend sama.
4. Submit PO, approve, lalu send ke supplier.
5. Buat goods receipt sebagian dan pastikan PO menjadi partially received.
6. Terima semua sisa barang dan pastikan PO menjadi completed.
7. Coba cancel PO yang sudah menerima barang, harus ditolak.

Retur:

1. Buat retur draft dan submit.
2. QC dengan total good + damaged + rejected tidak sama dengan qty retur, harus ditolak.
3. QC dengan qty valid dan damaged kecil, status menjadi inspected.
4. QC dengan loss di atas 1.000.000, status menjadi pending approval.
5. Approve retur nilai besar.
6. Settlement return to supplier, pastikan ada mutasi return out.
7. Coba retur source item melebihi source quantity, harus ditolak.

Toko internal:

1. Buka shift tanpa attendance aktif sebagai kasir biasa, harus ditolak.
2. Buka shift dengan override supervisor, harus berhasil.
3. Checkout POS tunai, pastikan stok berkurang dan expected cash bertambah.
4. Checkout POS kredit tanpa pelanggan, harus ditolak.
5. Checkout POS kredit dengan limit tidak cukup, harus ditolak.
6. Void sale sebelum closing, stok kembali dan status sale void approved.
7. Retur POS damaged, stok masuk lalu menjadi stok rusak.
8. Input expense cash, closing, dan pastikan expected cash = opening + cash sales - expenses - refunds.
9. Submit closing dengan selisih tanpa alasan, harus ditolak.
10. Approve closing, lalu pastikan sale pada shift tersebut tidak bisa void/return.
11. Buat restock request, approve, convert to transfer, lalu terima transfer di cabang.

## 7. Catatan Implementasi dan Risiko

- Jangan mengubah snapshot transaksi lama. Field seperti `product_name_snapshot`, `unit_cost_snapshot`, `hpp_snapshot`, dan `price_snapshot` sengaja disimpan agar histori tetap konsisten walaupun master berubah.
- Jangan update stok langsung dari controller atau view. Semua perubahan stok harus lewat service inventory agar stock mutation dan idempotency konsisten.
- Jangan mengandalkan hitungan frontend untuk nilai final. Frontend hanya estimasi; backend tetap sumber kebenaran untuk subtotal, grand total, harga, stok, dan kas.
- Jangan menghapus dokumen transaksi final. Gunakan workflow void, cancel, settlement, atau reverse sesuai modul.
- Setiap query lokasi kerja harus mengikuti scope user melalui `permittedWorkLocationIds()` atau `canAccessWorkLocation()`.
- Supplier/pelanggan nonaktif sebaiknya tidak tersedia pada transaksi baru, tetapi histori lama tetap harus bisa dibuka.
