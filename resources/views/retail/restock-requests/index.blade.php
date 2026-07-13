@extends('layouts.metronic.app')

@section('title', 'Permintaan Restock - ' . config('app.name'))
@section('page_title', 'Permintaan Restock Cabang')

@section('content')
    <div class="row g-5">
        @can('create', \App\Models\RestockRequest::class)
            <div class="col-lg-4">
                <x-metronic.card title="Buat Request Restock">
                    <form method="POST" action="{{ route('retail.restock-requests.store') }}">
                        @csrf
                        <x-metronic.form-group name="branch_id" label="Cabang" required><select name="branch_id" class="form-select form-select-solid" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group>
                        <x-metronic.form-group name="source_warehouse_id" label="Gudang Sumber"><select name="source_warehouse_id" class="form-select form-select-solid"><option value="">Gudang utama cabang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></x-metronic.form-group>
                        <x-metronic.form-group name="priority" label="Prioritas"><select name="priority" class="form-select form-select-solid"><option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Urgent</option><option value="low">Rendah</option></select></x-metronic.form-group>
                        <x-metronic.form-group name="needed_at" label="Tanggal Dibutuhkan"><input type="date" name="needed_at" class="form-control form-control-solid"></x-metronic.form-group>
                        <div class="table-responsive mb-4">
                            <table class="table align-middle">
                                <thead><tr class="text-muted fw-bold fs-7 text-uppercase"><th>Produk</th><th>Qty</th></tr></thead>
                                <tbody>
                                    @for($i = 0; $i < 3; $i++)
                                        <tr>
                                            <td><select name="items[{{ $i }}][product_id]" class="form-select form-select-sm"><option value="">Pilih</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
                                            <td><input name="items[{{ $i }}][quantity_requested]" type="number" min="0" step="0.0001" class="form-control form-control-sm" value="{{ $i === 0 ? '1' : '' }}"></td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                        <x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" class="form-control form-control-solid" rows="2"></textarea></x-metronic.form-group>
                        <div class="d-grid gap-2"><button name="action" value="submit" class="btn btn-primary">Submit Request</button><button name="action" value="draft" class="btn btn-light">Simpan Draft</button></div>
                    </form>
                </x-metronic.card>
            </div>
        @endcan
        <div class="@can('create', \App\Models\RestockRequest::class) col-lg-8 @else col-12 @endcan">
            <x-metronic.card title="Antrian Request">
                <form method="GET" class="row g-3 mb-5">
                    <div class="col-md-4"><select name="branch_id" class="form-select form-select-solid"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold fs-7 text-uppercase"><th>No</th><th>Cabang</th><th>Gudang</th><th>Item</th><th>Prioritas</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @forelse($requests as $request)
                            <tr>
                                <td class="fw-bold">{{ $request->number }}</td>
                                <td>{{ $request->branch?->name }}</td>
                                <td>{{ $request->sourceWarehouse?->name }}</td>
                                <td>{{ $request->items->count() }} item · {{ $request->requestedQuantity() }}</td>
                                <td>{{ ucfirst($request->priority) }}</td>
                                <td><x-metronic.status-badge :status="$request->status" /></td>
                                <td>
                                    @can('approve', $request)
                                        @if($request->status === \App\Enums\RestockRequestStatus::PENDING_APPROVAL)
                                            <form method="POST" action="{{ route('retail.restock-requests.approve', $request) }}" class="d-inline">@csrf @foreach($request->items as $item)<input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ $item->quantity_requested }}">@endforeach<button class="btn btn-sm btn-light-success">Approve</button></form>
                                            <form method="POST" action="{{ route('retail.restock-requests.reject', $request) }}" class="d-inline">@csrf<input type="hidden" name="reason" value="Ditolak dari daftar"><button class="btn btn-sm btn-light-danger">Reject</button></form>
                                        @endif
                                        @if($request->status === \App\Enums\RestockRequestStatus::APPROVED)
                                            <form method="POST" action="{{ route('retail.restock-requests.convert', $request) }}">@csrf<button class="btn btn-sm btn-primary">Convert to Transfer</button></form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada request restock" description="Cabang dapat membuat permintaan dari form di halaman ini." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
