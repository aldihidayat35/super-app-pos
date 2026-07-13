@extends('layouts.metronic.app')

@section('title', 'Detail PO - ' . config('app.name'))
@section('page_title', 'Detail dan Approval PO')

@section('toolbar_actions')
    <a href="{{ route('purchasing.purchase-orders.print', $purchaseOrder) }}" class="btn btn-light-success"><i class="ki-outline ki-printer"></i> Print</a>
    <a href="{{ route('purchasing.purchase-orders.print', [$purchaseOrder, 'download' => 'pdf']) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> PDF</a>
    <a href="{{ route('purchasing.purchase-orders.export-one', $purchaseOrder) }}" class="btn btn-light-info"><i class="ki-outline ki-file-down"></i> Excel</a>
@endsection

@section('content')
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="{{ $purchaseOrder->number }}">
                <div class="row g-4 mb-5">
                    <div class="col-md-4"><div class="text-muted">Supplier</div><div class="fw-bold">{{ $purchaseOrder->supplier?->name }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Gudang</div><div class="fw-bold">{{ $purchaseOrder->warehouse?->name }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Status</div><x-metronic.status-badge :status="$purchaseOrder->status" /></div>
                    <div class="col-md-4"><div class="text-muted">Tanggal</div><div class="fw-bold">{{ $purchaseOrder->order_date?->format('d/m/Y') }}</div></div>
                    <div class="col-md-4"><div class="text-muted">ETA</div><div class="fw-bold">{{ $purchaseOrder->expected_at?->format('d/m/Y') ?: '-' }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Termin</div><div class="fw-bold">{{ $purchaseOrder->payment_term_days }} hari</div></div>
                    <div class="col-md-12"><div class="text-muted">Catatan</div><div>{{ $purchaseOrder->notes ?: '-' }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Unit/Faktor</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th></tr></thead>
                        <tbody>
                        @foreach($purchaseOrder->items as $item)
                            <tr>
                                <td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                                <td>{{ $item->unit_name_snapshot }}<div class="text-muted">x {{ $item->conversion_factor_snapshot }}</div></td>
                                <td>{{ $item->quantity_ordered }}</td>
                                <td>{{ $item->quantity_received }}</td>
                                <td class="fw-bold">{{ $item->outstandingQuantity() }}</td>
                                <td>Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $item->discount_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $item->tax_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="row justify-content-end mt-5">
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between"><span>Subtotal Item</span><span>Rp {{ number_format((float) $purchaseOrder->items_subtotal, 0, ',', '.') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Diskon Header</span><span>Rp {{ number_format((float) $purchaseOrder->header_discount, 0, ',', '.') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Ongkir</span><span>Rp {{ number_format((float) $purchaseOrder->freight_cost, 0, ',', '.') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Biaya Tambahan</span><span>Rp {{ number_format((float) $purchaseOrder->additional_cost, 0, ',', '.') }}</span></div>
                        <div class="separator my-3"></div>
                        <div class="d-flex justify-content-between fs-4 fw-bold"><span>Total</span><span>Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</span></div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Aksi Dokumen">
                <div class="d-grid gap-3">
                    @can('update', $purchaseOrder)
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::DRAFT)
                            <form method="POST" action="{{ route('purchasing.purchase-orders.submit', $purchaseOrder) }}">@csrf<button class="btn btn-primary w-100">Ajukan Approval</button></form>
                        @endif
                        @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::APPROVED)
                            <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}">@csrf<button class="btn btn-info w-100">Tandai Dikirim</button></form>
                        @endif
                    @endcan
                    @can('approve', $purchaseOrder)
                        <form method="POST" action="{{ route('purchasing.purchase-orders.approve', $purchaseOrder) }}">@csrf<input name="notes" class="form-control form-control-solid mb-2" placeholder="Catatan approval"><button class="btn btn-success w-100">Approve PO</button></form>
                    @endcan
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchasing.purchase-orders.cancel', $purchaseOrder) }}">@csrf<input name="reason" class="form-control form-control-solid mb-2" placeholder="Alasan batal" required><button class="btn btn-light-danger w-100">Cancel PO</button></form>
                    @endcan
                </div>
            </x-metronic.card>

            <x-metronic.card title="Timeline Status" class="mt-5">
                @forelse($purchaseOrder->statusHistories as $history)
                    <div class="border-start border-3 ps-4 mb-4">
                        <div class="fw-bold">{{ ucfirst(str_replace('_', ' ', $history->to_status)) }}</div>
                        <div class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }} oleh {{ $history->actor?->name ?: '-' }}</div>
                        <div>{{ $history->notes ?: '-' }}</div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada timeline" description="Perubahan status akan tercatat di sini." />
                @endforelse
            </x-metronic.card>

            <x-metronic.card title="Approval" class="mt-5">
                @forelse($purchaseOrder->approvals as $approval)
                    <div class="mb-3"><span class="badge badge-light-success">Approved</span><div>{{ $approval->approver?->name }} — {{ $approval->approved_at?->format('d/m/Y H:i') }}</div><div class="text-muted">{{ $approval->notes ?: '-' }}</div></div>
                @empty
                    <div class="text-muted">Belum ada approval.</div>
                @endforelse
            </x-metronic.card>
        </div>
    </div>
@endsection
