# Testing GudangToko

Dokumen ini menjadi kontrak kualitas P26 untuk memastikan alur bisnis utama tetap aman saat modul saling terhubung.

## Command utama

```bash
composer lint
composer analyse
composer test
composer quality
composer test:critical
```

Command CI yang disiapkan:

```bash
composer ci
```

`composer ci` menjalankan:

1. `php artisan config:clear`
2. `php artisan migrate:fresh --seed --seeder=TestingScenarioSeeder --env=testing --force`
3. Pint
4. PHPStan/Larastan
5. Full automated test

Jalankan command CI hanya pada database testing/CI. Jangan jalankan pada database local yang berisi pekerjaan manual, dan tidak boleh dijalankan pada production.

## Seed dan akun testing

Seeder khusus:

```bash
php artisan db:seed --class=TestingScenarioSeeder --env=testing
```

Seeder ini hanya boleh berjalan di environment `local` atau `testing`.

Akun deterministic:

| Role | Email | Password |
| --- | --- | --- |
| super_admin | testing-admin@gudangtoko.test | password |
| owner_approver | testing-owner@gudangtoko.test | password |
| kepala_gudang | testing-gudang@gudangtoko.test | password |
| staff_gudang | testing-staff-gudang@gudangtoko.test | password |
| purchasing | testing-purchasing@gudangtoko.test | password |
| kepala_toko | testing-retail@gudangtoko.test | password |
| kasir | testing-kasir@gudangtoko.test | password |
| langganan_owner | testing-b2b-owner@gudangtoko.test | password |

Seeder juga menyiapkan konfigurasi notifikasi dry-run:

- channel WA testing;
- template daily report;
- recipient owner;
- schedule daily report;
- alert rule stok kritis.

## Test pyramid

### Unit/service calculation

- `tests/Unit/Services/CriticalBusinessCalculationTest.php`
  - PriceResolver below-minimum dan unit conversion.
  - InventoryService receive/reserve/release/issue dan available stock.
  - Receivable aging bucket deterministic.
  - CashShift closing formula: cash, non-cash, refund, expense.

### Feature/state transition

Test existing per modul tetap menjadi guard utama:

- `tests/Feature/Warehouse/InventoryCoreTest.php`
- `tests/Feature/Warehouse/GoodsReceiptWorkflowTest.php`
- `tests/Feature/Warehouse/StockTransferWorkflowTest.php`
- `tests/Feature/Warehouse/StockOpnameWorkflowTest.php`
- `tests/Feature/Retail/PosWorkflowTest.php`
- `tests/Feature/Retail/CashShiftClosingTest.php`
- `tests/Feature/B2B/B2bOrderReservationTest.php`
- `tests/Feature/B2B/B2bFulfillmentTest.php`
- `tests/Feature/Receivables/ReceivableLedgerTest.php`
- `tests/Feature/Attendance/AttendanceShiftTest.php`
- `tests/Feature/Control/ApprovalAuditAnomalyTest.php`
- `tests/Feature/Notifications/NotificationDailyReportTest.php`

### End-to-end business journey

- `tests/Feature/EndToEnd/CriticalBusinessJourneyTest.php`
  - PO approved → receipt posted → HPP moving average → stock warehouse.
  - Transfer warehouse → branch dengan reserve/release/ship/receive.
  - POS dari stok cabang → stock issue.
  - Closing shift → expected cash → approval.

## Matrix skenario P26

| Skenario | Test utama |
| --- | --- |
| E2E-01 Supplier → PO → receipt parsial/full → HPP → stok/kartu stok | `GoodsReceiptWorkflowTest`, `CriticalBusinessJourneyTest` |
| E2E-02 Restock cabang → approve → pick/pack → ship → receive → saldo dua lokasi | `StockTransferWorkflowTest`, `CriticalBusinessJourneyTest` |
| E2E-03 Check-in → buka shift → POS → return/void → expense → closing → approval | `AttendanceShiftTest`, `PosWorkflowTest`, `CashShiftClosingTest`, `CriticalBusinessCalculationTest`, `CriticalBusinessJourneyTest` |
| E2E-04 B2B catalog → cart → checkout → reservation → invoice → payment → shipment → proof receive | `B2bPortalTest`, `B2bOrderReservationTest`, `B2bFulfillmentTest` |
| E2E-05 Opname → variance → approval → adjustment → audit/anomaly | `StockOpnameWorkflowTest`, `ApprovalAuditAnomalyTest` |
| E2E-06 Piutang → aging → reminder → partial allocation → credit note → balance reconciliation | `ReceivableLedgerTest`, `CriticalBusinessCalculationTest` |
| E2E-07 Daily report → scheduler → WA/Telegram fake → secure token → delivery log | `NotificationDailyReportTest` |
| Multi-location authorization dan customer isolation | `StockTransferWorkflowTest`, `B2bPortalTest`, `B2bFulfillmentTest`, `UserRbacManagementTest` |
| Cancellation/expiry releases reserved stock exactly once | `B2bOrderReservationTest`, `StockTransferWorkflowTest`, `InventoryCoreTest` |
| Void/return/reversal keeps ledger consistent | `PosWorkflowTest`, `ReturnAndLossWorkflowTest`, `CashShiftClosingTest` |
| Concurrency stock/reservation/numbering/credit-limit | `InventoryCoreTest`, `PurchaseOrderWorkflowTest`, `B2bOrderReservationTest`, `ReceivableLedgerTest` |

## Quality gate sebelum merge/deploy

Wajib hijau:

```bash
composer quality
```

Untuk jalur cepat saat mengubah service kritis:

```bash
composer test:critical
```

Untuk CI database bersih:

```bash
APP_ENV=testing composer ci
```

## Known gaps

- Test browser JavaScript penuh belum memakai Laravel Dusk/Playwright; saat ini coverage UI memakai feature HTTP dan validasi HTML Blade.
- Coverage percentage belum dipaksa threshold karena proyek belum mengaktifkan driver coverage di CI.
- Integrasi WA API real tidak dites terhadap vendor spesifik; test memakai HTTP fake dan provider generic.
- Parallel test belum dijadikan default karena beberapa skenario sengaja menguji nomor dokumen/idempotency dan perlu evaluasi isolation per database CI.
