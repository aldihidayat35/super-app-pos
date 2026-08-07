@extends('layouts.metronic.app')

@section('title', 'Detail ' . $purchaseOrder->number)
@section('page_title', 'Detail dan Approval PO')

@section('toolbar_actions')
    <a href="{{ route('purchasing.purchase-orders.print', $purchaseOrder) }}" class="btn btn-light-success" title="Cetak Purchase Order">
        <i class="ki-outline ki-printer fs-5 me-2"></i>Cetak
    </a>
    <a href="{{ route('purchasing.purchase-orders.print', [$purchaseOrder, 'download' => 'pdf']) }}" class="btn btn-light-primary" title="Unduh PDF Purchase Order">
        <i class="ki-outline ki-file-down fs-5 me-2"></i>PDF
    </a>
    <a href="{{ route('purchasing.purchase-orders.export-one', $purchaseOrder) }}" class="btn btn-light-info" title="Unduh data Purchase Order dalam Excel">
        <i class="ki-outline ki-file-down fs-5 me-2"></i>Excel
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="purchasing-po-show" title="Panduan Detail Purchase Order">
        <x-slot:function><p>Purchase Order adalah pesanan resmi perusahaan kepada supplier. Halaman ini menampilkan dokumen, nilai, item, status, dan tindakan yang valid untuk pengguna saat ini.</p></x-slot:function>
        <x-slot:workflow><ol><li>Purchasing melengkapi PO draft dan mengajukannya.</li><li>Approver menyetujui PO.</li><li>Purchasing mengirim PO ke supplier.</li><li>Gudang mencatat penerimaan sampai selesai.</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Aksi berubah mengikuti status, permission, dan kondisi penerimaan. PO yang sudah menerima barang tidak dapat dibatalkan.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $status = $purchaseOrder->status;
        $totalOrdered = (float) $purchaseOrder->orderedQuantity();
        $totalReceived = (float) $purchaseOrder->receivedQuantity();
        $totalOutstanding = max($totalOrdered - $totalReceived, 0);
        $progressPercent = $totalOrdered > 0 ? min(100, ($totalReceived / $totalOrdered) * 100) : 0;
        $historyStatuses = $purchaseOrder->statusHistories->pluck('to_status')->all();
        $processSteps = [
            ['status' => 'draft', 'label' => 'Draft', 'description' => 'PO disiapkan oleh Purchasing.'],
            ['status' => 'submitted', 'label' => 'Diajukan', 'description' => 'PO menunggu persetujuan.'],
            ['status' => 'approved', 'label' => 'Disetujui', 'description' => 'PO boleh dikirim atau diterima.'],
            ['status' => 'sent_to_supplier', 'label' => 'Dikirim', 'description' => 'Pesanan telah dikirim ke supplier.'],
            ['status' => 'partially_received', 'label' => 'Penerimaan', 'description' => 'Barang diterima sebagian oleh gudang.'],
            ['status' => 'completed', 'label' => 'Selesai', 'description' => 'Seluruh barang telah diterima.'],
        ];
        [$processHolder, $processNext] = match ($status) {
            \App\Enums\PurchaseOrderStatus::DRAFT => ['Purchasing dengan izin purchase_orders.create', 'Lengkapi data dan ajukan PO untuk persetujuan.'],
            \App\Enums\PurchaseOrderStatus::SUBMITTED => ['Pengguna dengan izin purchase_orders.approve', 'Tinjau nilai dan item, lalu setujui PO.'],
            \App\Enums\PurchaseOrderStatus::APPROVED => ['Purchasing atau petugas penerimaan yang berwenang', 'Kirim PO ke supplier atau catat penerimaan saat barang tiba.'],
            \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER => ['Petugas dengan izin goods_receipts.create', 'Catat barang yang diterima dari supplier.'],
            \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED => ['Petugas dengan izin goods_receipts.create', 'Lanjutkan penerimaan untuk jumlah yang masih outstanding.'],
            \App\Enums\PurchaseOrderStatus::COMPLETED => ['Proses selesai', 'Tidak ada tindakan transaksi lanjutan pada PO.'],
            \App\Enums\PurchaseOrderStatus::CANCELLED => ['Proses dihentikan', 'PO telah dibatalkan dan tidak dapat diproses lagi.'],
        };
        $canEdit = auth()->user()?->can('update', $purchaseOrder) ?? false;
        $canApprove = auth()->user()?->can('approve', $purchaseOrder) ?? false;
        $canSend = auth()->user()?->can('send', $purchaseOrder) ?? false;
        $canCancel = auth()->user()?->can('cancel', $purchaseOrder) ?? false;
        $canReceive = (auth()->user()?->can('goods_receipts.create') ?? false)
            && in_array($status, [\App\Enums\PurchaseOrderStatus::APPROVED, \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER, \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED], true);
        $hasAction = $canEdit || $canApprove || $canSend || $canCancel || $canReceive;
    @endphp

    @if ($status === \App\Enums\PurchaseOrderStatus::CANCELLED)
        <div class="alert alert-light-danger d-flex align-items-start mb-5">
            <i class="ki-outline ki-cross-circle fs-2 text-danger me-3"></i>
            <div><div class="fw-bold">Purchase Order dibatalkan</div><div class="fs-7">{{ $purchaseOrder->cancel_reason ?: 'Tidak ada alasan pembatalan yang dicatat.' }}</div></div>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <h2 class="fs-3 fw-bold mb-0">{{ $purchaseOrder->number }}</h2>
                <x-metronic.status-badge :status="$status" />
            </div>
            <div class="text-muted fs-7">Dibuat oleh {{ $purchaseOrder->creator?->name ?: 'Sistem' }} pada {{ $purchaseOrder->created_at?->format('d/m/Y H:i') ?: '-' }}</div>
        </div>
        <div class="text-end"><div class="text-muted fs-8">NILAI PURCHASE ORDER</div><div class="fs-2 fw-bold text-primary">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</div></div>
    </div>

    <x-metronic.card title="Alur Purchase Order">
        <div class="row g-3">
            @foreach ($processSteps as $step)
                @php
                    $stepState = $status->value === $step['status'] ? 'current' : (in_array($step['status'], $historyStatuses, true) ? 'done' : 'pending');
                    $stepColor = $stepState === 'done' ? 'success' : ($stepState === 'current' ? 'primary' : 'secondary');
                @endphp
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="border rounded p-3 h-100 {{ $stepState === 'current' ? 'border-primary bg-light-primary' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2"><span class="badge badge-light-{{ $stepColor }}">{{ $loop->iteration }}</span><span class="fw-bold fs-7">{{ $step['label'] }}</span></div>
                        <div class="text-muted fs-8">{{ $step['description'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-4 mt-2 pt-4 border-top">
            <div class="col-md-6"><div class="text-muted fs-8 mb-1">PIHAK YANG MEMEGANG PROSES</div><div class="fw-semibold">{{ $processHolder }}</div></div>
            <div class="col-md-6"><div class="text-muted fs-8 mb-1">LANGKAH BERIKUTNYA</div><div class="fw-semibold">{{ $processNext }}</div></div>
        </div>
    </x-metronic.card>

    <div class="row g-5 mt-0">
        <div class="col-xl-8">
            <x-metronic.card title="Informasi Dokumen">
                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">NOMOR PO</div><div class="fw-semibold">{{ $purchaseOrder->number }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">TANGGAL PESANAN</div><div class="fw-semibold">{{ $purchaseOrder->order_date?->format('d/m/Y') ?: '-' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">PERKIRAAN TIBA</div><div class="fw-semibold {{ $purchaseOrder->expected_at?->isPast() && ! $status->isFinal() ? 'text-danger' : '' }}">{{ $purchaseOrder->expected_at?->format('d/m/Y') ?: 'Belum ditentukan' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">STATUS</div><x-metronic.status-badge :status="$status" /></div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">REFERENSI PERMINTAAN PEMBELIAN</div>
                        @if ($purchaseOrder->purchaseRequest)
                            @can('view', $purchaseOrder->purchaseRequest)
                                <a href="{{ route('purchasing.requests.show', $purchaseOrder->purchaseRequest) }}" class="fw-semibold text-primary">Berasal dari {{ $purchaseOrder->purchaseRequest->number }}</a>
                            @else
                                <span class="fw-semibold">{{ $purchaseOrder->purchaseRequest->number }}</span>
                            @endcan
                        @else
                            <span class="text-muted">PO dibuat langsung tanpa Purchase Request.</span>
                        @endif
                    </div>
                    @if ($purchaseOrder->notes)
                        <div class="col-md-6"><div class="text-muted fs-8 mb-1">CATATAN PO</div><div class="fw-semibold">{{ $purchaseOrder->notes }}</div></div>
                    @endif
                </div>

                <div class="separator my-5"></div>

                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="fw-bold mb-3">Supplier</div>
                        <div class="text-muted fs-8 mb-1">Pemasok yang menerima pesanan pembelian ini.</div>
                        @if (auth()->user()?->can('suppliers.view'))
                            <a href="{{ route('admin.suppliers.show', $purchaseOrder->supplier_id) }}" class="fw-semibold text-primary">{{ $purchaseOrder->supplier?->code }} - {{ $purchaseOrder->supplier?->name }}</a>
                        @else
                            <div class="fw-semibold">{{ $purchaseOrder->supplier?->code }} - {{ $purchaseOrder->supplier?->name }}</div>
                        @endif
                        <div class="text-muted fs-7 mt-2">Kontak: {{ $purchaseOrder->supplier?->contact_name ?: '-' }} / {{ $purchaseOrder->supplier?->phone_number ?: '-' }}</div>
                        <div class="text-muted fs-7">Termin pembayaran: {{ $purchaseOrder->payment_term_days }} hari</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold mb-3">Tujuan Penerimaan</div>
                        <div class="text-muted fs-8 mb-1">Gudang yang akan menerima barang dari supplier.</div>
                        <div class="fw-semibold">{{ $purchaseOrder->warehouse?->code }} - {{ $purchaseOrder->warehouse?->name }}</div>
                        <div class="text-muted fs-7 mt-2">Penerimaan dicatat melalui modul Goods Receipt sesuai akses lokasi kerja.</div>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Item Purchase Order" class="mt-5">
                @if ($totalOrdered > 0)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div><span class="fw-semibold">Progress penerimaan {{ number_format($progressPercent, 0) }}%</span><div class="text-muted fs-8">{{ qty($totalReceived) }} diterima dari {{ qty($totalOrdered) }} dipesan; {{ qty($totalOutstanding) }} outstanding.</div></div>
                        <div class="progress w-200px" style="height: 8px;"><div class="progress-bar bg-{{ $progressPercent >= 100 ? 'success' : 'primary' }}" style="width: {{ $progressPercent }}%"></div></div>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th class="text-end">Dipesan</th><th class="text-end">Diterima</th><th class="text-end">Harga</th><th class="text-end">Diskon</th><th class="text-end">Pajak</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr>
                                    <td><span class="fw-semibold">{{ $item->product_name_snapshot }}</span><div class="text-muted fs-8">{{ $item->product_sku_snapshot }} / {{ $item->unit_name_snapshot }} x {{ qty($item->conversion_factor_snapshot) }}</div></td>
                                    <td class="text-end">{{ qty($item->quantity_ordered) }}</td>
                                    <td class="text-end"><span class="{{ (float) $item->quantity_received > 0 ? 'text-success fw-semibold' : '' }}">{{ qty($item->quantity_received) }}</span><div class="text-muted fs-8">Sisa {{ qty($item->outstandingQuantity()) }}</div></td>
                                    <td class="text-end">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $item->discount_amount, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $item->tax_amount, 0, ',', '.') }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Nilai Purchase Order" class="mt-5">
                <div class="row g-4">
                    <div class="col-6 col-lg"><div class="text-muted fs-8">SUBTOTAL ITEM</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->items_subtotal, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">DISKON DOKUMEN</div><div class="fw-semibold text-danger">Rp {{ number_format((float) $purchaseOrder->header_discount, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">BIAYA PENGIRIMAN</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->freight_cost, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">BIAYA TAMBAHAN</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->additional_cost, 0, ',', '.') }}</div></div>
                    <div class="col-12 col-lg"><div class="text-muted fs-8">GRAND TOTAL</div><div class="fs-4 fw-bold text-primary">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</div></div>
                </div>
            </x-metronic.card>
        </div>

        <div class="col-xl-4">
            <x-metronic.card title="Aksi yang Tersedia untuk Anda">
                <div class="alert alert-light-primary fs-7">{{ $processNext }}</div>

                @if ($canEdit)
                    <a href="{{ route('purchasing.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-light-primary w-100 mb-3"><i class="ki-outline ki-pencil fs-5 me-2"></i>Edit PO</a>
                    @if ($status === \App\Enums\PurchaseOrderStatus::DRAFT)
                        <form method="POST" action="{{ route('purchasing.purchase-orders.submit', $purchaseOrder) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-primary w-100"><i class="ki-outline ki-send fs-5 me-2"></i>Ajukan Persetujuan</button>
                        </form>
                    @endif
                @endif

                @if ($canApprove)
                    <form method="POST" action="{{ route('purchasing.purchase-orders.approve', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <x-metronic.form-group name="notes" label="Catatan Persetujuan" help="Catatan akan disimpan pada histori approval PO.">
                            <textarea name="notes" rows="2" class="form-control form-control-solid">{{ old('notes') }}</textarea>
                        </x-metronic.form-group>
                        <button class="btn btn-success w-100"><i class="ki-outline ki-check fs-5 me-2"></i>Setujui PO</button>
                    </form>
                @endif

                @if ($canSend)
                    <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <div class="text-muted fs-8 mb-2">Tandai setelah dokumen resmi dikirim kepada supplier.</div>
                        <button class="btn btn-info w-100"><i class="ki-outline ki-send fs-5 me-2"></i>Tandai Dikirim ke Supplier</button>
                    </form>
                @endif

                @if ($canReceive)
                    <a href="{{ route('warehouse.goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="btn btn-success w-100 mb-3"><i class="ki-outline ki-package fs-5 me-2"></i>Catat Penerimaan Barang</a>
                @endif

                @if ($canCancel)
                    <div class="border-top pt-4 mt-2">
                        <form method="POST" action="{{ route('purchasing.purchase-orders.cancel', $purchaseOrder) }}" onsubmit="return confirm('Yakin ingin membatalkan Purchase Order ini?')">
                            @csrf
                            <x-metronic.form-group name="reason" label="Alasan Pembatalan" required help="Pembatalan bersifat final dan alasannya disimpan pada audit status.">
                                <textarea name="reason" rows="2" class="form-control form-control-solid" required>{{ old('reason') }}</textarea>
                            </x-metronic.form-group>
                            <button class="btn btn-light-danger w-100"><i class="ki-outline ki-cross fs-5 me-2"></i>Batalkan PO</button>
                        </form>
                    </div>
                @endif

                @unless ($hasAction)
                    <div class="text-muted fs-7">Tidak ada aksi transaksi yang sesuai dengan permission dan status PO untuk akun Anda saat ini.</div>
                @endunless
            </x-metronic.card>

            <x-metronic.card title="Riwayat Status" class="mt-5">
                @forelse ($purchaseOrder->statusHistories as $history)
                    @php
                        $historyStatus = \App\Enums\PurchaseOrderStatus::tryFrom($history->to_status);
                        $historyColor = $historyStatus?->badge() ?? 'secondary';
                    @endphp
                    <div class="d-flex gap-3 {{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
                        <span class="symbol symbol-35px bg-light-{{ $historyColor }}"><span class="symbol-label"><i class="ki-outline ki-check text-{{ $historyColor }}"></i></span></span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2"><span class="fw-semibold">{{ $historyStatus?->label() ?: ucfirst($history->to_status) }}</span><span class="text-muted fs-8">{{ $history->created_at->format('d/m/Y H:i') }}</span></div>
                            <div class="text-muted fs-8">Oleh {{ $history->actor?->name ?: 'Sistem' }}</div>
                            @if ($history->notes)<div class="mt-1 fs-7">{{ $history->notes }}</div>@endif
                        </div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada riwayat" description="Perubahan status PO akan tampil di sini." />
                @endforelse
            </x-metronic.card>

            @if ($purchaseOrder->approvals->isNotEmpty())
                <x-metronic.card title="Catatan Approval" class="mt-5">
                    @foreach ($purchaseOrder->approvals as $approval)
                        <div class="{{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
                            <div class="d-flex justify-content-between gap-2"><span class="fw-semibold">{{ $approval->approver?->name ?: '-' }}</span><span class="badge badge-light-success">Disetujui</span></div>
                            <div class="text-muted fs-8">{{ $approval->approved_at?->format('d/m/Y H:i') ?: '-' }}</div>
                            @if ($approval->notes)<div class="mt-2 fs-7">{{ $approval->notes }}</div>@endif
                        </div>
                    @endforeach
                </x-metronic.card>
            @endif
        </div>
    </div>
@endsection
