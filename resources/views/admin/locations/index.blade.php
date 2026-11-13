@extends('layouts.metronic.app')

@section('title', 'Lokasi Operasional - ' . config('app.name'))
@section('page_title', 'Lokasi Operasional')

@section('page_guide')
    <x-metronic.page-guide id="admin-locations" title="Panduan Manajemen Lokasi Operasional">
        <x-slot:function>
            <p>Halaman ini menggabungkan manajemen seluruh lokasi operasional bisnis: Gudang Pusat (kolom kiri) dan Toko/Cabang Retail (kolom kanan). Memungkinkan Super Admin dan Manajer untuk memantau, menambah, serta mengelola konfigurasi gudang dan cabang dalam satu dashboard terpadu.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pantau ringkasan total dan status aktif gudang serta toko/cabang.</li>
                <li>Gunakan filter pencarian kota atau status pada masing-masing panel.</li>
                <li>Klik tombol Tambah Gudang atau Tambah Cabang untuk mendaftarkan lokasi baru.</li>
                <li>Gunakan tombol aksi Detail atau Edit untuk memperbarui informasi spesifik lokasi.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Kolom Kiri (Gudang):</strong> Mengelola titik simpan utama, kapasitas, dan area layanan logistik.</li>
                <li><strong>Kolom Kanan (Toko/Cabang):</strong> Mengelola outlet retail, gudang pemasok utama, dan konfigurasi kasir.</li>
                <li><strong>Status Aktif/Nonaktif:</strong> Mengendalikan apakah lokasi dapat dipilih dalam transaksi harian.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Perubahan status lokasi mempengaruhi izin akses staf kasir/gudang dan ketersediaan lokasi pada penerimaan barang serta transfer stok.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan filter pada panel gudang atau cabang.</li>
                <li>Klik tombol aksi pada baris yang diinginkan.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul>
                    <li>Menonaktifkan lokasi operasional akan membatasi transaksi baru tetapi tidak menghapus histori jurnal atau mutasi persediaan.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Gudang GDG-PUSAT menyuplai Cabang TOKO-SUDIRMAN. Keduanya terpantau aktif berdampingan.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('admin.warehouses.create')
        <a href="{{ route('admin.warehouses.create') }}" class="btn btn-light-primary">
            <i class="ki-outline ki-delivery-3 fs-4 me-1"></i> Tambah Gudang
        </a>
    @endcan
    @can('admin.branches.create')
        <a href="{{ route('admin.branches.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-shop fs-4 me-1"></i> Tambah Toko / Cabang
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        {{-- KOLOM KIRI: MANAJEMEN GUDANG --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush h-100 shadow-xs border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary me-3">
                            <span class="symbol-label">
                                <i class="ki-outline ki-delivery-3 fs-2 text-primary"></i>
                            </span>
                        </div>
                        <div class="d-flex flex-column">
                            <h3 class="fs-4 fw-bold text-gray-900 mb-0">Manajemen Gudang</h3>
                            <span class="text-muted fs-7">Fasilitas penyimpanan & distribusi logistik</span>
                        </div>
                    </div>
                    <div class="card-toolbar d-flex gap-2">
                        <span class="badge badge-light-primary fw-bold fs-8">Total: {{ $warehouseKpis['total'] }}</span>
                        <span class="badge badge-light-success fw-bold fs-8">Aktif: {{ $warehouseKpis['active'] }}</span>
                    </div>
                </div>

                <div class="card-body pt-4">
                    {{-- Filter Gudang --}}
                    <form method="GET" action="{{ route('admin.operational-locations.index') }}" class="d-flex flex-wrap gap-2 mb-4">
                        <input type="hidden" name="branch_status" value="{{ $branchStatus }}">
                        <input type="hidden" name="primary_warehouse_id" value="{{ $primaryWarehouseId }}">
                        <div class="flex-grow-1 min-w-150px">
                            <input name="warehouse_city" value="{{ $warehouseCity }}" class="form-control form-control-sm form-control-solid" placeholder="Cari kota...">
                        </div>
                        <div class="w-130px">
                            <select name="warehouse_status" class="form-select form-select-sm form-select-solid">
                                <option value="">Semua Status</option>
                                <option value="active" @selected($warehouseStatus === 'active')>Aktif</option>
                                <option value="inactive" @selected($warehouseStatus === 'inactive')>Nonaktif</option>
                            </select>
                        </div>
                        <button class="btn btn-sm btn-light-primary" type="submit">
                            <i class="ki-outline ki-magnifier"></i>
                        </button>
                    </form>

                    {{-- Tabel Gudang --}}
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                                    <th>Gudang</th>
                                    <th>Kota</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($warehouses as $warehouse)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.warehouses.show', $warehouse) }}" class="fw-bold text-gray-900 text-hover-primary fs-7 d-block">
                                                {{ $warehouse->name }}
                                            </a>
                                            <span class="text-muted fs-8 font-monospace">{{ $warehouse->code }}</span>
                                        </td>
                                        <td class="fs-8 text-gray-700">{{ $warehouse->city ?: '-' }}</td>
                                        <td>
                                            @if ($warehouse->is_active)
                                                <span class="badge badge-light-success fs-8">Aktif</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @can('admin.warehouses.view')
                                                    <a href="{{ route('admin.warehouses.show', $warehouse) }}" class="btn btn-icon btn-light btn-sm" title="Detail">
                                                        <i class="ki-outline ki-eye fs-7"></i>
                                                    </a>
                                                @endcan
                                                @can('admin.warehouses.update')
                                                    <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-icon btn-light-primary btn-sm" title="Edit">
                                                        <i class="ki-outline ki-pencil fs-7"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-6 fs-7">
                                            Tidak ada data gudang yang sesuai filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($warehouses->hasPages())
                        <div class="mt-4">
                            {{ $warehouses->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: MANAJEMEN TOKO / CABANG --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush h-100 shadow-xs border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <div class="symbol symbol-40px symbol-circle bg-light-info me-3">
                            <span class="symbol-label">
                                <i class="ki-outline ki-shop fs-2 text-info"></i>
                            </span>
                        </div>
                        <div class="d-flex flex-column">
                            <h3 class="fs-4 fw-bold text-gray-900 mb-0">Manajemen Toko / Cabang</h3>
                            <span class="text-muted fs-7">Outlet retail & gerai operasional</span>
                        </div>
                    </div>
                    <div class="card-toolbar d-flex gap-2">
                        <span class="badge badge-light-info fw-bold fs-8">Total: {{ $branchKpis['total'] }}</span>
                        <span class="badge badge-light-success fw-bold fs-8">Aktif: {{ $branchKpis['active'] }}</span>
                    </div>
                </div>

                <div class="card-body pt-4">
                    {{-- Filter Cabang --}}
                    <form method="GET" action="{{ route('admin.operational-locations.index') }}" class="d-flex flex-wrap gap-2 mb-4">
                        <input type="hidden" name="warehouse_city" value="{{ $warehouseCity }}">
                        <input type="hidden" name="warehouse_status" value="{{ $warehouseStatus }}">
                        <div class="flex-grow-1 min-w-150px">
                            <select name="primary_warehouse_id" class="form-select form-select-sm form-select-solid">
                                <option value="">Semua Gudang Pemasok</option>
                                @foreach ($allActiveWarehouses as $w)
                                    <option value="{{ $w->id }}" @selected((string) $primaryWarehouseId === (string) $w->id)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-130px">
                            <select name="branch_status" class="form-select form-select-sm form-select-solid">
                                <option value="">Semua Status</option>
                                <option value="active" @selected($branchStatus === 'active')>Aktif</option>
                                <option value="inactive" @selected($branchStatus === 'inactive')>Nonaktif</option>
                            </select>
                        </div>
                        <button class="btn btn-sm btn-light-info" type="submit">
                            <i class="ki-outline ki-magnifier"></i>
                        </button>
                    </form>

                    {{-- Tabel Cabang --}}
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                                    <th>Cabang / Toko</th>
                                    <th>Gudang Pemasok</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($branches as $branch)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.branches.show', $branch) }}" class="fw-bold text-gray-900 text-hover-primary fs-7 d-block">
                                                {{ $branch->name }}
                                            </a>
                                            <span class="text-muted fs-8 font-monospace">{{ $branch->code }}</span>
                                        </td>
                                        <td class="fs-8 text-gray-700">
                                            {{ $branch->primaryWarehouse?->name ?: 'Direct Supplier' }}
                                        </td>
                                        <td>
                                            @if ($branch->is_active)
                                                <span class="badge badge-light-success fs-8">Aktif</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @can('admin.branches.view')
                                                    <a href="{{ route('admin.branches.show', $branch) }}" class="btn btn-icon btn-light btn-sm" title="Detail">
                                                        <i class="ki-outline ki-eye fs-7"></i>
                                                    </a>
                                                @endcan
                                                @can('admin.branches.update')
                                                    <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-icon btn-light-primary btn-sm" title="Edit">
                                                        <i class="ki-outline ki-pencil fs-7"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-6 fs-7">
                                            Tidak ada data cabang yang sesuai filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($branches->hasPages())
                        <div class="mt-4">
                            {{ $branches->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
