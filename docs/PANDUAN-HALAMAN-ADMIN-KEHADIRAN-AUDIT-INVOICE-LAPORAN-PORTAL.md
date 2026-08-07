# Panduan Halaman Administrasi, Kehadiran, Sistem, Control Audit, Invoice Pembayaran, Laporan, dan Portal Langganan

Dokumen ini melengkapi panduan modul operasional sebelumnya. Fokusnya adalah halaman administrasi internal, administrasi sistem, kehadiran, control dan audit, invoice dan pembayaran, laporan, serta portal langganan/B2B. Struktur dibuat konsisten: istilah, fungsi halaman, variabel input/filter, alur frontend, logic backend, relasi model, dampak lintas modul, dan checklist pengujian.

Referensi implementasi utama:

- Administrasi: `app/Http/Controllers/Admin/UserController.php`, `RoleController.php`, `PermissionController.php`, `SystemSettingController.php`, `DocumentSequenceController.php`, serta `resources/views/admin/*`.
- Kehadiran: `app/Http/Controllers/Attendance/*`, `app/Services/Attendance/AttendanceService.php`, `app/Http/Requests/Attendance/*`, dan `resources/views/attendance/*`.
- Administrasi sistem: `app/Http/Controllers/System/*`, `app/Services/System/*`, `resources/views/admin/settings/*`.
- Control dan audit: `app/Http/Controllers/Control/*`, `app/Services/Control/*`, `resources/views/audit/*`.
- Notifikasi dan audit notification: `app/Http/Controllers/Notifications/*`, `app/Services/Notifications/*`, `resources/views/notifications/*`.
- Invoice dan pembayaran: `app/Http/Controllers/InvoiceController.php`, `PaymentController.php`, `Receivables/ReceivableController.php`, `app/Services/B2B/B2bFulfillmentService.php`, `app/Services/Receivables/ReceivableService.php`, `resources/views/invoices/*`, `payments/*`, `receivables/*`.
- Laporan: `app/Http/Controllers/Reports/*`, `app/Services/Reports/ReportMetricService.php`, `ReportExportService.php`, `resources/views/reports/*`, `resources/views/dashboards/*`.
- Portal langganan: `app/Http/Controllers/B2B/*`, `app/Services/B2B/*`, `resources/views/b2b/*`.

## 1. Administrasi Internal

Administrasi internal mengatur akun user, role, permission, lokasi kerja user, master produk pendukung, cabang, gudang, pelanggan, supplier, dan import master data. Bagian pelanggan dan supplier dijelaskan mendetail di dokumen relasi bisnis; dokumen ini fokus ke user, role, permission, dan master administrasi umum.

### 1.1 Istilah

| Istilah | Makna |
|---|---|
| User | Akun login aplikasi internal atau portal pelanggan. |
| Role | Kelompok hak akses, misalnya `super_admin`, `staff_gudang`, `kasir`, `langganan_owner`. |
| Permission | Hak akses granular yang dipakai middleware, policy, dan tampilan tombol. |
| Work location | Lokasi kerja yang membatasi akses data transaksi dan stok. |
| Location scope | Daftar work location yang boleh diakses user. |
| Active user | User yang belum dinonaktifkan dan lolos middleware `active.user`. |
| DataTable users | Endpoint AJAX untuk daftar user dengan filter dan paging server-side. |

### 1.2 Daftar User

Route utama:

- `GET /admin/users`
- `GET /admin/users/datatable`
- `GET /admin/users/export`

Fungsi halaman:

- Melihat user internal dan portal.
- Mencari user berdasarkan nama, email, username, atau role.
- Memfilter status aktif dan role.
- Membuka detail, edit, reset password, export, dan pengaturan lokasi kerja.

Variabel filter:

| Variabel | Tipe | Fungsi | Dampak backend |
|---|---:|---|---|
| `q` | string | Pencarian nama, email, username. | Query `users` dan relasi role. |
| `role` | string/int | Filter role tertentu. | Query `whereHas('roles')`. |
| `status` | string | Filter aktif/nonaktif. | Query `is_active` atau status terkait. |
| `location_id` | integer | Filter lokasi kerja. | Query pivot `user_work_locations`. |

Bagian FE:

- Toolbar `Tambah User` membuka form create.
- Toolbar `Export` men-download daftar user.
- Filter dan tabel menampilkan identitas, role, lokasi, status, dan aksi.
- Tombol detail/edit/lokasi hanya tampil jika permission cocok.
- Endpoint datatable menyediakan JSON untuk tabel jika halaman memakai mode AJAX.

Logic BE:

1. `UserController@index()` menyiapkan filter, role, dan data awal.
2. `dataTable()` memakai `UsersDataTable` untuk response JSON.
3. `export()` memakai query filter dan stream CSV.
4. Semua route dilindungi permission `admin.users.*`.
5. Detail user memuat role, permissions, lokasi kerja, audit aktivitas, dan transaksi terkait sesuai implementasi view.

### 1.3 Form User

Route:

- `GET /admin/users/create`
- `POST /admin/users`
- `GET /admin/users/{user}/edit`
- `PUT /admin/users/{user}`
- `PATCH /admin/users/{user}/deactivate`
- `POST /admin/users/{user}/password-reset`

Variabel form user:

| Variabel | Tipe | Wajib | Fungsi | Catatan |
|---|---:|---|---|---|
| `name` | string | Ya | Nama tampilan user. | Muncul di audit, approval, dan transaksi. |
| `username` | string | Ya/opsional sesuai request | Identitas login alternatif selain email. | Harus unik jika digunakan. |
| `email` | email | Ya | Email login/reset password. | Harus unik. |
| `password` | string | Create wajib | Password awal. | Update biasanya opsional. |
| `password_confirmation` | string | Jika password diisi | Konfirmasi password. | Validasi Laravel confirmed. |
| `roles` | array | Tidak/Ya sesuai form | Role yang diberikan. | Disinkronkan ke Spatie Permission. |
| `is_active` | boolean | Tidak | Status user. | User nonaktif ditolak middleware. |
| `work_location_ids` | array | Tidak | Scope lokasi kerja user. | Dikelola juga dari halaman lokasi user. |
| `phone_number` / `whatsapp_number` | string | Tidak | Kontak user. | Dipakai notifikasi/manual contact bila ada. |

Logic BE:

1. Store memvalidasi email/username unik.
2. Password di-hash sebelum disimpan.
3. Role disinkronkan dengan Spatie Permission.
4. Work location disinkronkan melalui pivot jika form mengirim lokasi.
5. Update tidak mengganti password jika field password kosong.
6. Deactivate mengubah status user menjadi nonaktif, bukan menghapus histori.
7. Reset password mengirim notifikasi reset password ke email user.

Risiko:

- Jangan menghapus user yang sudah punya histori transaksi. Nonaktifkan saja.
- Perubahan role langsung memengaruhi menu, tombol, policy, dan akses route.
- Perubahan lokasi kerja langsung memengaruhi visibilitas stok, PO, shift, POS, dan laporan.

### 1.4 Lokasi Kerja User

Route:

- `GET /admin/users/{user}/locations`
- `PUT /admin/users/{user}/locations`

Variabel:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `work_location_ids` | array integer | Daftar lokasi kerja yang boleh diakses user. |

Logic:

1. Form menampilkan seluruh work location aktif.
2. User admin memilih lokasi yang boleh diakses.
3. Backend sync pivot user-work-location.
4. Method `User::permittedWorkLocationIds()` dan `User::canAccessWorkLocation()` memakai data ini.

### 1.5 Role dan Permission

Route utama:

- `GET /admin/roles`
- `POST /admin/roles`
- `GET /admin/roles/{role}`
- `PUT /admin/roles/{role}`
- `POST /admin/roles/{role}/duplicate`
- `PUT /admin/roles/{role}/permissions`
- `DELETE /admin/roles/{role}`
- `GET /admin/permissions`

Variabel role:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `name` | string | Ya | Nama teknis role. |
| `guard_name` | string | Tidak | Guard Spatie, biasanya `web`. |
| `display_name` / label | string | Tidak | Nama tampilan jika tersedia. |
| `permissions` | array string | Tidak | Permission yang diberikan ke role. |

Bagian FE:

- Index role menampilkan role, jumlah user, jumlah permission, dan aksi.
- Form role membuat/mengedit identitas role.
- Halaman detail role menampilkan permission aktif dan user yang memakai role.
- Form permission role biasanya berupa checklist per modul.
- Duplicate role mempercepat pembuatan role turunan.

Logic BE:

1. Store membuat role baru lalu sync permission jika dikirim.
2. Update mengganti data role.
3. `updatePermissions()` melakukan sync permission secara eksplisit.
4. Destroy dicegah jika role masih dipakai atau role sistem tertentu.
5. Permission index hanya menampilkan daftar permission; perubahan permission biasanya dari seeder/config RBAC, bukan form bebas.

Risiko:

- Role `super_admin` tidak boleh dikurangi sembarangan karena dipakai akses sistem.
- Permission route dan policy harus selaras; tombol FE tersembunyi tidak cukup sebagai pengaman.

### 1.6 Master Administrasi Umum

Halaman ini berada di prefix `/admin`, meliputi:

- Gudang: `admin/warehouses`.
- Cabang: `admin/branches`.
- Kategori produk: `admin/product-categories`.
- Brand produk: `admin/product-brands`.
- Satuan: `admin/units`.
- Produk: `admin/products`.
- Barcode dan import produk.
- Import pelanggan/supplier melalui `admin/parties/{type}/import`.

Variabel penting master produk:

| Variabel | Fungsi |
|---|---|
| `sku` | Kode unik produk. |
| `name` | Nama produk. |
| `category_id` | Kategori produk. |
| `brand_id` | Brand produk. |
| `base_unit_id` | Satuan dasar stok. |
| `minimum_stock` | Ambang rekomendasi restock/purchase. |
| `minimum_order` | Minimal qty order B2B. |
| `cost_price` | HPP/master cost untuk snapshot transaksi. |
| `selling_price` | Harga jual dasar. |
| `status` | Produk aktif/nonaktif. |

Logic umum:

1. Master aktif dipakai sebagai opsi transaksi baru.
2. Master nonaktif tetap boleh tampil di histori transaksi via snapshot.
3. Import memakai preview lalu commit agar data bisa dicek sebelum masuk database.
4. Barcode memakai data SKU/product unit untuk cetak label.

## 2. Kehadiran

Modul kehadiran mengatur karyawan, shift kerja, jadwal, check-in/out, izin, dan koreksi absensi. Modul ini juga terhubung ke shift kasir: buka shift POS dapat mensyaratkan attendance aktif.

### 2.1 Istilah

| Istilah | Makna |
|---|---|
| Employee | Profil karyawan yang dapat terhubung ke user login. |
| Work shift | Template jam kerja, toleransi terlambat, toleransi pulang cepat, dan hari kerja. |
| Employee schedule | Jadwal aktual karyawan pada tanggal tertentu. |
| Attendance | Record check-in/check-out harian. |
| Check method | Cara absensi: login, PIN, QR, atau supervisor. |
| Attendance request | Pengajuan izin/cuti/sakit/dinas dari karyawan. |
| Correction | Koreksi jam check-in/out yang perlu approval. |
| Late minutes | Selisih terlambat setelah toleransi. |
| Early leave minutes | Selisih pulang cepat setelah toleransi. |

### 2.2 Karyawan

Route:

- `GET /attendance/employees`
- `GET /attendance/employees/create`
- `POST /attendance/employees`
- `GET /attendance/employees/{employee}/edit`
- `PUT /attendance/employees/{employee}`
- `PATCH /attendance/employees/{employee}/deactivate`

Variabel form employee:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `user_id` | integer | Tidak | Menghubungkan employee ke user login. |
| `work_location_id` | integer | Ya | Lokasi kerja default karyawan. |
| `employee_no` | string | Ya | Nomor induk karyawan, unik. |
| `name` | string | Ya | Nama karyawan. |
| `position` | string | Tidak | Jabatan. |
| `whatsapp_number` | string | Tidak | Nomor WA. |
| `joined_at` | date | Tidak | Tanggal bergabung. |
| `status` | enum | Ya | Status employee. |
| `is_active` | boolean | Tidak | Flag aktif. |

Alur FE:

1. Admin membuka daftar karyawan.
2. Filter atau cari karyawan.
3. Tambah/edit karyawan dari form.
4. Nonaktifkan jika karyawan tidak digunakan lagi.

Logic BE:

1. Request memastikan `employee_no` unik.
2. `user_id` jika diisi harus unik di tabel employee.
3. Work location harus valid.
4. Deactivate mengubah status aktif tanpa menghapus histori absensi.

### 2.3 Template Shift Kerja

Route:

- `GET /attendance/work-shifts`
- `GET /attendance/work-shifts/create`
- `POST /attendance/work-shifts`
- `GET /attendance/work-shifts/{workShift}/edit`
- `PUT /attendance/work-shifts/{workShift}`

Variabel form:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `work_location_id` | integer | Tidak | Shift khusus lokasi tertentu. |
| `code` | string | Ya | Kode shift unik. |
| `name` | string | Ya | Nama shift. |
| `start_time` | time `H:i` | Ya | Jam mulai kerja. |
| `end_time` | time `H:i` | Ya | Jam selesai kerja. |
| `is_cross_midnight` | boolean | Tidak | Shift melewati tengah malam. |
| `tolerance_late_minutes` | integer | Ya | Toleransi terlambat. |
| `tolerance_early_leave_minutes` | integer | Ya | Toleransi pulang cepat. |
| `break_minutes` | integer | Tidak | Durasi istirahat. |
| `work_days` | array integer | Tidak | Hari aktif, 0 sampai 6. |
| `effective_from` | date | Tidak | Mulai berlaku. |
| `effective_until` | date | Tidak | Akhir berlaku. |
| `is_active` | boolean | Tidak | Status template. |

Logic BE:

1. Kode shift harus unik.
2. Jam disimpan sebagai template, bukan record attendance.
3. Shift cross midnight dipakai menghitung jadwal yang melewati tanggal.
4. Effective date mencegah shift lama dipakai di periode yang salah.

### 2.4 Jadwal Karyawan

Route: `GET /attendance/schedules`, `POST /attendance/schedules`.

Variabel form jadwal:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `employee_id` | integer | Ya | Karyawan yang dijadwalkan. |
| `work_shift_id` | integer | Ya | Template shift. |
| `work_location_id` | integer | Tidak | Lokasi kerja jadwal, override lokasi employee. |
| `scheduled_date` | date | Ya | Tanggal jadwal. |
| `status` | enum | Tidak | Status jadwal. |
| `notes` | text | Tidak | Catatan jadwal. |

Logic BE `AttendanceService::createSchedule()`:

1. Employee dan shift diambil dari database.
2. Work location schedule memakai input atau lokasi employee.
3. Jadwal dibuat untuk tanggal tertentu.
4. Status default mengikuti enum schedule jika tidak diisi.

### 2.5 Check-In dan Check-Out

Route:

- `GET /attendance/check`
- `POST /attendance/check/in`
- `POST /attendance/check/out`

Variabel input:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `method` | enum | Tidak | `login`, `pin`, `qr`, atau `supervisor`. |
| `proof` | file | Tidak | Bukti foto/PDF. |
| `device_info` | string | Tidak | Informasi perangkat. |
| `location_note` | string | Tidak | Catatan lokasi. |
| `notes` | text | Tidak | Catatan absensi. |

Alur FE:

1. User membuka halaman check.
2. Sistem menampilkan employee, jadwal aktif, dan attendance hari ini jika ada.
3. Tombol check-in muncul jika belum check-in.
4. Tombol check-out muncul jika sudah check-in dan belum check-out.
5. User mengisi metode, bukti, perangkat, lokasi, dan catatan jika perlu.

Logic BE:

1. Service mencari employee berdasarkan user login.
2. Service mencari jadwal aktif berdasarkan employee, work location, dan tanggal.
3. Check-in membuat attendance baru, menandai waktu masuk, metode, bukti, device, lokasi, dan status.
4. Jika melewati toleransi, service menghitung late minutes.
5. Check-out mengisi waktu pulang pada attendance aktif.
6. Jika pulang sebelum jam selesai melewati toleransi, service menghitung early leave.
7. Attendance aktif juga dipakai oleh `CashShiftService` saat kasir membuka shift.

### 2.6 Pengajuan Izin dan Koreksi

Route request:

- `GET /attendance/requests`
- `POST /attendance/requests`
- `POST /attendance/requests/{attendanceRequest}/approve`
- `POST /attendance/requests/{attendanceRequest}/reject`

Variabel request:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `type` | enum | Ya | Jenis pengajuan: izin/cuti/sakit/dinas sesuai enum. |
| `start_at` | datetime | Ya | Awal periode. |
| `end_at` | datetime | Ya | Akhir periode, harus setelah start. |
| `reason` | text | Ya | Alasan. |
| `proof` | file | Tidak | Bukti pendukung. |
| `replacement_employee_id` | integer | Tidak | Karyawan pengganti. |

Route correction:

- `GET /attendance/corrections`
- `POST /attendance/corrections`
- `POST /attendance/corrections/{correction}/approve`
- `POST /attendance/corrections/{correction}/reject`

Variabel correction:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `attendance_id` | integer | Ya | Attendance yang dikoreksi. |
| `proposed_check_in_at` | datetime | Tidak | Jam masuk usulan. |
| `proposed_check_out_at` | datetime | Tidak | Jam pulang usulan. |
| `reason` | text | Ya | Alasan koreksi. |
| `proof` | file | Tidak | Bukti pendukung. |

Logic BE:

1. Pengajuan dibuat status pending.
2. Approver menyetujui atau menolak.
3. Jika request cuti/izin disetujui, service dapat membuat attendance leave sesuai periode.
4. Koreksi yang disetujui mengubah jam attendance sesuai proposed value.
5. Koreksi yang ditolak menyimpan catatan penolakan tanpa mengubah attendance.

## 3. Administrasi Sistem

Administrasi sistem dipakai untuk pengaturan umum, sequence nomor dokumen, health check, backup, log aplikasi, import awal, dan maintenance. Sebagian besar route sistem hanya untuk `super_admin`.

### 3.1 Pengaturan Umum

Route:

- `GET /admin/settings/general`
- `PUT /admin/settings/general`

Variabel umum yang lazim dikelola:

| Variabel | Fungsi |
|---|---|
| `app_name` | Nama aplikasi yang muncul di dokumen/tampilan. |
| `company_name` | Nama perusahaan. |
| `company_address` | Alamat perusahaan. |
| `company_phone` | Nomor telepon perusahaan. |
| `company_email` | Email perusahaan. |
| `tax_number` | NPWP atau identitas pajak. |
| `timezone` | Zona waktu operasional. |
| `currency` | Mata uang utama. |

Logic BE:

1. FormRequest memvalidasi nilai.
2. Controller menyimpan ke `system_settings`.
3. Pengaturan dipakai view, cetak dokumen, dan laporan.

### 3.2 Nomor Dokumen

Route:

- `GET /admin/settings/document-numbers`
- `PUT /admin/settings/document-numbers`

Istilah:

| Istilah | Makna |
|---|---|
| Sequence | Counter nomor dokumen per tipe. |
| Prefix | Awalan nomor, contoh PO, GR, RET. |
| Padding | Jumlah digit counter. |
| Reset period | Pola reset harian/bulanan/tahunan jika diterapkan. |

Logic BE:

1. Halaman menampilkan daftar tipe dokumen dari `DocumentNumberService`.
2. Update mengubah konfigurasi sequence.
3. Service transaksi memakai sequence saat membuat nomor dokumen.
4. Perubahan sequence tidak mengubah nomor dokumen lama.

Risiko:

- Mengubah prefix/sequence di tengah periode bisa membuat format dokumen tidak konsisten.
- Jangan reset counter ke angka yang dapat bentrok dengan dokumen existing.

### 3.3 Health, Backup, Log, Import, dan Maintenance

Route penting:

- `GET /admin/system/health`
- `GET /admin/system/backups`
- `POST /admin/system/backups/run`
- `GET /admin/system/backups/download`
- `GET /admin/system/logs`
- `POST /admin/system/logs/failed-jobs/{failedJob}/retry`
- `POST /admin/system/logs/resolve`
- `GET /admin/system/imports`
- `GET /admin/system/imports/templates/{type}`
- `POST /admin/system/imports/preview`
- `GET /admin/system/maintenance`
- `POST /admin/system/maintenance`

Bagian FE:

| Halaman | Fungsi |
|---|---|
| Health | Melihat status aplikasi, database, cache, queue, storage, dan dependency penting. |
| Backups | Melihat katalog backup, menjalankan backup, dan download backup. |
| Logs | Melihat log aplikasi, error, dan failed jobs. |
| Imports | Preview import data awal sebelum commit. |
| Maintenance | Menjalankan aksi maintenance terkontrol. |

Logic BE:

1. `HealthCheckService` mengumpulkan status komponen.
2. `BackupCatalogService` membaca daftar backup dan metadata.
3. `ApplicationLogService` membaca log aplikasi dan failed jobs.
4. `InitialDataImportService` memvalidasi file import dan menghasilkan preview.
5. Maintenance action divalidasi oleh `RunMaintenanceActionRequest`.
6. Route sistem di dalam prefix `admin/system` dilindungi role `super_admin`.

Risiko:

- Backup/download berisi data sensitif; akses harus tetap super admin.
- Retry failed job dapat mengulang efek bisnis; hanya lakukan jika idempotency aman.
- Maintenance jangan dijalankan pada jam transaksi padat.

## 4. Control, Audit, dan Notifikasi

Control dan audit berfungsi mencatat aktivitas sensitif, approval workflow, anomaly alert, audit security, channel notifikasi, template, schedule, recipient, notification log, alert rule, dan secure daily report.

### 4.1 Istilah

| Istilah | Makna |
|---|---|
| Audit log | Catatan aktivitas/event yang penting untuk investigasi. |
| Security audit | Audit event keamanan seperti login, kegagalan login, atau akses sensitif. |
| Approval request | Permintaan persetujuan generik untuk aksi berisiko. |
| Approval step | Langkah approval, approver, status, dan waktu keputusan. |
| Anomaly alert | Alert otomatis ketika sistem mendeteksi pola tidak normal. |
| Risk value | Nilai risiko numerik atau nominal yang dipakai menentukan level. |
| Notification channel | Kanal kirim notifikasi seperti WhatsApp atau Telegram. |
| Notification template | Template pesan dengan placeholder. |
| Notification recipient | Tujuan notifikasi, bisa user/lokasi/nomor tertentu. |
| Secure report token | Token untuk membuka daily report via link aman. |

### 4.2 Audit Log

Route:

- `GET /audit-logs`
- `GET /audit-logs/export`
- `GET /audit-logs/{auditLog}`

Variabel filter:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `q` | string | Cari event, subject, atau metadata. |
| `event` | string | Filter tipe event. |
| `module` | string | Filter modul. |
| `severity` | string | Filter level severity. |
| `user_id` | integer | Filter actor. |
| `date_from` | date | Awal periode audit. |
| `date_to` | date | Akhir periode audit. |

Logic `AuditLogService`:

1. `record()` menyimpan event, actor, subject, work location, IP, user agent, severity, before/after, metadata, dan correlation id.
2. `security()` membuat audit log khusus event keamanan dari request.
3. `redact()` menyamarkan field sensitif.
4. Field seperti password, token, secret, credential, dan sejenisnya tidak boleh tampil mentah.

Bagian FE:

- Index menampilkan filter dan tabel audit.
- Show menampilkan detail event, actor, subject, IP, user agent, before/after, metadata.
- Export menghasilkan CSV untuk investigasi.

### 4.3 Approval Inbox

Route umum control approval berada pada controller `ApprovalInboxController`.

Variabel keputusan:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `comments` | text | Catatan approve/reject. |
| `decision` | enum | Keputusan jika dipakai request gabungan. |

Logic `ApprovalWorkflowService`:

1. `create()` membuat approval request untuk subject tertentu dengan module, requester, risk value, reason, before/after, metadata, permission/role approver, handler key, dan correlation id.
2. Risk level dihitung dari risk value.
3. `approve()` memvalidasi status pending, expiry, dan hak approver.
4. Approval menandai approval request approved, membuat step, mencatat audit, dan menjalankan handler bila ada.
5. `reject()` menandai rejected, mencatat step, dan audit.
6. Approval expired tidak boleh diputuskan.

Risiko:

- Approval handler bisa mengeksekusi perubahan bisnis. Pastikan idempotent.
- Jangan bypass approval workflow untuk aksi yang sudah dikategorikan risk tinggi.

### 4.4 Anomaly Alert

Route:

- Index anomaly.
- Resolve anomaly melalui `AnomalyController@resolve`.

Variabel resolve:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `status` | enum/string | Ya | Status penyelesaian alert. |
| `resolution_note` / `note` | text | Tidak | Catatan investigasi. |

Logic `AnomalyDetectionService`:

1. `flag()` membuat alert dari subject model, rule key, judul, deskripsi, severity, risk value, dan evidence.
2. `detectPriceApproval()` memeriksa approval harga.
3. `detectClosingDifference()` memeriksa selisih closing kas.
4. `detectOverdueReceivables()` membuat alert piutang overdue.
5. `detectLoginFailures()` membuat alert kegagalan login berulang.
6. `resolve()` mengubah status alert dan menyimpan resolver.

### 4.5 Security Audit

Route: halaman security audit berada pada `SecurityAuditController@index`.

Fungsi:

- Melihat ringkasan event keamanan.
- Melihat jumlah event login gagal, akses mencurigakan, alert aktif, dan event high severity.
- Filter event, user, tanggal, dan severity.

Logic:

1. Controller membangun query audit log yang berkaitan dengan security.
2. Summary dihitung dari event dalam periode tertentu.
3. Alert security ditampilkan untuk tindakan cepat.

### 4.6 Notifikasi

Route prefix: `/admin/notifications`.

Halaman:

| Halaman | Route | Fungsi |
|---|---|---|
| Channels | `/channels` | Konfigurasi kanal WA/Telegram/dll. |
| Templates | `/templates` | Template pesan dengan placeholder. |
| Schedules | `/schedules` | Jadwal pengiriman daily report. |
| Recipients | `/recipients` | Penerima notifikasi. |
| Logs | `/logs` | Riwayat pengiriman dan retry. |
| Alerts | `/alerts` | Aturan alert dan preview. |

Variabel channel:

| Variabel | Fungsi |
|---|---|
| `name` | Nama channel. |
| `type` | Jenis channel, misalnya WhatsApp atau Telegram. |
| `credential_data` | Credential/API token, harus disimpan aman dan disamarkan saat audit. |
| `is_active` | Channel dapat dipakai atau tidak. |

Variabel template:

| Variabel | Fungsi |
|---|---|
| `code` | Kode unik template. |
| `name` | Nama template. |
| `channel_type` | Kanal tujuan template. |
| `subject` | Subjek pesan jika kanal mendukung. |
| `content` | Isi pesan dengan placeholder. |
| `variables` | Daftar placeholder yang diizinkan. |
| `is_active` | Template aktif. |

Variabel schedule:

| Variabel | Fungsi |
|---|---|
| `template_id` | Template yang dikirim. |
| `work_location_id` | Scope lokasi report. |
| `frequency` | Frekuensi daily/weekly/monthly sesuai request. |
| `send_time` | Jam kirim. |
| `timezone` | Zona waktu jadwal. |
| `filters` | Filter report yang diserialisasi. |
| `is_active` | Jadwal aktif. |

Logic notifikasi:

1. Template renderer mengganti placeholder dari context.
2. Renderer menolak placeholder yang tidak masuk daftar allowed variables.
3. Dispatch service membuat `notification_logs` sebelum kirim.
4. Pengiriman WA/Telegram memakai channel aktif.
5. Credential dan response payload disamarkan sebelum disimpan.
6. Daily report schedule membuat snapshot `daily_reports`, token aman, lalu queue/send log notifikasi.
7. Secure report link hanya bisa dibuka jika token masih valid dan belum dicabut.

## 5. Invoice, Pembayaran, dan Piutang

Modul ini mengikat B2B fulfillment, pembayaran invoice, verifikasi pembayaran, piutang pelanggan, pembayaran piutang, credit note, collection note, dan limit kredit.

### 5.1 Istilah

| Istilah | Makna |
|---|---|
| Invoice | Tagihan resmi dari order B2B. |
| Invoice item | Detail produk dan nilai pada invoice. |
| Payment | Bukti pembayaran invoice. |
| Payment allocation | Alokasi payment ke invoice. |
| Receivable | Piutang pelanggan dari invoice atau POS kredit. |
| Receivable entry | Mutasi ledger piutang: issue, payment, credit note, adjustment. |
| Receivable payment | Pembayaran yang dialokasikan ke satu atau lebih piutang. |
| Credit note | Pengurang piutang yang membutuhkan approval. |
| Collection note | Catatan follow-up penagihan. |
| Aging bucket | Kelompok umur piutang berdasarkan jatuh tempo. |

### 5.2 Invoice

Route:

- `GET /invoices`
- `POST /invoices/from-b2b/{order}`
- `GET /invoices/{invoice}`
- `GET /invoices/{invoice}/pdf`

Variabel filter invoice:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `q` | string | Cari nomor invoice, nomor order, atau pelanggan. |
| `status` | enum/string | Filter status invoice. |
| `customer_id` | integer | Filter pelanggan. |
| `date_from` | date | Awal tanggal invoice. |
| `date_to` | date | Akhir tanggal invoice. |

Logic issue invoice dari B2B:

1. Order B2B harus valid untuk dibuat invoice.
2. `B2bFulfillmentService::issueInvoice()` membuka transaksi database.
3. Invoice dibuat dari snapshot order dan item.
4. Due date memakai input atau term pelanggan.
5. Invoice item dibuat dari order item.
6. Jika payment preference kredit, receivable dibuat melalui `ReceivableService::createFromInvoice()`.
7. Status order diperbarui sesuai alur fulfillment.

Bagian FE:

- Index menampilkan filter dan daftar invoice.
- Show menampilkan header invoice, pelanggan, order, item, status, total, outstanding, pembayaran, dan aksi PDF.
- PDF menghasilkan dokumen invoice untuk cetak/kirim.

### 5.3 Input dan Verifikasi Pembayaran Invoice

Route:

- `GET /payments/create`
- `POST /payments`
- `GET /payments/{payment}/verify`
- `POST /payments/{payment}/verify`
- `GET /payments/{payment}/proof`

Variabel input pembayaran:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `invoice_id` | integer | Ya | Invoice yang dibayar. |
| `amount` | decimal | Ya | Nominal pembayaran. |
| `method` | enum | Ya | Metode bayar sesuai `PaymentMethod`. |
| `payment_date` | date | Ya | Tanggal pembayaran. |
| `bank_name` | string | Tidak | Bank asal/tujuan. |
| `reference_no` | string | Tidak | Nomor referensi transfer. |
| `payer_name` | string | Tidak | Nama pembayar. |
| `proof` | file | Tidak | Bukti pembayaran. |
| `notes` | text | Tidak | Catatan. |
| `idempotency_key` | string | Tidak | Mencegah double submit. |

Variabel verifikasi:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `decision` | enum | Ya | `approve` atau `reject`. |
| `reject_reason` | text | Wajib jika reject | Alasan penolakan. |

Logic BE:

1. Store membuat payment dengan status menunggu verifikasi.
2. Bukti pembayaran disimpan jika file dikirim.
3. Verify approve mengubah status payment menjadi verified.
4. Payment verified dialokasikan ke invoice dan/atau receivable.
5. Verify reject mengubah status payment menjadi rejected dan menyimpan alasan.
6. Endpoint proof mengembalikan file bukti dengan policy akses.

### 5.4 Piutang

Route prefix: `/receivables`.

Halaman:

| Halaman | Fungsi |
|---|---|
| Dashboard | Ringkasan piutang, overdue, aging, dan collection. |
| Index | Daftar piutang per pelanggan/dokumen. |
| Customer detail | Kartu piutang pelanggan. |
| Payments create/store | Input pembayaran piutang multi-alokasi. |
| Reminders | Catatan penagihan dan follow-up. |
| Credit limits | Kelola limit kredit pelanggan. |
| Adjustments | Credit note/adjustment piutang. |

Variabel pembayaran piutang:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `customer_id` | integer | Ya | Pelanggan pembayar. |
| `method` | enum | Ya | Metode pembayaran. |
| `payment_date` | date | Ya | Tanggal pembayaran. |
| `reference_no` | string | Tidak | Referensi pembayaran. |
| `proof` | file | Tidak | Bukti. |
| `notes` | text | Tidak | Catatan. |
| `idempotency_key` | string | Tidak | Mencegah double submit. |
| `allocations[receivable_id]` | decimal | Ya minimal 1 | Nominal dialokasikan ke piutang tertentu. |

Variabel credit limit:

| Variabel | Fungsi |
|---|---|
| `credit_limit` | Batas kredit. |
| `payment_term_days` | Tempo pembayaran. |
| `approval_threshold_amount` | Ambang nominal yang butuh approval. |
| `max_overdue_days` | Maksimum hari overdue yang masih ditoleransi. |
| `status` | Status limit. |
| `blocked_reason` | Alasan blokir. |
| `notes` | Catatan internal. |

Variabel collection note:

| Variabel | Fungsi |
|---|---|
| `customer_id` | Pelanggan yang dihubungi. |
| `receivable_id` | Piutang spesifik, opsional. |
| `channel` | `manual`, `wa`, `telegram`, `phone`, `visit`. |
| `contact_person` | Orang yang dihubungi. |
| `note` | Catatan penagihan. |
| `next_follow_up_date` | Tanggal follow-up berikutnya. |
| `delivery_status` | Status komunikasi: draft, sent, failed, promised, paid. |

Logic `ReceivableService`:

1. `assertCanUseCredit()` mengecek customer aktif, limit tidak blocked, overdue tidak melewati batas, dan available credit cukup.
2. `createFromInvoice()` membuat piutang dari invoice dan entry awal.
3. `createFromPosSale()` membuat piutang dari POS kredit.
4. `recordPayment()` membuat receivable payment dan alokasi ke beberapa piutang.
5. `applyExternalPayment()` mengurangi outstanding piutang dari sumber payment eksternal.
6. `createCreditNote()` membuat credit note pending.
7. `approveCreditNote()` mengurangi piutang setelah disetujui.
8. `refreshAging()` memperbarui aging bucket berdasarkan due date.
9. `reconcileCustomerBalance()` menyamakan saldo piutang customer dari total receivable outstanding.
10. Status piutang menjadi paid, partial, overdue, atau open sesuai balance dan due date.

Risiko:

- Jangan mengurangi piutang langsung dari field outstanding. Pakai entry ledger.
- Payment yang sudah diverifikasi tidak boleh diedit bebas; gunakan adjustment resmi.
- Credit note harus punya alasan dan approval.

## 6. Laporan dan Dashboard

Laporan mengambil data dari transaksi lintas modul dan difilter berdasarkan tanggal serta lokasi kerja. `ReportMetricService` menjadi pusat perhitungan KPI, dashboard, dan report generic.

### 6.1 Istilah

| Istilah | Makna |
|---|---|
| KPI | Angka ringkasan seperti revenue, margin, stok, cash difference, overdue. |
| Filter report | Tanggal, lokasi kerja, cabang, gudang, customer, channel, atau granularity. |
| Dashboard owner | Ringkasan eksekutif untuk owner/approver. |
| Dashboard warehouse | Ringkasan gudang: stok, mutasi, order, restock. |
| Dashboard retail | Ringkasan toko: POS, shift, margin, transaksi. |
| Report export | Permintaan export report ke format tertentu. |
| Granularity | Level agregasi harian, bulanan, tahunan. |

### 6.2 Route Laporan

| Route | Tipe report | Permission |
|---|---|---|
| `/reports/daily` | Daily report | `reports.view` |
| `/reports/warehouse` | Gudang dan stok | `reports.view|stock.view` |
| `/reports/retail` | Toko/POS | `reports.view|cash_shifts.view|pos.view` |
| `/reports/b2b` | Order langganan | `reports.view|b2b_orders.view` |
| `/reports/pricing` | Harga dan margin | `reports.view|prices.view` |
| `/reports/suppliers` | Performa supplier | `reports.view|suppliers.view` |
| `/reports/losses` | Loss/kerusakan | `reports.view|losses.view` |
| `/reports/attendance` | Kehadiran | `reports.view|attendance.view` |
| `/reports/shift-productivity` | Produktivitas shift | `reports.view|attendance.view` |
| `/reports/receivables` | Piutang | `reports.view|receivables.view` |
| `/reports/audit-notifications` | Audit dan notifikasi | `reports.view|audit.view` |
| `/reports/exports` | Export report | `reports.export|audit.export` untuk route export terkait |

Variabel filter report:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `date_from` | date | Awal periode. |
| `date_to` | date | Akhir periode. |
| `work_location_id` | integer | Scope lokasi kerja. |
| `warehouse_id` | integer | Filter gudang. |
| `branch_id` | integer | Filter cabang/toko. |
| `customer_id` | integer | Filter pelanggan. |
| `supplier_id` | integer | Filter supplier. |
| `granularity` | string | Harian/bulanan/tahunan. |
| `channel` | string | POS/B2B/retail jika report mendukung. |

Logic `ReportMetricService`:

1. `filters()` menormalisasi filter dan membatasi lokasi berdasarkan user.
2. Dashboard owner, warehouse, retail, dan B2B memakai cache 60 detik.
3. `report(type)` memilih method report sesuai tipe.
4. Daily report menggabungkan revenue, transaksi, stok, piutang, dan alert.
5. Warehouse report menghitung stock summary, stock health, movement, top movers, dead stock, restock needed.
6. Retail report menghitung sales, margin, active shifts, payment methods, cash difference, returns.
7. B2B report menghitung order summary, revenue, fulfillment, piutang terkait.
8. Pricing report menghitung perubahan harga, approval harga, margin.
9. Supplier report menghitung PO, goods receipt, lead time/performa.
10. Attendance report menghitung hadir, terlambat, izin, koreksi, produktivitas.
11. Receivable report menghitung issued, outstanding, aging, overdue.
12. Audit notification report menghitung audit event, anomaly, notification status.

### 6.3 Dashboard

Route dashboard:

- `/dashboard`
- `/owner/dashboard`
- `/warehouse/dashboard`
- `/retail/dashboard`
- `/retail/dashboard/data`
- `/warehouse/dashboard/data`
- `/langganan/dashboard`

Bagian FE dashboard:

| Bagian | Fungsi |
|---|---|
| KPI grid | Angka utama sesuai role. |
| Chart revenue/movement | Tren transaksi/stok. |
| Alert list | Warning stok, piutang, anomaly, atau closing. |
| Recent activity | Transaksi terbaru sesuai modul. |
| Quick action | Shortcut ke halaman kerja utama. |

Logic:

1. Dashboard internal memakai `ReportMetricService` dan role/user context.
2. Dashboard retail memiliki endpoint data JSON untuk refresh dashboard.
3. Dashboard B2B memfilter data hanya untuk customer aktif user login.
4. Data sensitif seperti nilai stok atau margin disembunyikan jika user tidak punya permission terkait.

### 6.4 Export Laporan

Route:

- `GET /reports/exports`
- `POST /reports/exports`
- `GET /reports/exports/{export}/download`

Variabel export:

| Variabel | Fungsi |
|---|---|
| `report_type` | Tipe laporan. |
| `format` | Format output, misalnya csv/xlsx/pdf sesuai service. |
| `filters` | Filter report yang disimpan sebagai metadata. |

Logic:

1. `ReportExportService::request()` memvalidasi tipe report.
2. Record `report_exports` dibuat dengan user requester, status, type, format, dan filter.
3. Service menghasilkan snapshot data report.
4. Download memastikan user berhak mengakses export.

## 7. Portal Langganan / B2B

Portal langganan adalah area `/langganan` untuk pelanggan B2B. User portal harus terhubung ke customer B2B aktif, verified, account active, pivot active, dan tidak blocked.

### 7.1 Istilah

| Istilah | Makna |
|---|---|
| Langganan owner | Role pemilik akun pelanggan; bisa mengubah profil. |
| Langganan PIC | User pelanggan yang melakukan order. |
| Active customer | Customer B2B aktif dan terverifikasi. |
| Cart | Keranjang aktif per customer dan user. |
| Availability snapshot | Status stok saat item masuk cart/order. |
| Price snapshot | Harga yang terkunci pada cart/order. |
| Payment preference | Preferensi bayar: cash, transfer, credit. |
| Delivery method | Metode pengiriman: courier, pickup, expedition. |
| Reservation | Stok yang dikunci gudang untuk order B2B. |

### 7.2 Login dan Akses Portal

Route:

- `GET /langganan/login`
- `POST /langganan/login`
- `GET /langganan/forgot-password`
- `POST /langganan/forgot-password`
- `POST /langganan/logout`

Logic BE:

1. Login memakai action autentikasi yang sama dengan internal.
2. Setelah login, `B2bPortalService::activeCustomerFor()` memastikan user punya customer B2B aktif.
3. Middleware `b2b.customer` memastikan user portal tidak masuk tanpa customer aktif.
4. Jika customer belum aktif, belum verified, pivot inactive, atau blocked, akses ditolak.

### 7.3 Katalog Produk

Route:

- `GET /langganan/katalog`
- `GET /langganan/katalog/{product}`
- `POST /langganan/keranjang/add`

Variabel katalog:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `q` | string | Cari SKU/nama/kategori. |
| `category_id` | integer | Filter kategori jika tersedia. |
| `product_id` | integer | Produk yang ditambahkan ke cart. |
| `unit_id` | integer | Satuan order. |
| `quantity` | decimal | Qty order. |
| `notes` | string | Catatan item. |

Logic `B2bPortalService::addToCart()`:

1. Service mengambil cart aktif atau membuat cart baru.
2. Produk harus aktif.
3. Unit harus aktif.
4. Harga dihitung via `PriceResolverService` dengan channel `b2b` dan customer.
5. Harga yang butuh approval ditolak masuk cart.
6. Qty base harus memenuhi minimum order produk.
7. Jika produk-unit sudah ada di cart, qty digabung dan harga dihitung ulang.
8. Cart item menyimpan selected price, line total, price source, availability snapshot, price metadata, dan notes.

### 7.4 Keranjang dan Checkout

Route:

- `GET /langganan/keranjang`
- `PUT /langganan/keranjang`
- `DELETE /langganan/keranjang/items/{item}`
- `POST /langganan/keranjang/checkout`
- `GET /langganan/checkout`
- `POST /langganan/checkout`

Variabel update cart:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `items[n][id]` | integer | Ya | ID cart item. |
| `items[n][quantity]` | decimal | Ya | Qty baru. Jika 0 item dihapus. |
| `items[n][notes]` | string | Tidak | Catatan item. |

Variabel checkout:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `customer_address_id` | integer | Tidak | Alamat kirim pelanggan. |
| `requested_delivery_date` | date | Tidak | Tanggal kirim diminta, tidak boleh sebelum hari ini. |
| `delivery_method` | enum | Ya | `courier`, `pickup`, atau `expedition`. |
| `courier_name` | string | Tidak | Nama kurir/ekspedisi. |
| `payment_preference` | enum | Ya | `cash`, `transfer`, atau `credit`. |
| `notes` | text | Tidak | Catatan order. |
| `idempotency_key` | string | Tidak | Mencegah double order. |
| `terms_accepted` | accepted | Ya | Persetujuan syarat. |

Logic submit order:

1. Customer dilock dan divalidasi aktif.
2. Cart aktif user dilock.
3. Cart di-refresh agar harga dan availability terbaru.
4. Cart kosong ditolak.
5. Alamat kirim harus milik customer jika diisi.
6. Grand total = subtotal cart + shipping cost.
7. Grand total harus memenuhi `customer.minimum_order`.
8. Jika payment preference credit, total setelah order tidak boleh melebihi credit limit.
9. Order dibuat status `PENDING_CONFIRMATION`.
10. Item order menyimpan snapshot SKU, nama produk, unit, conversion factor, qty, base qty, minimum price, selected price, line total, price source, available stock, dan price snapshot.
11. Cart berubah status `SUBMITTED`.
12. Status history dan event `B2bOrderStatusChanged` dibuat.

### 7.5 Order, Reorder, Pengiriman, dan Komplain

Route:

- `GET /langganan/orders`
- `GET /langganan/orders/{order}`
- `POST /langganan/orders/{order}/cancel`
- `POST /langganan/orders/{order}/receive`
- `GET /langganan/reorder`
- `POST /langganan/reorder`
- `GET /langganan/shipments/{shipment}`
- `POST /langganan/shipments/{shipment}/confirm`
- `GET /langganan/complaints`
- `POST /langganan/complaints`

Variabel cancel:

| Variabel | Fungsi |
|---|---|
| `reason` | Alasan pembatalan order. |

Variabel komplain:

| Variabel | Tipe | Wajib | Fungsi |
|---|---:|---|---|
| `b2b_order_id` | integer | Tidak | Order terkait. |
| `shipment_id` | integer | Tidak | Pengiriman terkait. |
| `b2b_order_item_id` | integer | Tidak | Item order terkait. |
| `type` | enum | Ya | `kurang`, `pecah`, `salah_barang`, `lainnya`. |
| `requested_solution` | enum | Tidak | `kirim_pengganti`, `refund`, `credit_note`, `diskusi`. |
| `quantity` | decimal | Tidak | Qty bermasalah. |
| `evidence` | file | Tidak | Foto/video/PDF bukti. |
| `message` | text | Ya | Penjelasan komplain. |

Logic order workflow:

1. Customer hanya bisa melihat order miliknya.
2. Cancel hanya boleh pada status yang masih bisa dibatalkan customer.
3. Cancel me-release reservation aktif.
4. Gudang melakukan reserve dari sisi internal, lalu order menjadi `RESERVED`.
5. Packing hanya dari status reserved/invoice/payment approved sesuai workflow.
6. Ship melepas reservation lalu melakukan issue stok.
7. Jika semua reservation dikirim, order menjadi `SHIPPED`.
8. Customer confirm receive mengubah order menjadi `RECEIVED`.
9. Komplain dibuat dari order/shipment/item milik customer.

State order B2B ringkas:

| Status | Makna |
|---|---|
| `pending_confirmation` | Order baru dari portal. |
| `warehouse_validation` | Order sedang dicek gudang. |
| `reserved` | Stok berhasil direserve. |
| `invoice_ready` | Invoice sudah/siap dibuat. |
| `awaiting_payment` | Menunggu pembayaran. |
| `approved_credit` | Kredit disetujui. |
| `packing` | Barang dipacking. |
| `shipped` | Barang dikirim dan stok issue. |
| `received` | Pelanggan mengonfirmasi terima. |
| `cancelled` | Dibatalkan pelanggan. |
| `rejected` | Ditolak internal. |

### 7.6 Profil Pelanggan

Route:

- `GET /langganan/profil`
- `PUT /langganan/profil`

Variabel profil:

| Variabel | Tipe | Fungsi |
|---|---:|---|
| `business_name` | string | Nama usaha. |
| `pic_name` | string | PIC pelanggan. |
| `whatsapp_number` | string | Nomor WA. |
| `email` | email | Email pelanggan. |
| `business_address` | text | Alamat usaha. |
| `city` | string | Kota. |
| `addresses[n][id]` | integer | ID alamat existing. |
| `addresses[n][label]` | string | Label alamat. |
| `addresses[n][recipient_name]` | string | Penerima. |
| `addresses[n][phone_number]` | string | Nomor penerima. |
| `addresses[n][address]` | text | Alamat kirim. |
| `addresses[n][city]` | string | Kota alamat kirim. |
| `addresses[n][postal_code]` | string | Kode pos. |
| `addresses[n][directions]` | text | Patokan/arahan kurir. |
| `primary_address_index` | integer | Alamat utama. |

Logic:

1. Hanya role `langganan_owner` yang boleh update profil.
2. Data customer diperbarui dalam transaksi.
3. Customer address dibuat/diupdate melalui `CustomerAccessService`.
4. Satu alamat ditandai primary berdasarkan index.

## 8. Hak Akses dan Dampak Modul

| Modul | Permission utama | Dampak |
|---|---|---|
| Administrasi user | `admin.users.view/create/update/export/reset_password/assign_locations` | Mengubah akses seluruh aplikasi. |
| Role permission | `admin.roles.*`, `admin.permissions.view` | Mengubah menu, tombol, route, dan policy. |
| Sistem | `system.health.view`, role `super_admin` | Backup, logs, import, maintenance. |
| Kehadiran | `attendance.view`, `attendance.update`, `attendance.check`, `attendance.approve` | Absensi, shift kasir, laporan produktivitas. |
| Audit | `audit.view`, `audit.export`, `audit.resolve` | Investigasi, anomaly, compliance. |
| Notifikasi | `notifications.view/update/send` | Daily report, reminder, retry log. |
| Invoice | `invoices.view/create`, `receivables.view` | Tagihan B2B dan piutang. |
| Payment | `payments.create`, `payments.verify` | Pembayaran dan pelunasan invoice/piutang. |
| Receivable | `receivables.view/pay/adjust/approve/manage_limits/remind` | Kredit pelanggan, aging, collection. |
| Reports | `reports.view`, `reports.export` | KPI dan export data bisnis. |
| Portal B2B | `b2b_orders.view/create`, role `langganan_owner` | Order pelanggan, profil, komplain. |

## 9. Checklist Pengujian Manual

Administrasi:

1. Buat user baru dengan role dan lokasi kerja tertentu, login, pastikan menu sesuai permission.
2. Ubah lokasi kerja user, pastikan stok/transaksi lokasi lama tidak terlihat lagi.
3. Duplicate role, ubah permission, pastikan role baru tidak mengubah role asal.
4. Nonaktifkan user, pastikan login ditolak.
5. Export user dengan filter, pastikan isi sesuai filter.

Kehadiran:

1. Buat employee terkait user.
2. Buat shift kerja dan jadwal hari ini.
3. Check-in sebelum jam masuk, pastikan status hadir normal.
4. Check-in setelah toleransi, pastikan late minutes terhitung.
5. Check-out sebelum toleransi pulang, pastikan early leave terhitung.
6. Ajukan izin dan approve, pastikan record leave/attendance terbentuk sesuai service.
7. Ajukan koreksi jam dan approve, pastikan attendance berubah.

Sistem:

1. Buka health check sebagai user non-super-admin dan pastikan akses mengikuti permission.
2. Buka backup/log/maintenance sebagai non-super-admin, harus ditolak.
3. Preview import dengan file valid dan invalid.
4. Ubah sequence dokumen di testing, buat dokumen baru, pastikan nomor mengikuti konfigurasi.

Audit dan notifikasi:

1. Lakukan aksi sensitif, pastikan audit log tercatat.
2. Pastikan field sensitif tidak muncul mentah di audit metadata.
3. Buat anomaly dari closing selisih, resolve dengan catatan.
4. Buat template notifikasi dengan placeholder valid lalu preview.
5. Coba placeholder tidak terdaftar, preview harus ditolak.
6. Test channel notifikasi, pastikan notification log dibuat.
7. Retry log failed, pastikan status berubah sesuai hasil retry.

Invoice pembayaran piutang:

1. Issue invoice dari order B2B.
2. Input payment invoice dengan bukti.
3. Reject payment, pastikan invoice/piutang tidak berubah.
4. Approve payment, pastikan allocation, outstanding, dan status invoice/piutang berubah.
5. Buat payment piutang multi-alokasi.
6. Buat credit note, approve, pastikan outstanding turun dan ledger entry tercatat.
7. Update credit limit menjadi blocked, coba transaksi kredit, harus ditolak.
8. Buat collection note dan pastikan muncul di customer/piutang.

Laporan:

1. Buka report harian dengan filter tanggal.
2. Buka report warehouse dengan user lokasi terbatas, pastikan data lokasi lain tidak ikut.
3. Buka report retail sebagai user tanpa akses margin, pastikan nilai sensitif disembunyikan jika view menerapkan masking.
4. Request export report dan download.
5. Cek dashboard retail data JSON, pastikan filter branch bekerja.

Portal langganan:

1. Login user B2B yang customer-nya inactive, harus ditolak.
2. Login customer aktif, buka katalog.
3. Tambah produk dengan qty di bawah minimum order produk, harus ditolak.
4. Checkout dengan total di bawah minimum order customer, harus ditolak.
5. Checkout kredit melewati limit, harus ditolak.
6. Checkout valid, pastikan cart menjadi submitted dan order pending confirmation.
7. Cancel order sebelum diproses, reservation jika ada harus release.
8. Gudang reserve, pack, ship; pelanggan confirm receive.
9. Submit komplain dengan evidence, pastikan terkait customer/order sendiri.

## 10. Catatan Implementasi dan Risiko

- Administrasi user, role, permission, dan lokasi kerja adalah konfigurasi keamanan. Perubahan kecil bisa membuka atau menutup akses transaksi.
- Attendance aktif dipakai oleh shift kasir; perubahan absensi dapat berdampak ke kemampuan kasir membuka shift.
- Audit log harus append-only secara operasional. Jangan menghapus audit untuk “membersihkan” histori.
- Credential notifikasi harus selalu disamarkan dalam audit/log.
- Invoice, payment, receivable, credit note, dan ledger piutang tidak boleh diedit langsung setelah posting. Gunakan adjustment/approval resmi.
- Dashboard dan laporan harus mengikuti scope lokasi user. Jangan membuat query report yang mengabaikan `permittedWorkLocationIds()`.
- Portal B2B harus selalu memvalidasi customer aktif, account status, verification status, pivot active, dan blocked status pada sisi backend.
- Snapshot transaksi pada invoice, cart, order, payment, dan audit sengaja disimpan agar histori tidak berubah saat master data berubah.
