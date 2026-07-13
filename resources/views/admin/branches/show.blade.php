@extends('layouts.metronic.app')

@section('title', 'Detail Cabang - ' . config('app.name'))
@section('page_title', 'Detail Cabang')

@section('toolbar_actions')
    @can('update', $branch)
        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Profil Cabang">
                <div class="mb-4"><div class="text-muted fs-7">Kode</div><div class="fw-bold fs-4">{{ $branch->code }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Nama Toko</div><div class="fw-semibold">{{ $branch->name }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Gudang Pemasok</div><div class="fw-semibold">{{ $branch->primaryWarehouse?->name }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kepala Toko</div><div class="fw-semibold">{{ $branch->manager?->name ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Telepon</div><div class="fw-semibold">{{ $branch->phone_number ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Target Penjualan</div><div class="fw-semibold">{{ $branch->sales_target ? \App\Support\CurrencyFormatter::rupiah($branch->sales_target) : '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Harga/Closing</div><div class="fw-semibold">{{ $branch->price_configuration }} · {{ $branch->closing_configuration }} · {{ $branch->is_closing_required ? 'Closing wajib' : 'Closing opsional' }}</div></div>
                <x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" />
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Alamat">
                <p class="mb-0">{{ $branch->address ?: 'Belum ada alamat.' }}</p>
            </x-metronic.card>
            <x-metronic.card title="Tab Operasional" class="mt-6">
                <ul class="nav nav-tabs nav-line-tabs mb-5">
                    <li class="nav-item"><span class="nav-link active">User</span></li>
                    <li class="nav-item"><span class="nav-link">Stok</span></li>
                    <li class="nav-item"><span class="nav-link">Shift</span></li>
                    <li class="nav-item"><span class="nav-link">Performa</span></li>
                    <li class="nav-item"><span class="nav-link">Histori</span></li>
                </ul>
                <div class="row g-4">
                    <div class="col-md-6"><div class="border rounded p-4"><div class="text-muted fs-7">User Lokasi</div><div class="fw-bold">{{ $branch->workLocation?->users->count() ?? 0 }}</div></div></div>
                    <div class="col-md-6"><div class="border rounded p-4"><div class="text-muted fs-7">Gudang Pemasok</div><div class="fw-bold">{{ $branch->primaryWarehouse?->code }}</div></div></div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
