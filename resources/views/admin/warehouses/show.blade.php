@extends('layouts.metronic.app')

@section('title', 'Detail Gudang - ' . config('app.name'))
@section('page_title', 'Detail Gudang')

@section('page_guide')
    <x-metronic.page-guide id="admin-warehouse-show" title="Panduan Detail Gudang">
        <x-slot:function><p>Halaman menampilkan rincian profil gudang, alamat, dan tab operasional yang berisi histori transaksi dan statistik stok.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kode/Nama/Kota:</strong> info profil gudang.</li><li><strong>Telepon/Kepala Gudang/Kapasitas:</strong> kontak dan kapasitas.</li><li><strong>Area Layanan/Status:</strong> wilayah dan aktif/nonaktif.</li><li><strong>Alamat:</strong> alamat lengkap gudang.</li><li><strong>Tab Operasional:</strong> statistik dan histori transaksi gudang.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Halaman ini read-only. Aksi Edit tersedia untuk mengubah data gudang.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa info profil gudang.</li><li>Buka tab operasional untuk statistik.</li><li>Klik Edit jika perlu mengubah data.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('update', $warehouse)
        <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Profil Gudang">
                <div class="mb-4"><div class="text-muted fs-7">Kode</div><div class="fw-bold fs-4">{{ $warehouse->code }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Nama</div><div class="fw-semibold">{{ $warehouse->name }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kota</div><div class="fw-semibold">{{ $warehouse->city ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Telepon</div><div class="fw-semibold">{{ $warehouse->phone_number ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kepala Gudang</div><div class="fw-semibold">{{ $warehouse->manager?->name ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kapasitas</div><div class="fw-semibold">{{ $warehouse->capacity ? qty($warehouse->capacity) : '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Area Layanan</div><div class="fw-semibold">{{ $warehouse->service_area ?: '-' }}</div></div>
                <x-metronic.status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" :label="$warehouse->is_active ? 'Aktif' : 'Nonaktif'" />
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Alamat">
                <p class="mb-0">{{ $warehouse->address ?: 'Belum ada alamat.' }}</p>
            </x-metronic.card>
            <x-metronic.card title="Tab Operasional" class="mt-6">
                <ul class="nav nav-tabs nav-line-tabs mb-5">
                    <li class="nav-item"><span class="nav-link active">Lokasi Rak</span></li>
                    <li class="nav-item"><span class="nav-link">User</span></li>
                    <li class="nav-item"><span class="nav-link">Cabang Dilayani</span></li>
                    <li class="nav-item"><span class="nav-link">Stok Ringkas</span></li>
                    <li class="nav-item"><span class="nav-link">Histori</span></li>
                </ul>
                <div class="row g-4">
                    <div class="col-md-6"><div class="border rounded p-4"><div class="text-muted fs-7">User Lokasi</div><div class="fw-bold">{{ $warehouse->workLocation?->users->count() ?? 0 }}</div></div></div>
                    <div class="col-md-6"><div class="border rounded p-4"><div class="text-muted fs-7">Cabang Dilayani</div><div class="fw-bold">{{ $warehouse->branches->count() }}</div></div></div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
