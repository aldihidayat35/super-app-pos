# State Machines

## 1. Aturan umum

- Status disimpan sebagai PHP backed enum dan divalidasi melalui transition service, bukan assignment bebas.
- Setiap transition mencatat actor, waktu, alasan bila diwajibkan, version dokumen, dan audit log.
- Transition yang mengubah stok/uang/reservasi/limit memakai transaksi database, row lock, dan idempotency key.
- Terminal document tidak dapat kembali ke draft. Koreksi menggunakan state/dokumen reversal baru.
- Nama status final dapat disesuaikan sebelum migration, tetapi makna transisi tidak boleh ambigu.

## 2. Purchase Order

`draft -> submitted -> approved -> sent -> partially_received -> fully_received -> completed`

Transisi tambahan:

- `draft|submitted -> cancelled` oleh pembuat/approver sesuai permission.
- `submitted -> rejected -> draft` bila revisi diperbolehkan.
- `approved|sent|partially_received -> cancelled` hanya untuk sisa qty yang belum diterima dan dengan alasan/approval.
- `fully_received -> completed` setelah rekonsiliasi biaya/dokumen.

Larangan: receipt pada PO belum approved/sent; received qty melebihi toleransi; edit item setelah receipt tanpa change order.

## 3. Goods Receipt

`draft -> inspecting -> submitted -> approved -> posted`

- `submitted -> rejected -> draft` untuk koreksi.
- `draft|inspecting -> cancelled` jika belum posting.
- `posted -> reversed` melalui reversal receipt, bukan edit/delete.

Efek `posted`: lock PO dan stock, tambah accepted stock, pisahkan rejected/damaged, buat mutations, update received qty dan HPP sesuai metode terpilih.

## 4. Stock Transfer

`draft -> submitted -> approved -> picking -> packed -> dispatched -> partially_received -> received -> completed`

- `draft|submitted -> cancelled` tanpa mutation keluar.
- `submitted -> rejected -> draft`.
- Setelah `dispatched`, pembatalan langsung dilarang; gunakan return-to-origin/correction transfer.
- Selisih receipt menghasilkan discrepancy yang membutuhkan resolusi/approval sebelum `completed`.

Efek dispatch/receipt dan in-transit stock menunggu keputusan OQ-05.

## 5. POS Sale

`draft -> priced -> payment_pending -> completed`

- `draft|priced|payment_pending -> cancelled` tanpa posting final.
- `completed -> void_pending -> void_approved -> voided` dengan reversal payment/stock.
- `void_pending -> void_rejected -> completed`.
- `completed -> partially_returned|returned` melalui Return terpisah.

Syarat complete: shift aktif, stok cukup, harga valid/approval aktif, total payment sesuai kebijakan, mutation dan payment posted atomik.

## 6. Cash Shift

`scheduled -> opened -> closing_submitted -> verified -> closed`

- `closing_submitted -> reopened` hanya supervisor berizin, sebelum verified, dengan alasan.
- `closing_submitted -> rejected -> opened` untuk recount.
- `closed -> corrected` hanya melalui adjustment/reconciliation document; transaksi asal tetap locked.

Syarat buka: attendance aktif atau override supervisor. Syarat close: semua transaksi final, expected totals dihitung, actual count diinput, variance dan handover dicatat.

## 7. B2B Order

`draft -> submitted -> warehouse_validation -> reserved -> confirmed -> invoiced -> fulfillment -> shipped -> delivered -> completed`

- `warehouse_validation -> revision_requested -> submitted`.
- `warehouse_validation -> rejected`.
- `submitted|warehouse_validation|reserved|confirmed -> cancelled`, dengan release reservation tepat sekali.
- `invoiced -> payment_pending` untuk prepaid; kembali ke fulfillment setelah paid/credit approved.
- `shipped|delivered|completed -> return_requested` melalui Return.
- Partial fulfillment membentuk backorder hanya jika OQ-07 menyetujuinya.

Validasi kredit dan stock reservation dilakukan atomik saat transisi yang disepakati (OQ-07/OQ-09).

## 8. Shipment

`draft -> picking -> packed -> ready_to_ship -> dispatched -> in_transit -> delivered -> completed`

- `draft|picking|packed|ready_to_ship -> cancelled` dan release alokasi yang belum dikonsumsi.
- `in_transit -> delivery_failed -> ready_to_ship|returned_to_origin`.
- `delivered -> discrepancy_reported` bila bukti/qty bermasalah; resolusi menuju `completed` atau Return.

Dispatch wajib mencatat courier, waktu, surat jalan/resi, dan handover. Delivery wajib evidence sesuai kebijakan.

## 9. Return

`draft -> submitted -> inspected -> approved -> resolution_pending -> completed`

- `submitted|inspected -> rejected`.
- `draft|submitted -> cancelled` sebelum efek finansial/stok.
- Resolution: `refund`, `replacement`, `credit_note`, `repair`, `supplier_return`, atau `write_off` sesuai sumber/kondisi.
- Return selesai membuat mutation ke sellable/damaged/quarantine dan efek billing melalui dokumen koreksi, bukan edit transaksi asal.

## 10. Stock Opname

`draft -> scheduled -> frozen -> counting -> reconciled -> approval_pending -> approved -> posted -> completed`

- `scheduled -> counting` jika freeze tidak digunakan.
- `approval_pending -> rejected -> counting|reconciled`.
- `draft|scheduled -> cancelled`.
- `posted` membuat adjustment mutations; tidak boleh recount/edit. Koreksi memakai opname/adjustment baru.

## 11. Receivable

`pending -> open -> partially_paid -> paid`

State waktu/risiko:

- `open|partially_paid -> overdue` berdasarkan due date.
- `overdue -> partially_paid|paid` saat payment allocation.
- `open|partially_paid|overdue -> disputed` dan kembali setelah resolusi.
- `open|partially_paid|overdue -> written_off_pending -> written_off` hanya approval; recovery berikutnya tetap dicatat.
- Credit note/reversal dapat menuju `settled` bila balance nol.

Balance tidak diubah manual; berasal dari invoice, payment allocation, reversal, dan credit note.

## 12. Approval

`requested -> pending -> approved|rejected|expired|cancelled`

- Multi-level bila diperlukan: `pending -> level_1_approved -> level_2_pending -> approved`.
- Requester dapat cancel selama belum diputuskan.
- Keputusan final immutable; perubahan memerlukan approval request baru.
- Approval harus terikat snapshot target/action/value/threshold agar perubahan dokumen tidak memakai approval lama.

## 13. Ringkasan efek samping

| Transition | Efek wajib |
|---|---|
| Receipt posted | stock + mutation + PO received + HPP event |
| Transfer dispatched/received | mutation/in-transit + destination receipt + discrepancy |
| POS completed | stock mutation + payment/receivable + shift totals + HPP snapshot |
| B2B reserved/cancelled | reservation create/release |
| Shipment dispatched | consume reservation/stock sesuai keputusan bisnis |
| Return completed | stock condition mutation + refund/credit note/replacement |
| Opname posted | adjustment mutation + audit + approval reference |
| Payment posted/reversed | allocation + receivable balance + cash/bank movement |
| Shift closed | lock transaction set + variance + verifier audit |

