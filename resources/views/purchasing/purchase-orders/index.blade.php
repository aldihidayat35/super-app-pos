@extends('layouts.metronic.app')

@section('title', 'Purchase Order - ' . config('app.name'))
@section('page_title', 'Daftar Purchase Order')

@section('toolbar_actions')
    <a href="{{ route('purchasing.purchase-orders.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export Excel</a>
    <x-metronic.permission-button permission="purchase_orders.create" :href="route('purchasing.purchase-orders.create')" icon="ki-outline ki-plus">Tambah PO</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor</th><th>Supplier</th><th>Gudang</th><th>Tanggal/ETA</th><th>Total</th><th>Received/Outstanding</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($purchaseOrders as $po)
                    <tr>
                        <td class="fw-bold">{{ $po->number }}</td>
                        <td>{{ $po->supplier?->name }}</td>
                        <td>{{ $po->warehouse?->name }}</td>
                        <td>{{ $po->order_date?->format('d/m/Y') }}<div class="text-muted">ETA: {{ $po->expected_at?->format('d/m/Y') ?: '-' }}</div></td>
                        <td>Rp {{ number_format((float) $po->grand_total, 0, ',', '.') }}</td>
                        <td>{{ $po->receivedQuantity() }} / {{ $po->outstandingQuantity() }}</td>
                        <td><x-metronic.status-badge :status="$po->status" /></td>
                        <td class="text-end">
                            <a href="{{ route('purchasing.purchase-orders.show', $po) }}" class="btn btn-sm btn-light">Detail</a>
                            @can('update', $po)<a href="{{ route('purchasing.purchase-orders.edit', $po) }}" class="btn btn-sm btn-light-primary">Edit</a>@endcan
                            <a href="{{ route('purchasing.purchase-orders.print', $po) }}" class="btn btn-sm btn-light-success">Print</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada PO" description="Purchase Order akan tampil setelah dibuat." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $purchaseOrders->links() }}
    </x-metronic.card>
@endsection
