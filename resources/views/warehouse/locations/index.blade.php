@extends('layouts.metronic.app')

@section('title', 'Zona, Rak, dan Bin - ' . config('app.name'))
@section('page_title', 'Zona, Rak, dan Bin')

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.locations.create')" icon="ki-outline ki-plus">Tambah Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><select name="warehouse_id" class="form-select form-select-solid"><option value="">Semua gudang</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="inactive" @selected($status === 'inactive')>Nonaktif</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode Penuh</th><th>Gudang</th><th>Parent</th><th>Tipe</th><th>Kapasitas</th><th>Jenis Barang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($locations as $location)
                    <tr>
                        <td class="fw-bold">{{ $location->full_code }}<div class="text-muted">{{ $location->name }}</div></td>
                        <td>{{ $location->warehouse?->name }}</td>
                        <td>{{ $location->parent?->full_code ?: '-' }}</td>
                        <td>{{ $location->type->label() }}</td>
                        <td>{{ $location->capacity ?: '-' }}</td>
                        <td>{{ $location->item_type ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$location->is_active ? 'active' : 'inactive'" :label="$location->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            @can('update', $location)
                                <a href="{{ route('warehouse.locations.edit', $location) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @if ($location->is_active)<form method="POST" action="{{ route('warehouse.locations.deactivate', $location) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>@endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada lokasi gudang" description="Buat zona, rak, atau bin untuk mulai memetakan stok." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $locations->links() }}
    </x-metronic.card>
@endsection
