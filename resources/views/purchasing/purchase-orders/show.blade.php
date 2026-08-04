@extends('layouts.metronic.app')
@section('title', 'Detail ' . $purchaseOrder->number)
@section('page_title', 'Detail dan Approval PO')

@section('toolbar_actions')
    <a href="{{ route('purchasing.purchase-orders.print', $purchaseOrder) }}" class="btn btn-light-success">
        <i class="ki-outline ki-printer fs-5 me-2"></i>Print
    </a>
    <a href="{{ route('purchasing.purchase-orders.print', [$purchaseOrder, 'download' => 'pdf']) }}" class="btn btn-light-primary">
        <i class="ki-outline ki-file-down fs-5 me-2"></i>PDF
    </a>
    <a href="{{ route('purchasing.purchase-orders.export-one', $purchaseOrder) }}" class="btn btn-light-info">
        <i class="ki-outline ki-file-down fs-5 me-2"></i>Excel
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="purchasing-po-show" title="Panduan Detail Purchase Order">
        <x-slot:function><p>Halaman ini menampilkan rincian lengkap Purchase Order (PO) termasuk informasi supplier, item produk, status, approval, dan histori transaksi.</p></x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Header:</strong> nomor PO, tanggal, supplier, gudang, status, dan total nilai.</li>
                <li><strong>Progress:</strong> indikator penerimaan barang (ordered vs received).</li>
                <li><strong>Item Table:</strong> daftar produk yang dipesan dengan qty, harga, diskon, dan subtotal.</li>
                <li><strong>Aksi:</strong> tombol untuk submit, approve, send, atau cancel PO.</li>
                <li><strong>Timeline:</strong> histori perubahan status PO.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts><p>Status PO memengaruhi alur pekerjaan purchasing dan penerimaan barang.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa header dan status PO.</li><li>Verifikasi item dan quantity.</li><li>Lakukan aksi sesuai status (submit/approve/cancel).</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $totalOrdered = $purchaseOrder->items->sum(fn($item) => (float) $item->quantity_ordered);
        $totalReceived = $purchaseOrder->items->sum(fn($item) => (float) $item->quantity_received);
        $totalOutstanding = $totalOrdered - $totalReceived;
        $progressPercent = $totalOrdered > 0 ? min(100, ($totalReceived / $totalOrdered) * 100) : 0;
        $statusColor = match($purchaseOrder->status?->value) {
            'draft' => 'warning',
            'submitted' => 'info',
            'approved' => 'success',
            'sent_to_supplier' => 'primary',
            'partially_received' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    @endphp

    <div class="row g-6">
        {{-- Kolom Kiri: Info PO & Items --}}
        <div class="col-lg-8">
            {{-- Header Card --}}
            <x-metronic.card class="mb-6">
                <div class="d-flex justify-content-between align-items-start mb-5">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-70px symbol-circle bg-light-primary text-primary me-4">
                            <span class="symbol-label fs-1 fw-bold">{{ substr($purchaseOrder->number, 0, 1) }}</span>
                        </div>
                        <div>
                            <h3 class="fw-bold text-gray-900 mb-1">{{ $purchaseOrder->number }}</h3>
                            <div class="text-muted fs-7">
                                Dibuat oleh {{ $purchaseOrder->creator?->name ?: 'System' }}
                                @if($purchaseOrder->order_date)
                                    · {{ $purchaseOrder->order_date->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <x-metronic.status-badge :status="$purchaseOrder->status?->value ?? 'draft'" :label="$purchaseOrder->status?->label() ?? 'Draft'" />
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Supplier</div>
                        <a href="{{ route('admin.suppliers.show', $purchaseOrder->supplier_id) }}" class="fw-semibold text-primary text-hover-primary">
                            <i class="ki-outline ki-user fs-5 me-1 text-muted"></i>{{ $purchaseOrder->supplier?->name ?: '-' }}
                        </a>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Gudang</div>
                        <div class="fw-semibold">
                            <i class="ki-outline ki-warehouse fs-5 me-1 text-muted"></i>{{ $purchaseOrder->warehouse?->name ?: '-' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Termin Pembayaran</div>
                        <div class="fw-semibold">
                            <i class="ki-outline ki-calendar fs-5 me-1 text-muted"></i>{{ $purchaseOrder->payment_term_days }} hari
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Tanggal Pesanan</div>
                        <div class="fw-semibold">
                            <i class="ki-outline ki-calendar fs-5 me-1 text-muted"></i>{{ $purchaseOrder->order_date?->format('d/m/Y') ?: '-' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Estimasi Tiba (ETA)</div>
                        <div class="fw-semibold {{ $purchaseOrder->expected_at && $purchaseOrder->expected_at->isPast() ? 'text-danger' : '' }}">
                            <i class="ki-outline ki-time fs-5 me-1 text-muted"></i>{{ $purchaseOrder->expected_at?->format('d/m/Y') ?: '-' }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-7 mb-1">Purchase Request</div>
                        <div class="fw-semibold">
                            @if($purchaseOrder->purchaseRequest)
                                <a href="#" class="text-primary text-hover-primary">
                                    <i class="ki-outline ki-basket fs-5 me-1 text-muted"></i>{{ $purchaseOrder->purchaseRequest->number }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>
                    @if($purchaseOrder->notes)
                    <div class="col-md-12">
                        <div class="text-muted fs-7 mb-1">Catatan</div>
                        <div class="bg-light p-3 rounded">{{ $purchaseOrder->notes }}</div>
                    </div>
                    @endif
                </div>
                {{-- Progress Bar --}}
                @if($totalOrdered > 0)
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-7 fw-semibold">Progress Penerimaan</span>
                        <span class="fw-bold">{{ number_format($progressPercent, 0) }}% ({{ qty($totalReceived) }} / {{ qty($totalOrdered) }})</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar {{ $progressPercent >= 100 ? 'bg-success' : ($progressPercent > 0 ? 'bg-warning' : 'bg-light') }}"
                             role="progressbar"
                             style="width: {{ $progressPercent }}%;"
                             aria-valuenow="{{ $progressPercent }}"
                             aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                    @if($totalOutstanding > 0)
                    <div class="text-muted fs-7 mt-2">
                        <i class="ki-outline ki-exclamation-triangle fs-6 me-1 text-warning"></i>
                        Outstanding: {{ qty($totalOutstanding) }} unit
                    </div>
                    @endif
                </div>
                @endif

                {{-- Items Table --}}
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th class="min-w-200px">Produk</th>
                                <th class="min-w-100px">Qty</th>
                                <th class="min-w-100px">Harga</th>
                                <th class="min-w-100px">Diskon</th>
                                <th class="min-w-100px">Pajak</th>
                                <th class="min-w-120px text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseOrder->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold text-gray-900">{{ $item->product_name_snapshot }}</span>
                                            <span class="text-muted font-monospace">{{ $item->product_sku_snapshot }}</span>
                                            <span class="text-muted">{{ $item->unit_name_snapshot }} × {{ qty($item->conversion_factor_snapshot) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">Order: {{ qty($item->quantity_ordered) }}</span>
                                            <span class="text-success">Recv: {{ qty($item->quantity_received) }}</span>
                                            @if($item->outstandingQuantity() > 0)
                                                <span class="text-warning fw-bold">Out: {{ qty($item->outstandingQuantity()) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        @if($item->discount_amount > 0)
                                            <span class="text-danger">-Rp {{ number_format((float) $item->discount_amount, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($item->tax_amount > 0)
                                            <span class="text-warning">Rp {{ number_format((float) $item->tax_amount, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Totals --}}
                <div class="row justify-content-end mt-5">
                    <div class="col-md-5">
                        <div class="bg-light p-4 rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal Item</span>
                                <span class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->items_subtotal, 0, ',', '.') }}</span>
                            </div>
                            @if($purchaseOrder->header_discount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Diskon Header</span>
                                <span class="fw-semibold text-danger">-Rp {{ number_format((float) $purchaseOrder->header_discount, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            @if($purchaseOrder->freight_cost > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Ongkir</span>
                                <span class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->freight_cost, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            @if($purchaseOrder->additional_cost > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Biaya Tambahan</span>
                                <span class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->additional_cost, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="separator my-3"></div>
                            <div class="d-flex justify-content-between fs-4 fw-bold text-primary">
                                <span>Total</span>
                                <span>Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-metronic.card>

            {{-- Tab Navigation --}}
            <x-metronic.card class="mt-6">
                <ul class="nav nav-tabs nav-line-tabs mb-5" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline" type="button" role="tab">
                            <i class="ki-outline ki-clock fs-5 me-2"></i>Timeline Status
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approvals-tab" data-bs-toggle="tab" data-bs-target="#approvals" type="button" role="tab">
                            <i class="ki-outline ki-shield fs-5 me-2"></i>Approval
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Tab Timeline --}}
                    <div class="tab-pane fade show active" id="timeline" role="tabpanel">
                        @forelse($purchaseOrder->statusHistories as $history)
                            <div class="d-flex gap-4 mb-4">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-40px symbol-circle bg-{{ $statusColor }}-simple">
                                        <i class="ki-outline {{ match($history->to_status) {
                                            'draft' => ' ki-pencil',
                                            'submitted' => ' ki-send',
                                            'approved' => ' ki-check',
                                            'sent_to_supplier' => ' ki-truck',
                                            'partially_received' => ' ki-truck',
                                            'completed' => ' ki-check-circle',
                                            'cancelled' => ' ki-close',
                                            default => ' ki-circle'
                                        } }} fs-4 text-{{ $statusColor }}"></i>
                                    </div>
                                    @if(!$loop->last)
                                        <div class="border border-2 border-{{ $statusColor }} border-dashed" style="height: 40px; min-width: 2px;"></div>
                                    @endif
                                </div>
                                <div class="flex-grow-1 pb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge badge-{{ $statusColor }} fs-7 fw-normal me-2">
                                                {{ ucfirst($history->to_status) }}
                                            </span>
                                            <span class="fw-bold text-gray-900">{{ $history->actor?->name ?? 'System' }}</span>
                                        </div>
                                        <span class="text-muted fs-7">{{ $history->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if($history->notes)
                                        <div class="mt-2 text-muted">{{ $history->notes }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada timeline" description="Perubahan status akan tercatat di sini." icon="ki-outline ki-clock" />
                        @endforelse
                    </div>

                    {{-- Tab Approvals --}}
                    <div class="tab-pane fade" id="approvals" role="tabpanel">
                        @forelse($purchaseOrder->approvals as $approval)
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-50px symbol-circle bg-success-simple text-success me-3">
                                                <span class="symbol-label">{{ substr($approval->approver?->name ?? 'A', 0, 1) }}</span>
                                            </div>
                                            <div>
                                                <div class="fw-bold">{{ $approval->approver?->name ?? '-' }}</div>
                                                <div class="text-muted fs-7">{{ $approval->approved_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="badge badge-success">Approved</span>
                                    </div>
                                    @if($approval->notes)
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="text-muted fs-7 mb-1">Catatan:</div>
                                            <div>{{ $approval->notes }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada approval" description="Approval akan tercatat setelah PO disetujui." icon="ki-outline ki-shield" />
                        @endforelse
                    </div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            {{-- Actions Card --}}
            <x-metronic.card title="Aksi Dokumen">
                <div class="d-grid gap-3">
                    @can('update', $purchaseOrder)
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::DRAFT)
                            <form method="POST" action="{{ route('purchasing.purchase-orders.submit', $purchaseOrder) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ki-outline ki-send fs-5 me-2"></i>Ajukan Approval
                                </button>
                            </form>
                        @endif
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::SUBMITTED)
                            <div class="alert alert-warning fs-7">
                                <i class="ki-outline ki-alert fs-5 me-2"></i>Menunggu approval
                            </div>
                        @endif
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::APPROVED)
                            <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}">
                                @csrf
                                <button type="submit" class="btn btn-info w-100">
                                    <i class="ki-outline ki-truck fs-5 me-2"></i>Tandai Dikirim
                                </button>
                            </form>
                        @endif
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER || $purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED)
                            <a href="{{ route('warehouse.goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="btn btn-success w-100">
                                <i class="ki-outline ki-plus fs-5 me-2"></i>Catat Penerimaan
                            </a>
                        @endif
                    @endcan

                    @can('approve', $purchaseOrder)
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::SUBMITTED)
                            <form method="POST" action="{{ route('purchasing.purchase-orders.approve', $purchaseOrder) }}">
                                @csrf
                                <input type="text" name="notes" class="form-control form-control-solid mb-2" placeholder="Catatan approval (opsional)">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="ki-outline ki-check fs-5 me-2"></i>Approve PO
                                </button>
                            </form>
                        @endif
                    @endcan

                    @if(in_array($purchaseOrder->status?->value, ['draft', 'submitted']))
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchasing.purchase-orders.cancel', $purchaseOrder) }}" onsubmit="return confirm('Yakin ingin membatalkan PO ini?')">
                            @csrf
                            <button type="submit" class="btn btn-light-danger w-100">
                                <i class="ki-outline ki-close fs-5 me-2"></i>Batalkan PO
                            </button>
                        </form>
                    @endcan
                    @endif
                </div>
            </x-metronic.card>

            {{-- Quick Stats --}}
            <x-metronic.card title="Ringkasan" class="mt-6">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fw-bold fs-3 text-primary">{{ $purchaseOrder->items->count() }}</div>
                            <div class="text-muted fs-8">Total Item</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fw-bold fs-3 text-success">{{ qty($totalReceived) }}</div>
                            <div class="text-muted fs-8">Sudah Diterima</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fw-bold fs-3 text-warning">{{ qty($totalOutstanding) }}</div>
                            <div class="text-muted fs-8">Outstanding</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fw-bold fs-3 text-info">{{ $progressPercent > 0 ? number_format($progressPercent, 0) . '%' : '0%' }}</div>
                            <div class="text-muted fs-8">Progress</div>
                        </div>
                    </div>
                </div>
                <div class="separator my-4"></div>
                <div class="text-center">
                    <div class="text-muted fs-7 mb-1">Nilai Total</div>
                    <div class="fw-bold fs-2 text-primary">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</div>
                </div>
            </x-metronic.card>

            {{-- Related PO --}}
            @if($purchaseOrder->purchaseRequest)
            <x-metronic.card title="Purchase Request" class="mt-6">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="ki-outline ki-basket fs-2x text-primary"></i>
                    </div>
                    <div class="fw-bold fs-5">{{ $purchaseOrder->purchaseRequest->number }}</div>
                    <div class="text-muted fs-7">{{ $purchaseOrder->purchaseRequest->created_at?->format('d M Y') }}</div>
                </div>
            </x-metronic.card>
            @endif
        </div>
    </div>
@endsection
