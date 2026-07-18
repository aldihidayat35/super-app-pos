@extends('layouts.metronic.app')

@section('title', 'Kartu Stok - ' . config('app.name'))
@section('page_title', 'Kartu Stok Produk')

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-card.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export CSV</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="work_location_id" class="form-select form-select-solid"><option value="">Semua lokasi</option>@foreach ($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="mutation_type" class="form-select form-select-solid"><option value="">Semua jenis</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(($filters['mutation_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
            <div class="col-md-3"><select name="warehouse_location_id" class="form-select form-select-solid"><option value="">Semua zona/rak/bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}" @selected(($filters['warehouse_location_id'] ?? '') == $location->id)>{{ $location->full_code }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="reference_no" value="{{ $filters['reference_no'] ?? '' }}" class="form-control form-control-solid" placeholder="No referensi"></div>
            <div class="col-md-3"><select name="user_id" class="form-select form-select-solid"><option value="">Semua user</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Masuk</th><th>Keluar</th><th>Before</th><th>After</th><th>Referensi</th><th>User</th><th></th></tr></thead>
                <tbody>
                @forelse ($mutations as $mutation)
                    <tr>
                        <td>{{ $mutation->occurred_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $mutation->product?->sku }}<div class="text-muted">{{ $mutation->product?->name }}</div></td>
                        <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</td>
                        <td>{{ $mutation->mutation_type->label() }}</td>
                        <td class="text-success">{{ (float) $mutation->quantity_on_hand_change > 0 ? qty($mutation->quantity_on_hand_change) : '-' }}</td>
                        <td class="text-danger">{{ (float) $mutation->quantity_on_hand_change < 0 ? qty(abs((float) $mutation->quantity_on_hand_change)) : '-' }}</td>
                        <td>{{ qty($mutation->quantity_on_hand_before) }}</td>
                        <td class="fw-bold">{{ qty($mutation->quantity_on_hand_after) }}</td>
                        <td>{{ $mutation->reference_no ?: '-' }}</td>
                        <td>{{ $mutation->actor?->name ?: '-' }}</td>
                        <td class="text-end"><a href="{{ route('warehouse.stock-mutations.show', $mutation) }}" class="btn btn-sm btn-light">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11"><x-metronic.empty-state title="Belum ada mutasi" description="Kartu stok akan tampil setelah ada transaksi inventory." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $mutations->links() }}
    </x-metronic.card>
@endsection
