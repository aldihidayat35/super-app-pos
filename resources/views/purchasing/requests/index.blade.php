@extends('layouts.metronic.app')

@section('title', 'Permintaan Pembelian - ' . config('app.name'))
@section('page_title', 'Permintaan Pembelian')

@section('content')
    <div class="row g-5">
        <div class="col-lg-4">
            <x-metronic.card title="Request Manual">
                <form method="POST" action="{{ route('purchasing.requests.store') }}">
                    @csrf
                    <x-metronic.form-group name="warehouse_id" label="Gudang">
                        <select name="warehouse_id" class="form-select form-select-solid" required>
                            <option value="">Pilih gudang</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="priority" label="Prioritas">
                        <select name="priority" class="form-select form-select-solid" required>
                            <option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Mendesak</option><option value="low">Rendah</option>
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="reason" label="Alasan">
                        <textarea name="reason" rows="3" class="form-control form-control-solid" required></textarea>
                    </x-metronic.form-group>
                    <div class="border rounded p-3 mb-4">
                        <div class="fw-bold mb-3">Item</div>
                        <x-metronic.form-group name="items.0.product_id" label="Produk">
                            <select name="items[0][product_id]" class="form-select form-select-solid" required>
                                <option value="">Pilih produk</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>
                        <input type="hidden" name="items[0][unit_id]" value="">
                        <x-metronic.form-group name="items.0.quantity" label="Qty">
                            <input name="items[0][quantity]" type="number" step="0.0001" min="0.0001" class="form-control form-control-solid" required>
                        </x-metronic.form-group>
                        <x-metronic.form-group name="items.0.reason" label="Catatan Item">
                            <input name="items[0][reason]" class="form-control form-control-solid">
                        </x-metronic.form-group>
                    </div>
                    <button class="btn btn-primary w-100">Submit Request</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Rekomendasi dari Stok Minimum">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th>On Hand</th><th>Minimum</th><th>Rekomendasi</th></tr></thead>
                        <tbody>
                        @forelse ($recommendations as $stock)
                            <tr>
                                <td>{{ $stock->product?->sku }}<div class="text-muted">{{ $stock->product?->name }}</div></td>
                                <td>{{ $stock->workLocation?->name }}<div class="text-muted">{{ $stock->warehouseLocation?->full_code ?: '-' }}</div></td>
                                <td>{{ $stock->quantity_on_hand }}</td>
                                <td>{{ $stock->product?->minimum_stock }}</td>
                                <td><span class="badge badge-light-warning">Perlu review purchasing</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-metronic.empty-state title="Tidak ada rekomendasi" description="Stok di bawah minimum akan muncul di sini." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Daftar Permintaan" class="mt-5">
                <form method="GET" class="row g-3 mb-5">
                    <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor</th><th>Gudang</th><th>Requester</th><th>Prioritas</th><th>Status</th><th>Item</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($requests as $purchaseRequest)
                            <tr>
                                <td class="fw-bold">{{ $purchaseRequest->number }}</td>
                                <td>{{ $purchaseRequest->warehouse?->name }}</td>
                                <td>{{ $purchaseRequest->requester?->name }}</td>
                                <td>{{ ucfirst($purchaseRequest->priority) }}</td>
                                <td><x-metronic.status-badge :status="$purchaseRequest->status" /></td>
                                <td>{{ $purchaseRequest->items->count() }}</td>
                                <td class="text-end">
                                    @can('approve', $purchaseRequest)
                                        <form method="POST" action="{{ route('purchasing.requests.approve', $purchaseRequest) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light-primary">Approve</button></form>
                                        <form method="POST" action="{{ route('purchasing.requests.reject', $purchaseRequest) }}" class="d-inline">@csrf<input type="hidden" name="reason" value="Ditolak dari daftar."><button class="btn btn-sm btn-light-danger">Reject</button></form>
                                    @endcan
                                    @can('convert', $purchaseRequest)
                                        <form method="POST" action="{{ route('purchasing.requests.convert', $purchaseRequest) }}" class="d-inline-flex gap-2 align-items-center">@csrf<select name="supplier_id" class="form-select form-select-sm w-150px">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select><button class="btn btn-sm btn-light-success">Convert PO</button></form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada permintaan" description="Request manual dan rekomendasi reorder akan tampil di sini." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
