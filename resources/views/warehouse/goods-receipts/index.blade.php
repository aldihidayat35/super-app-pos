@extends('layouts.metronic.app')

@section('title', 'Penerimaan Barang - ' . config('app.name'))
@section('page_title', 'Daftar Penerimaan Barang')

@section('toolbar_actions')
    <a href="{{ route('warehouse.goods-receipts.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export</a>
    <x-metronic.permission-button permission="goods_receipts.create" :href="route('warehouse.goods-receipts.create')" icon="ki-outline ki-plus">Buat Receipt</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No Receipt</th><th>PO</th><th>Supplier</th><th>Gudang</th><th>Tanggal</th><th>Penerima</th><th>QC</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td class="fw-bold">{{ $receipt->number }}</td>
                        <td>{{ $receipt->purchaseOrder?->number }}</td>
                        <td>{{ $receipt->supplier?->name }}</td>
                        <td>{{ $receipt->warehouse?->name }}</td>
                        <td>{{ $receipt->received_at?->format('d/m/Y') }}</td>
                        <td>{{ $receipt->receiver?->name }}</td>
                        <td><span class="text-success">{{ $receipt->acceptedQuantity() }}</span> / <span class="text-danger">{{ $receipt->rejectedQuantity() }}</span> / <span class="text-warning">{{ $receipt->damagedQuantity() }}</span></td>
                        <td><x-metronic.status-badge :status="$receipt->status" /></td>
                        <td class="text-end">
                            <a href="{{ route('warehouse.goods-receipts.show', $receipt) }}" class="btn btn-sm btn-light-primary">Detail</a>
                            @can('update', $receipt)<a href="{{ route('warehouse.goods-receipts.edit', $receipt) }}" class="btn btn-sm btn-light">Edit</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada penerimaan" description="Buat receipt dari PO yang sudah disetujui atau dikirim ke supplier." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receipts->links() }}
    </x-metronic.card>
@endsection
