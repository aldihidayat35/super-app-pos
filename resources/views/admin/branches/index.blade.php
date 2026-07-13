@extends('layouts.metronic.app')

@section('title', 'Daftar Cabang/Toko - ' . config('app.name'))
@section('page_title', 'Daftar Cabang/Toko')

@section('toolbar_actions')
    <x-metronic.permission-button permission="admin.branches.create" :href="route('admin.branches.create')" icon="ki-outline ki-plus">
        Tambah Cabang
    </x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" action="{{ route('admin.branches.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <div class="d-flex flex-wrap gap-3">
                <select name="warehouse" class="form-select form-select-solid w-225px">
                    <option value="">Semua Gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
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
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama Toko</th><th>Gudang Pemasok</th><th>Kepala Toko</th><th>Target</th><th>Status Closing</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td class="fw-bold">{{ $branch->code }}</td>
                            <td><a href="{{ route('admin.branches.show', $branch) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $branch->name }}</a></td>
                            <td>{{ $branch->primaryWarehouse?->name }}</td>
                            <td>{{ $branch->manager?->name ?: '-' }}</td>
                            <td>{{ $branch->sales_target ? \App\Support\CurrencyFormatter::rupiah($branch->sales_target) : '-' }}</td>
                            <td>{{ $branch->is_closing_required ? 'Wajib' : 'Opsional' }} · {{ $branch->closing_configuration }}</td>
                            <td><x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <a href="{{ route('admin.branches.show', $branch) }}" class="btn btn-sm btn-light">Detail</a>
                                @can('update', $branch)
                                    <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                    @if ($branch->is_active)
                                        <form method="POST" action="{{ route('admin.branches.deactivate', $branch) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light-danger" type="submit">Nonaktifkan</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-metronic.empty-state title="Belum ada cabang" description="Cabang/toko akan tampil setelah dibuat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $branches->links() }}
    </x-metronic.card>
@endsection
