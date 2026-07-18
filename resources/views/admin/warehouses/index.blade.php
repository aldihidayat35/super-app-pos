@extends('layouts.metronic.app')

@section('title', 'Daftar Gudang - ' . config('app.name'))
@section('page_title', 'Daftar Gudang')

@section('toolbar_actions')
    <x-metronic.permission-button permission="admin.warehouses.create" :href="route('admin.warehouses.create')" icon="ki-outline ki-plus">
        Tambah Gudang
    </x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" action="{{ route('admin.warehouses.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <div class="d-flex flex-wrap gap-3">
                <input name="city" value="{{ $city }}" class="form-control form-control-solid w-225px" placeholder="Filter kota">
                <select name="status" class="form-select form-select-solid w-175px">
                    <option value="">Semua Status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button class="btn btn-light-primary" type="submit"><i class="ki-outline ki-magnifier"></i> Filter</button>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Kota</th><th>Kepala Gudang</th><th>Kapasitas</th><th>Area Layanan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td class="fw-bold">{{ $warehouse->code }}</td>
                            <td><a href="{{ route('admin.warehouses.show', $warehouse) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $warehouse->name }}</a></td>
                            <td>{{ $warehouse->city ?: '-' }}</td>
                            <td>{{ $warehouse->manager?->name ?: '-' }}</td>
                            <td>{{ $warehouse->capacity ? qty($warehouse->capacity) : '-' }}</td>
                            <td>{{ $warehouse->service_area ?: '-' }}</td>
                            <td><x-metronic.status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" :label="$warehouse->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <a href="{{ route('admin.warehouses.show', $warehouse) }}" class="btn btn-sm btn-light">Detail</a>
                                @can('update', $warehouse)
                                    <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                    @if ($warehouse->is_active)
                                        <form method="POST" action="{{ route('admin.warehouses.deactivate', $warehouse) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light-danger" type="submit">Nonaktifkan</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-metronic.empty-state title="Belum ada gudang" description="Gudang akan tampil setelah dibuat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $warehouses->links() }}
    </x-metronic.card>
@endsection
