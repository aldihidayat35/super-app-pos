# Backlog Pengembangan

## 1. Prinsip prioritas

- **Must**: wajib untuk operasi aman atau dependency langsung MVP.
- **Should**: bernilai tinggi tetapi dapat masuk sesudah alur inti stabil.
- **Could**: optimasi/advance; tidak memblokir go-live MVP.
- Fase berikutnya tidak dimulai sebelum acceptance criteria fase aktif lulus dan keputusan bisnis pemblokir disetujui.

## 2. Fase 0 - Kontrak, fondasi, dan keputusan

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Setujui SRS, domain, state machine, ERD | Stakeholder | Rework besar | Semua OQ pemblokir diberi keputusan/ditunda eksplisit; dokumen disetujui |
| Must | Tetapkan metode HPP, stock timing, reservasi, approval threshold | OQ-02..09 | Saldo/margin salah | Contoh numerik dan edge case ditandatangani owner |
| Must | Baseline auth, locale, timezone, error language | Keputusan OQ-01 | Waktu laporan salah | Semua UI/validasi Indonesia; timezone test lulus |
| Must | Strategi migration/import dan opening stock | Template data | Data awal kotor | Dry-run import menghasilkan rejection report dan opening mutation |
| Should | Tetapkan RPO/RTO dan retention | Infrastruktur | Kehilangan data | Restore drill terdokumentasi |

Exit criteria: kontrak disetujui, tidak ada keputusan pemblokir untuk Fase 1.

## 3. Fase 1 - Identity, organisasi, dan master data

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Login, user, role, permission granular | F0 | Privilege escalation | Policy test menolak cross-branch/cross-warehouse dan aksi tanpa permission |
| Must | Warehouse, branch, assignment scope | RBAC | Data bocor | User hanya melihat lokasi assignment; owner sesuai izin global |
| Must | Product, category, unit, conversion, barcode | Keputusan rounding | Qty salah | SKU/barcode unik; conversion base-unit tervalidasi; decimal test lulus |
| Must | Supplier dan B2B/customer master | Scope | Duplikasi | Unique/contact rules, status inactive, pagination/filter berfungsi |
| Should | Warehouse zone/rack/bin | Warehouse | Lokasi salah | Lokasi unik dalam warehouse dan kapasitas opsional tervalidasi |
| Could | Import master asynchronous | Template data | Data buruk | Preview, validation report, idempotent import, hanya local seeder untuk demo |

Exit criteria: CRUD aman, scope policy lulus, tidak ada N+1 pada list utama.

## 4. Fase 2 - Procurement, inventory ledger, dan pricing core

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | PO lifecycle dan approval | Master | PO berubah setelah receipt | Transition invalid ditolak; partial receipt tracked |
| Must | Goods Receipt + QC + HPP | PO, OQ-02 | Double posting | Concurrent retry memposting sekali; stock/HPP/PO konsisten dalam satu transaction |
| Must | Stock balance + append-only mutation | Product/location | Stok negatif/race | Concurrent decrement tidak pernah negatif; mutation before/after cocok saldo |
| Must | Available/reserved/blocked stock | Ledger, OQ-07 | Overselling | Dua reservasi concurrent tidak melebihi available; cancel melepas tepat sekali |
| Must | Minimum price dan price ring | HPP, OQ-03/04 | Jual rugi | Harga bawah minimum ditolak/approval sesuai policy; snapshot tersimpan |
| Must | Stock card dan audit | Mutation | Audit tidak lengkap | Semua perubahan dapat ditelusuri ke dokumen/user/lokasi |
| Should | Price history dan HPP-change alert | Pricing | Harga basi | Old/new/reason/actor tersimpan; affected products terdaftar |
| Could | Batch/lot | OQ-13 | Scope membesar | Implement hanya bila diwajibkan go-live |

Exit criteria: unit test kalkulasi HPP/harga dan feature test concurrency/negative stock lulus.

## 5. Fase 3 - Transfer dan stock opname

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Restock request dan stock transfer | Inventory, OQ-05 | Stok hilang di perjalanan | Dispatch/receipt menghasilkan ledger yang seimbang; partial/discrepancy tercatat |
| Must | Konfirmasi cabang | Transfer | Penerimaan fiktif | Receiver, timestamp, evidence, qty diterima dan selisih tercatat |
| Must | Stock opname dan adjustment approval | Inventory, approval threshold | Koreksi fraud | Freeze opsional; variance beralasan; hanya approved posting mutation |
| Should | Barcode counting/import | Product | Duplikasi hitung | Duplicate scan policy jelas; validation report tersedia |
| Should | Damaged/quarantine stock | Inventory | Available salah | Stock rusak tidak masuk available; disposal/recovery diaudit |

Exit criteria: skenario partial, lost/damaged, reject, reversal, dan concurrent stock lulus.

## 6. Fase 4 - Retail POS, shift, dan piutang toko

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Attendance minimum + POS shift open | Employee/schedule | User tak bertugas membuka POS | Shift ditolak tanpa attendance/override yang tercatat |
| Must | POS cart, pricing, payment, receipt | Stock/pricing | Double sale/payment | Complete atomik dan idempotent; stock tidak negatif; receipt bernomor unik |
| Must | Cash shift closing | POS | Selisih kas dimanipulasi | Expected vs actual per metode; variance; verifier; setelah closed terkunci |
| Must | Void/reversal dan retail return | Approval | Audit hilang | Tidak delete; reversal stock/payment terkait transaksi asal dan beralasan |
| Must | Credit POS/receivable/payment | Billing, OQ-09/11 | Limit terlampaui | Exposure concurrent tidak melebihi limit; partial payment dan aging benar |
| Should | Expense/petty cash per shift | Shift | Closing salah | Bukti dan approval threshold; included in expected cash |
| Could | Printer/barcode hardware integration | Perangkat | Vendor lock-in | Ditunda sampai perangkat dipilih |

Exit criteria: test price boundary, concurrent sale, mixed payment, credit limit, closing lock, void, dan return lulus.

## 7. Fase 5 - B2B, shipment, dan piutang gudang

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Portal scoped B2B dan katalog assigned ring | RBAC/pricing | Margin/data bocor | B2B hanya melihat account sendiri, available indicator, dan harga assigned |
| Must | Cart/order/reservation lifecycle | Inventory | Overselling | Submit/reserve/release idempotent; revision dan cancel konsisten |
| Must | Invoice dan prepaid/credit validation | Billing | Piutang salah | Invoice immutable; due date/limit/overdue block diuji |
| Must | Picking, packing, shipment, proof of delivery | Order/transfer | Qty/bukti tidak cocok | Shipped qty <= allocated; evidence validated; failure path tersedia |
| Must | B2B return/refund/credit note | Return policy | Saldo salah | Resolution mengoreksi stock dan receivable tanpa edit invoice asal |
| Should | Multiple delivery address/reorder | Account | Salah kirim | Address ownership policy dan snapshot alamat pada order |
| Could | Customer reminder otomatis | Notification | Spam/consent | Hanya setelah policy consent/template disetujui |

Exit criteria: end-to-end order cash/credit, partial/cancel, shipment failure, return, dan cross-account authorization lulus.

## 8. Fase 6 - Approval, audit, reporting, owner dashboard

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Generic approval with snapshot/expiry | Semua transaksi | Approval lama dipakai ulang | Target version/value diverifikasi; self-approval ditolak sesuai policy |
| Must | Audit log dan anomaly views | Semua transaksi | PII/secret bocor | Critical actions lengkap; secret masked; audit tidak editable via UI |
| Must | Stock, sales, margin, receivable reports | Stable ledger | Angka berbeda | Report totals reconcile dengan ledger pada fixture yang disepakati |
| Must | Owner dashboard awal | Reports | Query berat | KPI scoped/date-consistent; p95 target; drill-down tersedia |
| Should | Async XLSX/PDF export | Queue/package decision | Timeout | Job authorized, file expiry, status progress, audit download |
| Should | Price/void/variance anomaly alerts | Audit | False positive | Threshold configurable dan dapat ditelusuri |

Exit criteria: reconciliation suite lulus dan owner menyetujui sampel laporan.

## 9. Fase 7 - Notifikasi dan kehadiran lengkap

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Should | WA/Telegram encrypted configuration | Provider/OQ-16 | Secret leak | Secret encrypted/masked; test send logged |
| Should | Daily report scheduler + secure link | Reporting/queue | Link bocor | Token hashed, expires, permission checked, delivery/retry logged |
| Should | Attendance full lifecycle | Employee/OQ-12 | Titip absen | Schedule, status, evidence, correction approval, overnight test lulus |
| Should | Receivable reminders | Billing/consent | Salah penerima | Due calculation correct; recipient/template/delivery log auditable |
| Could | Productivity analytics | Stable attendance/POS | Interpretasi keliru | Metric definition disetujui dan dapat drill-down |

Exit criteria: retry/idempotency/security test notifikasi dan attendance correction test lulus.

## 10. Fase 8 - Hardening dan go-live

| Pri | Backlog | Dependensi | Risiko | Acceptance criteria |
|---|---|---|---|---|
| Must | Security/performance review | Semua MVP | Insiden produksi | Authz matrix, upload, rate limit, query, queue, secret review selesai |
| Must | Opening stock/import rehearsal | Data bisnis | Saldo awal salah | Owner sign-off reconciliation fisik vs opening mutation |
| Must | Backup/restore and rollback drill | Infra | Downtime/data loss | RPO/RTO tercapai dan bukti restore tersedia |
| Must | UAT per role dan SOP Indonesia | Semua MVP | Adopsi gagal | Checklist aktor lulus; SOP void/return/opname/closing disetujui |
| Must | Production seeding guard | Deploy | Data palsu production | Demo seeder menolak production; hanya master resmi diimport |
| Should | Observability dashboard | Queue/logging | Error terlambat diketahui | Alert error, failed job, slow query, disk usage tersedia |

## 11. Test minimum lintas fase

- Feature: authorization per role dan cross-location isolation.
- Feature: concurrent receipt/reservation/sale/transfer/payment/credit-limit.
- Feature: invalid state transition dan immutable closed document.
- Feature: append-only mutation/payment/audit serta reversal.
- Unit: unit conversion, HPP, minimum/maximum price, discount, invoice totals, aging, cash variance.
- Reconciliation: stock balance vs mutations; receivable vs invoice/allocation; closing vs payments/movements.
- UI smoke: Metronic layout, permission sidebar, error/empty/loading states, responsive table, pagination/filter.

## 12. Risiko utama program

| Risiko | Mitigasi |
|---|---|
| Data awal tidak bersih | Template, preview, reject report, dry-run, opening opname |
| Scope terlalu besar | Gate per fase dan larangan lanjut sebelum acceptance |
| Ketidakjelasan metode HPP/stock timing | Putuskan dengan contoh numerik sebelum migration transaksi |
| Race condition stok/kredit | DB transaction, row lock, idempotency, concurrency tests |
| Internet pasar tidak stabil | Network assessment; PWA/offline sebagai fase terpisah |
| Manipulasi harga/void/retur | Permission granular, threshold approval, immutable audit |
| Laporan tidak reconcile | Ledger sebagai source, snapshot, automated reconciliation |
| Package/vendor lock-in | Adapter interface dan keputusan package setelah spike terbatas |

