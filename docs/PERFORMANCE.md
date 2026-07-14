# Performance Hardening — P27

Dokumen ini mencatat audit performa dan optimasi baseline untuk GudangToko setelah modul P01-P26.

## Ringkasan hasil

| Area | Status | Bukti/Fix |
|---|---:|---|
| Pagination server-side | Selesai | Halaman daftar utama memakai pagination/query builder; DataTable/export berat diarahkan ke server-side/job. |
| N+1 baseline | Selesai sebagian | Controller utama memakai eager load pada halaman detail/list kritis. P27 menambahkan checklist manual untuk halaman report besar. |
| Index komposit | Selesai | Migration `2026_07_14_231500_add_security_performance_indexes.php` menambah index untuk audit, stock mutations, POS sales, B2B orders, receivables, approval requests. |
| Queue untuk pekerjaan berat | Selesai sebagian | Notification, report export, PDF/export tertentu memakai job/queue. Export besar wajib tetap asynchronous. |
| Cache permission/menu/KPI | Selesai sebagian | Spatie permission cache tersedia. KPI dashboard/report masih perlu cache TTL pendek per role/lokasi saat data production membesar. |
| Chunk/cursor | Tersedia pada export tertentu | Export audit menggunakan streaming cursor style (`each`). Export baru wajib memakai chunk/cursor. |
| Health observability | Selesai | `SYS-01 /admin/system/health` mengecek DB/cache/session/queue/scheduler/storage/runtime folder. |

## Index yang ditambahkan

| Tabel | Index | Tujuan |
|---|---|---|
| `audit_logs` | `actor_user_id, occurred_at` | Filter AUD-03 per user dan waktu. |
| `audit_logs` | `ip_address, occurred_at` | Analisis login gagal/rate limit per IP. |
| `audit_logs` | `severity, occurred_at` | Filter severity audit dan anomali. |
| `stock_mutations` | `work_location_id, occurred_at, mutation_type` | Kartu stok/filter mutasi per lokasi, tanggal, jenis. |
| `stock_mutations` | `mutation_type, occurred_at` | Dashboard gudang dan laporan masuk/keluar. |
| `pos_sales` | `work_location_id, status, completed_at` | Laporan toko per lokasi/status/periode. |
| `pos_sales` | `cash_shift_id, status` | Rekonsiliasi shift kasir. |
| `b2b_orders` | `status, submitted_at` | Queue order dan laporan B2B per status/periode. |
| `b2b_orders` | `reservation_expires_at, status` | Scheduler release reserved stock. |
| `receivables` | `work_location_id, status, due_date` | Aging piutang gudang/toko per lokasi. |
| `receivables` | `aging_bucket, status, due_date` | Dashboard aging dan reminder. |
| `approval_requests` | `module, current_status, created_at` | Kotak masuk approval dan laporan kontrol. |
| `approval_requests` | `requester_user_id, current_status` | Riwayat approval requester. |

## Query dan halaman prioritas

### AUD-03 Audit Keamanan

- Eager load `actor`.
- Filter memakai kolom indexed: `module`, `event`, `severity`, `actor_user_id`, `ip_address`, `occurred_at`.
- Pagination 20 baris.

### SYS-01 Health Sistem

- Hanya menjalankan probe ringan: `select 1`, cache put/get, cek config session/queue, storage link, permission folder.
- Tidak membaca tabel besar.

### Dashboard/report besar

- KPI harian sebaiknya memakai cache 1-5 menit per role/lokasi.
- Export besar wajib queue/job dan download hasil, bukan request sinkron panjang.
- Query report harus selalu membatasi periode tanggal default.

## Standar implementasi performa lanjutan

1. Setiap halaman index wajib pagination server-side.
2. Setiap filter umum (`status`, `date`, `location`, `reference`, `customer`, `supplier`) harus punya index yang cocok.
3. Hindari `with()` berlebihan; pilih relasi yang tampil saja.
4. Hindari aggregate berat per baris; gunakan subquery terindeks atau precomputed KPI jika data besar.
5. Gunakan `chunkById()`/cursor untuk export, notifikasi massal, dan job rekonsiliasi.
6. File PDF/Excel besar wajib queue.
7. Jalankan `EXPLAIN` pada query report lambat di staging dengan data realistis.
8. Tambahkan batas periode default pada report finansial/stok agar tidak full scan.

## Evidence test

- Test P27 memverifikasi route health/audit dan middleware keamanan.
- Test P09-P26 tetap memverifikasi jalur kritis inventory, purchase, receipt, transfer, POS, B2B, invoice, receivable, attendance, approval, notification, dan E2E seed.
- Setelah migration P27 dijalankan, database memiliki index tambahan untuk filter/report kritis.

## Risiko tersisa

- Belum ada benchmark query count browser/Dusk. Perlu profiling dengan data production-like.
- Cache KPI dashboard belum menyeluruh; risiko sedang saat transaksi dan laporan sudah besar.
- Index tambahan mempercepat read tetapi menambah biaya write. Pantau slow query dan write latency di tabel `stock_mutations` dan `pos_sales`.
